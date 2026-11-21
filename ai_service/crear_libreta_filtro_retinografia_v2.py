"""Crea la libreta del filtro de contenido de RetinAI CNN v2.

El filtro aprende a separar retinografias de dos negativos deliberadamente
distintos: recortes perioculares de LFW e imagenes naturales de Imagenette.
MSHF aporta las retinografias positivas y conserva su particion publicada de
prueba. Las identidades de LFW se separan por hash para evitar que una misma
persona aparezca en mas de una particion.
"""

import base64
import csv
import gzip
import io
import json
import math
from pathlib import Path


BASE = Path(__file__).parent
MANIFIESTO = BASE / "data" / "mshf_manifest_calidad.csv"


def empaquetar_manifiesto() -> str:
    filas = list(csv.DictReader(MANIFIESTO.open(encoding="utf-8")))
    campos = [
        "archivo",
        "ruta_zip",
        "particion_publicada",
        "sha256",
        "formato_real",
    ]
    texto = io.StringIO()
    escritor = csv.DictWriter(texto, fieldnames=campos)
    escritor.writeheader()
    escritor.writerows({campo: fila[campo] for campo in campos} for fila in filas)
    return base64.b64encode(gzip.compress(texto.getvalue().encode(), 9)).decode()


def celda(tipo: str, fuente: str) -> dict:
    resultado = {
        "cell_type": tipo,
        "metadata": {},
        "source": fuente.splitlines(keepends=True),
    }
    if tipo == "code":
        resultado.update(execution_count=None, outputs=[])
    return resultado


PAQUETE = empaquetar_manifiesto()

CELDAS = [
    celda(
        "markdown",
        """# RetinAI CNN v2: filtro de contenido (retinografía / no retinografía)

Esta libreta entrena **solo el primer componente** de la tubería CNN v2. Su salida es la probabilidad de que la entrada sea una retinografía. No evalúa calidad y no diagnostica enfermedades.

Positivos: 1.302 retinografías MSHF de cámaras convencionales, portátiles y de campo amplio. Se conserva la partición pública de prueba y se eliminan duplicados SHA entre particiones.

Negativos difíciles: recortes de la región ocular de LFW, separando identidades por partición. Negativos generales: Imagenette 320 px. La prueba combina fuentes distintas y el umbral se calibra únicamente con validación para limitar la aceptación de imágenes ajenas a 1 %.

Fuentes: MSHF DOI `10.6084/m9.figshare.21507564`; LFW, Huang et al. 2007; Imagenette, Jeremy Howard. El resultado sigue siendo un candidato de investigación: una prueba favorable no equivale a aprobación clínica.

**Ejecuta todo con GPU T4.** Al terminar se guardan en `Mi unidad/RETINAI_CNN_V2/` el Keras, el TFLite y `informe_filtro_retinografia_v2.json`.
""",
    ),
    celda("code", "%pip -q install tensorflow-datasets scikit-learn pillow importlib-resources\n"),
    celda(
        "code",
        f'''import base64, gzip, hashlib, io, json, math, urllib.request, zipfile
from pathlib import Path
import numpy as np
import pandas as pd
import tensorflow as tf
from tensorflow import keras
import tensorflow_datasets as tfds
from PIL import Image
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, confusion_matrix, f1_score, roc_auc_score

SEMILLA = 325
TAMANO = 224
LOTE = 16
tf.keras.utils.set_random_seed(SEMILLA)
DATOS_B64 = "{PAQUETE}"
tabla = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(DATOS_B64))))
assert len(tabla) == 1302 and tabla["archivo"].is_unique
print("GPU:", tf.config.list_physical_devices("GPU"))
if not tf.config.list_physical_devices("GPU"):
    raise RuntimeError("Selecciona GPU T4 en Entorno de ejecución > Cambiar tipo de entorno.")

positivos_desarrollo = tabla[tabla["particion_publicada"].eq("train")].drop_duplicates("sha256").copy()
positivos_prueba = tabla[tabla["particion_publicada"].eq("test")].copy()
positivos_prueba = positivos_prueba[~positivos_prueba["sha256"].isin(positivos_desarrollo["sha256"])].drop_duplicates("sha256").copy()
positivos_entrenamiento, positivos_validacion = train_test_split(
    positivos_desarrollo, test_size=0.20, random_state=SEMILLA
)
assert len(positivos_desarrollo) == 1038 and len(positivos_prueba) == 252
assert not set(positivos_desarrollo["sha256"]) & set(positivos_prueba["sha256"])
print("Retinografías:", len(positivos_entrenamiento), len(positivos_validacion), len(positivos_prueba))
''',
    ),
    celda(
        "code",
        '''zip_ruta = Path("/content/mshf_dataset_2.zip")
if not zip_ruta.is_file():
    print("Descargando MSHF (~1,14 GB)...", flush=True)
    urllib.request.urlretrieve("https://ndownloader.figshare.com/files/39485878", zip_ruta)
with zip_ruta.open("rb") as archivo:
    sha_mshf = hashlib.file_digest(archivo, "sha256").hexdigest()
if sha_mshf != "115def6513bc1516d77ceb4ac2e90a9fcf582e825604c08be9524f5fd6c4a219":
    raise RuntimeError(f"El ZIP de MSHF cambió: {sha_mshf}")

carpeta = Path("/content/mshf_contenido")
carpeta.mkdir(exist_ok=True)
elegidas = pd.concat([positivos_entrenamiento, positivos_validacion, positivos_prueba]).drop_duplicates("archivo")
with zipfile.ZipFile(zip_ruta) as z:
    for fila in elegidas.itertuples():
        contenido = z.read(fila.ruta_zip)
        if fila.formato_real == "TIFF":
            destino = carpeta / (Path(fila.archivo).stem + ".png")
            with Image.open(io.BytesIO(contenido)) as imagen:
                imagen.convert("RGB").save(destino, format="PNG")
        elif fila.formato_real == "JPEG":
            destino = carpeta / fila.archivo
            destino.write_bytes(contenido)
        else:
            raise RuntimeError(f"Formato inesperado: {fila.archivo} ({fila.formato_real})")

for grupo in (positivos_entrenamiento, positivos_validacion, positivos_prueba):
    grupo["ruta"] = grupo.apply(
        lambda fila: str(carpeta / (Path(fila["archivo"]).stem + ".png"))
        if fila["formato_real"] == "TIFF" else str(carpeta / fila["archivo"]), axis=1
    )
print("Retinografías extraídas:", len(elegidas))
''',
    ),
    celda(
        "code",
        '''AUTO = tf.data.AUTOTUNE

def preparar_retina(ruta, entrenar=False):
    imagen = tf.io.decode_image(tf.io.read_file(ruta), channels=3, expand_animations=False)
    imagen.set_shape((None, None, 3))
    imagen = tf.image.convert_image_dtype(imagen, tf.float32)
    imagen = tf.image.resize_with_pad(imagen, TAMANO, TAMANO)
    if entrenar:
        imagen = tf.image.random_flip_left_right(imagen)
        imagen = tf.image.random_brightness(imagen, 0.08)
    return tf.clip_by_value(imagen, 0., 1.) * 255., tf.constant(1., tf.float32), tf.constant("mshf")

def preparar_lfw(ejemplo, entrenar=False):
    imagen = tf.image.convert_image_dtype(ejemplo["image"], tf.float32)
    # LFW está alineado a 250x250. Este recorte conserva ojos, cejas y puente nasal.
    imagen = tf.image.crop_to_bounding_box(imagen, 45, 20, 125, 210)
    imagen = tf.image.resize_with_pad(imagen, TAMANO, TAMANO)
    if entrenar:
        imagen = tf.image.random_flip_left_right(imagen)
    return imagen * 255., tf.constant(0., tf.float32), tf.constant("lfw_periocular")

def preparar_imagenette(ejemplo, entrenar=False):
    imagen = tf.image.convert_image_dtype(ejemplo["image"], tf.float32)
    imagen = tf.image.resize_with_pad(imagen, TAMANO, TAMANO)
    if entrenar:
        imagen = tf.image.random_flip_left_right(imagen)
    return imagen * 255., tf.constant(0., tf.float32), tf.constant("imagenette")

def ds_retina(filas, entrenar=False):
    ds = tf.data.Dataset.from_tensor_slices(filas["ruta"].to_numpy())
    if entrenar:
        ds = ds.shuffle(len(filas), seed=SEMILLA, reshuffle_each_iteration=True)
    return ds.map(lambda ruta: preparar_retina(ruta, entrenar), num_parallel_calls=AUTO)

lfw = tfds.load("lfw", split="train", shuffle_files=False)
def particion_lfw(ejemplo):
    return tf.strings.to_hash_bucket_fast(ejemplo["label"], 100)
lfw_train = lfw.filter(lambda x: particion_lfw(x) < 70).shuffle(5000, seed=SEMILLA, reshuffle_each_iteration=False).take(len(positivos_entrenamiento))
lfw_val = lfw.filter(lambda x: (particion_lfw(x) >= 70) & (particion_lfw(x) < 85)).shuffle(3000, seed=SEMILLA, reshuffle_each_iteration=False).take(len(positivos_validacion))
lfw_test = lfw.filter(lambda x: particion_lfw(x) >= 85).shuffle(3000, seed=SEMILLA, reshuffle_each_iteration=False).take(len(positivos_prueba))

img_train = tfds.load("imagenette/320px-v2", split=f"train[:{len(positivos_entrenamiento)}]", shuffle_files=False)
img_val = tfds.load("imagenette/320px-v2", split=f"train[-{len(positivos_validacion)}:]", shuffle_files=False)
img_test = tfds.load("imagenette/320px-v2", split=f"validation[:{len(positivos_prueba)}]", shuffle_files=False)

train_unbatched = ds_retina(positivos_entrenamiento, True).concatenate(
    lfw_train.map(lambda x: preparar_lfw(x, True), num_parallel_calls=AUTO)
).concatenate(
    img_train.map(lambda x: preparar_imagenette(x, True), num_parallel_calls=AUTO)
).shuffle(len(positivos_entrenamiento) * 3, seed=SEMILLA, reshuffle_each_iteration=True)

val_unbatched = ds_retina(positivos_validacion).concatenate(
    lfw_val.map(lambda x: preparar_lfw(x), num_parallel_calls=AUTO)
).concatenate(
    img_val.map(lambda x: preparar_imagenette(x), num_parallel_calls=AUTO)
)
test_unbatched = ds_retina(positivos_prueba).concatenate(
    lfw_test.map(lambda x: preparar_lfw(x), num_parallel_calls=AUTO)
).concatenate(
    img_test.map(lambda x: preparar_imagenette(x), num_parallel_calls=AUTO)
)

def quitar_fuente(imagen, etiqueta, fuente):
    return imagen, etiqueta
train_ds = train_unbatched.map(quitar_fuente, num_parallel_calls=AUTO).batch(LOTE).prefetch(AUTO)
val_ds = val_unbatched.map(quitar_fuente, num_parallel_calls=AUTO).batch(LOTE).prefetch(AUTO)
test_ds = test_unbatched.map(quitar_fuente, num_parallel_calls=AUTO).batch(LOTE).prefetch(AUTO)
print("Ejemplos esperados:", len(positivos_entrenamiento) * 3, len(positivos_validacion) * 3, len(positivos_prueba) * 3)
''',
    ),
    celda(
        "code",
        '''from google.colab import drive
drive.mount("/content/drive")
destino = Path("/content/drive/MyDrive/RETINAI_CNN_V2")
destino.mkdir(parents=True, exist_ok=True)
modelo_ruta = destino / "filtro_retinografia_v2_candidato.keras"

base_cnn = keras.applications.EfficientNetB0(
    include_top=False, weights="imagenet", input_shape=(TAMANO, TAMANO, 3), pooling="avg"
)
base_cnn.trainable = False
entrada = keras.Input(shape=(TAMANO, TAMANO, 3))
x = base_cnn(entrada, training=False)
x = keras.layers.Dropout(0.30)(x)
salida = keras.layers.Dense(1, activation="sigmoid")(x)
modelo = keras.Model(entrada, salida)
modelo.compile(
    optimizer=keras.optimizers.Adam(1e-3),
    loss="binary_crossentropy",
    metrics=[keras.metrics.AUC(name="auc")],
)
guardar = keras.callbacks.ModelCheckpoint(modelo_ruta, monitor="val_auc", mode="max", save_best_only=True)
parar = keras.callbacks.EarlyStopping(monitor="val_auc", mode="max", patience=2, restore_best_weights=True)
# Hay dos familias negativas por cada positiva; estos pesos equilibran ambas clases.
pasos_entrenamiento = math.ceil(len(positivos_entrenamiento) * 3 / LOTE)
pasos_validacion = math.ceil(len(positivos_validacion) * 3 / LOTE)
historia_1 = modelo.fit(
    train_ds, validation_data=val_ds, epochs=7,
    steps_per_epoch=pasos_entrenamiento, validation_steps=pasos_validacion,
    class_weight={0: 0.75, 1: 1.5}, callbacks=[guardar, parar]
)

modelo = keras.models.load_model(modelo_ruta)
base = next(capa for capa in modelo.layers if isinstance(capa, keras.Model))
base.trainable = True
for capa in base.layers[:-30]:
    capa.trainable = False
modelo.compile(optimizer=keras.optimizers.Adam(1e-5), loss="binary_crossentropy", metrics=[keras.metrics.AUC(name="auc")])
historia_2 = modelo.fit(
    train_ds, validation_data=val_ds, epochs=4,
    steps_per_epoch=pasos_entrenamiento, validation_steps=pasos_validacion,
    class_weight={0: 0.75, 1: 1.5}, callbacks=[guardar, parar]
)
modelo = keras.models.load_model(modelo_ruta)
''',
    ),
    celda(
        "code",
        '''def recolectar(ds_con_fuente, modelo):
    probabilidades, etiquetas, fuentes = [], [], []
    for imagenes, y, fuente in ds_con_fuente.batch(LOTE):
        probabilidades.extend(modelo.predict(imagenes, verbose=0).reshape(-1).tolist())
        etiquetas.extend(y.numpy().astype("int32").tolist())
        fuentes.extend([x.decode() for x in fuente.numpy().tolist()])
    return np.asarray(etiquetas), np.asarray(probabilidades), np.asarray(fuentes)

y_val, p_val, fuente_val = recolectar(val_unbatched, modelo)
candidatos = np.unique(np.r_[np.linspace(0.05, 0.99, 189), p_val])
opciones = []
for umbral in candidatos:
    pred = (p_val >= umbral).astype("int32")
    negativos = y_val == 0
    positivos = y_val == 1
    fpr = float(pred[negativos].mean())
    sensibilidad = float(pred[positivos].mean())
    if fpr <= 0.01:
        opciones.append((sensibilidad, -fpr, float(umbral)))
if not opciones:
    raise RuntimeError("No se pudo calibrar un umbral con FPR de validación <= 1 %")
_, _, umbral = max(opciones)
print("Umbral calibrado:", umbral)

y_test, p_test, fuente_test = recolectar(test_unbatched, modelo)
pred_test = (p_test >= umbral).astype("int32")
matriz = confusion_matrix(y_test, pred_test, labels=[0, 1])
tn, fp, fn, tp = matriz.ravel()
por_fuente = {}
for fuente in sorted(set(fuente_test)):
    mascara = fuente_test == fuente
    por_fuente[fuente] = {
        "imagenes": int(mascara.sum()),
        "aceptadas_como_retinografia": int(pred_test[mascara].sum()),
        "probabilidad_media": float(p_test[mascara].mean()),
    }

convertidor = tf.lite.TFLiteConverter.from_keras_model(modelo)
ruta_tflite = destino / "filtro_retinografia_v2_candidato.tflite"
ruta_tflite.write_bytes(convertidor.convert())
interprete = tf.lite.Interpreter(model_path=str(ruta_tflite))
interprete.allocate_tensors()
assert tuple(interprete.get_input_details()[0]["shape"]) == (1, TAMANO, TAMANO, 3)
assert tuple(interprete.get_output_details()[0]["shape"]) == (1, 1)

informe = {
    "tipo": "prueba_exploratoria_filtro_retinografia_v2",
    "clases": {"0": "no_retinografia", "1": "retinografia"},
    "fuentes_negativas": ["LFW recorte periocular", "Imagenette 320px"],
    "separacion_lfw": "identidad separada por hash 70/15/15",
    "imagenes_prueba": int(len(y_test)),
    "umbral_calibrado_validacion": float(umbral),
    "objetivo_fpr_validacion": 0.01,
    "matriz_confusion_prueba": matriz.tolist(),
    "sensibilidad_retinografia_prueba": float(tp / (tp + fn)),
    "especificidad_no_retinografia_prueba": float(tn / (tn + fp)),
    "falsas_aceptaciones_prueba": int(fp),
    "falsos_rechazos_prueba": int(fn),
    "auc_prueba": float(roc_auc_score(y_test, p_test)),
    "f1_retinografia_prueba": float(f1_score(y_test, pred_test)),
    "reporte": classification_report(y_test, pred_test, labels=[0, 1], target_names=["no_retinografia", "retinografia"], output_dict=True, zero_division=0),
    "por_fuente": por_fuente,
    "mshf_zip_sha256": sha_mshf,
    "modelo_tflite": str(ruta_tflite),
    "limitaciones": [
        "LFW no es un conjunto clínico y sus recortes perioculares son negativos aproximados.",
        "No se han evaluado todas las modalidades oftálmicas no retinianas ni capturas de teléfonos del despliegue real.",
        "El candidato requiere una prueba prospectiva con imágenes ajenas reales antes de aprobación.",
    ],
}
salida = destino / "informe_filtro_retinografia_v2.json"
salida.write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
print("Matriz:", matriz)
print("Sensibilidad:", informe["sensibilidad_retinografia_prueba"], "especificidad:", informe["especificidad_no_retinografia_prueba"])
print("Informe:", salida)
from google.colab import files
files.download(str(salida))
''',
    ),
]


def main() -> None:
    destino = BASE / "RetinAI_CNN_V2_Entrenar_Filtro_Retinografia.ipynb"
    destino.write_text(
        json.dumps(
            {
                "cells": CELDAS,
                "metadata": {
                    "kernelspec": {
                        "display_name": "Python 3",
                        "language": "python",
                        "name": "python3",
                    }
                },
                "nbformat": 4,
                "nbformat_minor": 5,
            },
            ensure_ascii=False,
            indent=1,
        )
        + "\n",
        encoding="utf-8",
    )
    print(destino)


if __name__ == "__main__":
    main()
