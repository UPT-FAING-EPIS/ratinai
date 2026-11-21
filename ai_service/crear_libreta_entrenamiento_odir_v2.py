"""Genera una libreta reproducible de entrenamiento exploratorio de cuatro clases."""

import base64
import csv
import gzip
import io
import json
from pathlib import Path


base = Path(__file__).parent
filas = list(csv.DictReader((base / "odir_manifest_v2_split.csv").open(encoding="utf-8-sig", newline="")))
campos = ["archivo", "id_paciente", "etiqueta_provisional", "particion"]
minimo = io.StringIO()
escritor = csv.DictWriter(minimo, fieldnames=campos)
escritor.writeheader()
escritor.writerows({campo: fila[campo] for campo in campos} for fila in filas if fila["estado"] == "candidato_clase_unica")
contenido = base64.b64encode(gzip.compress(minimo.getvalue().encode("utf-8"), compresslevel=9)).decode("ascii")


def celda(tipo, fuente):
    datos = {"cell_type": tipo, "metadata": {}, "source": fuente.splitlines(keepends=True)}
    if tipo == "code":
        datos.update(execution_count=None, outputs=[])
    return datos


celdas = [
    celda("markdown", """# RetinAI CNN v2 — primer entrenamiento de cuatro clases

Experimento inicial con imágenes originales ODIR y etiquetas **por ojo**: normal, retinopatía diabética, glaucoma y catarata. Se usan 4.669 imágenes candidatas, divididas por paciente en entrenamiento, validación y prueba interna. Las imágenes con diagnósticos mixtos u otras enfermedades quedan fuera de este primer experimento.

Selecciona **GPU** en Colab y ejecuta todas las celdas en orden. Autoriza el montaje de Drive cuando aparezca: el modelo y los informes se guardarán en `Mi unidad/RETINAI_CNN_V2/`. No uses aún este modelo para decisiones clínicas; falta la revisión visual de calidad y evaluación externa.
"""),
    celda("code", "%pip -q install kagglehub scikit-learn\n"),
    celda("code", f"""import base64
import gzip
import io
from pathlib import Path
import kagglehub
import numpy as np
import pandas as pd
import tensorflow as tf

SEMILLA = 325
tf.keras.utils.set_random_seed(SEMILLA)
CLASES = ["normal", "retinopatia_diabetica", "glaucoma", "catarata"]
MANIFIESTO_GZIP_BASE64 = "{contenido}"
datos = gzip.decompress(base64.b64decode(MANIFIESTO_GZIP_BASE64))
manifiesto = pd.read_csv(io.BytesIO(datos), dtype={{"id_paciente": str}})
assert len(manifiesto) == 4669
assert set(manifiesto["etiqueta_provisional"]) == set(CLASES)
assert manifiesto["archivo"].is_unique
grupos = {{parte: set(grupo["id_paciente"]) for parte, grupo in manifiesto.groupby("particion")}}
assert set(grupos) == {{"entrenamiento", "validacion", "prueba_interna"}}
assert not (grupos["entrenamiento"] & grupos["validacion"])
assert not (grupos["entrenamiento"] & grupos["prueba_interna"])
assert not (grupos["validacion"] & grupos["prueba_interna"])
print("TensorFlow:", tf.__version__, "GPU:", tf.config.list_physical_devices("GPU"))
display(pd.crosstab(manifiesto["particion"], manifiesto["etiqueta_provisional"]))
"""),
    celda("code", """raiz = Path(kagglehub.dataset_download("andrewmvd/ocular-disease-recognition-odir5k"))
carpetas = [p for p in raiz.rglob("Training Images") if p.is_dir()]
if len(carpetas) != 1:
    raise RuntimeError(f"Se esperaba una carpeta Training Images, encontradas: {carpetas}")
imagenes = carpetas[0]
manifiesto["ruta"] = manifiesto["archivo"].map(lambda x: str(imagenes / x))
faltantes = manifiesto.loc[~manifiesto["ruta"].map(lambda x: Path(x).is_file()), "archivo"]
if len(faltantes):
    raise RuntimeError(f"Faltan {len(faltantes)} imágenes, ejemplos: {faltantes.head().tolist()}")
manifiesto["clase_id"] = manifiesto["etiqueta_provisional"].map({c: i for i, c in enumerate(CLASES)})
print("Archivos comprobados:", len(manifiesto))
"""),
    celda("code", """AUTOTUNE = tf.data.AUTOTUNE
TAMANO = 224
LOTE = 24

def abrir_imagen(ruta, etiqueta, entrenamiento=False):
    imagen = tf.io.decode_jpeg(tf.io.read_file(ruta), channels=3)
    imagen = tf.image.convert_image_dtype(imagen, tf.float32)
    imagen = tf.image.resize_with_pad(imagen, TAMANO, TAMANO)
    if entrenamiento:
        imagen = tf.image.random_brightness(imagen, max_delta=0.04)
        imagen = tf.image.random_saturation(imagen, lower=0.90, upper=1.10)
        imagen = tf.clip_by_value(imagen, 0.0, 1.0)
    return imagen * 255.0, etiqueta

def crear_dataset(parte, entrenamiento=False):
    tabla = manifiesto.loc[manifiesto["particion"].eq(parte)].copy()
    ds = tf.data.Dataset.from_tensor_slices((tabla["ruta"].to_numpy(), tabla["clase_id"].to_numpy(dtype="int32")))
    if entrenamiento:
        ds = ds.shuffle(len(tabla), seed=SEMILLA, reshuffle_each_iteration=True)
    ds = ds.map(lambda ruta, clase: abrir_imagen(ruta, clase, entrenamiento), num_parallel_calls=AUTOTUNE)
    return ds.batch(LOTE).prefetch(AUTOTUNE), tabla

train_ds, train_tabla = crear_dataset("entrenamiento", True)
val_ds, val_tabla = crear_dataset("validacion")
test_ds, test_tabla = crear_dataset("prueba_interna")
conteos = train_tabla["clase_id"].value_counts().sort_index()
pesos = {i: float(np.sqrt(conteos.max() / conteos[i])) for i in range(len(CLASES))}
media = np.mean(list(pesos.values()))
pesos = {i: peso / media for i, peso in pesos.items()}
print("Pesos de clase:", pesos)
"""),
    celda("code", """from tensorflow import keras
from tensorflow.keras import layers

base_modelo = keras.applications.EfficientNetB0(
    include_top=False, weights="imagenet", input_shape=(TAMANO, TAMANO, 3), pooling="avg"
)
base_modelo.trainable = False
entrada = keras.Input(shape=(TAMANO, TAMANO, 3))
x = layers.RandomFlip("horizontal", seed=SEMILLA)(entrada)
x = layers.RandomRotation(0.04, seed=SEMILLA)(x)
x = base_modelo(x, training=False)
x = layers.Dropout(0.30)(x)
salida = layers.Dense(len(CLASES), activation="softmax")(x)
modelo = keras.Model(entrada, salida)
modelo.compile(
    optimizer=keras.optimizers.Adam(learning_rate=1e-3),
    loss=keras.losses.SparseCategoricalCrossentropy(),
    metrics=["accuracy"],
)

from google.colab import drive
drive.mount("/content/drive")
destino = Path("/content/drive/MyDrive/RETINAI_CNN_V2")
destino.mkdir(parents=True, exist_ok=True)
modelo_ruta = destino / "clasificador_odir_4clases_exploratorio.keras"
callbacks = [
    keras.callbacks.ModelCheckpoint(str(modelo_ruta), monitor="val_loss", save_best_only=True),
    keras.callbacks.EarlyStopping(monitor="val_loss", patience=3, restore_best_weights=True),
    keras.callbacks.ReduceLROnPlateau(monitor="val_loss", factor=0.5, patience=2),
]
historial = modelo.fit(
    train_ds, validation_data=val_ds, epochs=12,
    class_weight=pesos, callbacks=callbacks,
)
pd.DataFrame(historial.history).to_csv(destino / "historial_odir_4clases.csv", index=False)
print("Modelo guardado:", modelo_ruta)
"""),
    celda("code", """import json
from sklearn.metrics import classification_report, confusion_matrix, balanced_accuracy_score, f1_score

mejor_modelo = keras.models.load_model(modelo_ruta)
probabilidades = mejor_modelo.predict(test_ds)
predichas = probabilidades.argmax(axis=1)
reales = test_tabla["clase_id"].to_numpy()
informe = classification_report(
    reales, predichas, labels=list(range(len(CLASES))),
    target_names=CLASES, output_dict=True, zero_division=0,
)
resumen = {
    "tipo": "evaluacion_interna_exploratoria_no_clinica",
    "fuente": "ODIR-5K Training Images",
    "clases": CLASES,
    "semilla_particion": SEMILLA,
    "pacientes_por_particion": {k: len(v) for k, v in grupos.items()},
    "imagenes_por_particion": manifiesto["particion"].value_counts().to_dict(),
    "f1_macro": float(f1_score(reales, predichas, average="macro")),
    "balanced_accuracy": float(balanced_accuracy_score(reales, predichas)),
    "confusion_matrix": confusion_matrix(reales, predichas, labels=list(range(len(CLASES)))).tolist(),
    "classification_report": informe,
}
informe_ruta = destino / "informe_odir_4clases.json"
informe_ruta.write_text(json.dumps(resumen, ensure_ascii=False, indent=2), encoding="utf-8")
print("F1 macro:", resumen["f1_macro"])
print("Balanced accuracy:", resumen["balanced_accuracy"])
print(classification_report(reales, predichas, labels=list(range(len(CLASES))), target_names=CLASES, zero_division=0))
print("Matriz de confusión (filas reales, columnas predichas):")
print(np.array(resumen["confusion_matrix"]))
print("Informe guardado:", informe_ruta)
"""),
    celda("code", """from google.colab import files
files.download(str(informe_ruta))
"""),
]

notebook = {
    "cells": celdas,
    "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
    "nbformat": 4,
    "nbformat_minor": 5,
}
destino = base / "RetinAI_CNN_V2_Entrenamiento_4Clases.ipynb"
destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
