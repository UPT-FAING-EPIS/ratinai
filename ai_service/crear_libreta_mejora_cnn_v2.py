"""Crea una segunda prueba CNN v2 con Sylhet depurado como entrenamiento."""

import base64
import csv
import gzip
import io
import json
import random
from collections import Counter
from pathlib import Path


base = Path(__file__).parent


def comprimir(filas, campos):
    texto = io.StringIO()
    escritor = csv.DictWriter(texto, fieldnames=campos)
    escritor.writeheader()
    escritor.writerows({campo: fila[campo] for campo in campos} for fila in filas)
    return base64.b64encode(gzip.compress(texto.getvalue().encode(), 9)).decode()


odir = list(csv.DictReader((base / "odir_manifest_v2_split.csv").open(encoding="utf-8-sig", newline="")))
odir = [fila for fila in odir if fila["estado"] == "candidato_clase_unica"]
sylhet = list(csv.DictReader((base / "sylhet_manifest_v2.csv").open(encoding="utf-8-sig", newline="")))
sylhet = [fila for fila in sylhet if fila["estado"] == "candidato_adicional_no_validado"]
sylhet_elegido = []
for etiqueta in ("normal", "diabetic_retinopathy", "glaucoma"):
    grupo = [fila for fila in sylhet if fila["etiqueta_carpeta"] == etiqueta]
    if len(grupo) < 500:
        raise ValueError(f"No hay suficientes imágenes Sylhet de {etiqueta}")
    sylhet_elegido.extend(random.Random(325).sample(sorted(grupo, key=lambda x: x["relativo"]), 500))
if Counter(f["etiqueta_carpeta"] for f in sylhet_elegido) != {"normal": 500, "diabetic_retinopathy": 500, "glaucoma": 500}:
    raise ValueError("Muestra Sylhet incompleta")

odir_b64 = comprimir(odir, ["archivo", "id_paciente", "etiqueta_provisional", "particion"])
sylhet_b64 = comprimir(sylhet_elegido, ["relativo", "etiqueta_carpeta", "sha256"])


def celda(tipo, texto):
    datos = {"cell_type": tipo, "metadata": {}, "source": texto.splitlines(keepends=True)}
    if tipo == "code":
        datos.update(execution_count=None, outputs=[])
    return datos


celdas = [
    celda("markdown", """# RetinAI CNN v2 — segundo experimento

El primer modelo tuvo baja sensibilidad para retinopatía diabética y glaucoma. Esta libreta **continúa** sus pesos, descongela las capas superiores y añade al entrenamiento 500 imágenes de Sylhet por clase (normal, retinopatía diabética y glaucoma). Se excluyeron archivos con etiquetas contradictorias y 15 posibles copias de ODIR. La catarata de Sylhet se excluye porque mezcla retinografías y fotografías externas del ojo.

Las imágenes Sylhet solo van al entrenamiento. Se comparan ambos modelos en la **validación ODIR**, no se vuelve a consultar la prueba interna. La validación no demuestra rendimiento en otras cámaras ni seguridad clínica. Selecciona GPU y ejecuta las celdas en orden.
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
from tensorflow import keras

SEMILLA = 325
tf.keras.utils.set_random_seed(SEMILLA)
CLASES = ["normal", "retinopatia_diabetica", "glaucoma", "catarata"]
ODIR_B64 = "{odir_b64}"
SYLHET_B64 = "{sylhet_b64}"
odir = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(ODIR_B64))), dtype={{"id_paciente": str}})
sylhet = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(SYLHET_B64))))
assert len(odir) == 4669 and len(sylhet) == 1500
assert not odir["archivo"].duplicated().any()
assert not sylhet["sha256"].duplicated().any()
print("GPU:", tf.config.list_physical_devices("GPU"))
if not tf.config.list_physical_devices("GPU"):
    raise RuntimeError("Selecciona GPU en Entorno de ejecución > Cambiar tipo de entorno antes de entrenar.")
display(pd.crosstab(odir["particion"], odir["etiqueta_provisional"]))
display(sylhet["etiqueta_carpeta"].value_counts())
"""),
    celda("code", """raiz_odir = Path(kagglehub.dataset_download("andrewmvd/ocular-disease-recognition-odir5k"))
raiz_sylhet = Path(kagglehub.dataset_download("mahin661/eye-disease-classification-fundus-image-dataset"))
carpetas = [p for p in raiz_odir.rglob("Training Images") if p.is_dir()]
if len(carpetas) != 1:
    raise RuntimeError(f"Carpetas ODIR Training Images encontradas: {carpetas}")
odir["ruta"] = odir["archivo"].map(lambda x: str(carpetas[0] / x))
sylhet["ruta"] = sylhet["relativo"].map(lambda x: str(raiz_sylhet / x))
sylhet["etiqueta_provisional"] = sylhet["etiqueta_carpeta"].replace({"diabetic_retinopathy": "retinopatia_diabetica"})
sylhet["particion"] = "entrenamiento"
sylhet["id_paciente"] = "sin_id_sylhet"
faltantes = [p for p in list(odir["ruta"]) + list(sylhet["ruta"]) if not Path(p).is_file()]
if faltantes:
    raise RuntimeError(f"Faltan {len(faltantes)} imágenes; ejemplo: {faltantes[:3]}")
tabla = pd.concat([odir, sylhet], ignore_index=True)
tabla["clase_id"] = tabla["etiqueta_provisional"].map({c: i for i, c in enumerate(CLASES)})
assert tabla["clase_id"].notna().all()
assert len(tabla.loc[tabla["particion"].eq("validacion")]) == 673
print("Imágenes disponibles:", len(tabla))
display(pd.crosstab(tabla["particion"], tabla["etiqueta_provisional"]))
"""),
    celda("code", """AUTOTUNE = tf.data.AUTOTUNE
TAMANO = 224
LOTE = 24

def abrir(ruta, clase, entrenamiento=False):
    imagen = tf.io.decode_jpeg(tf.io.read_file(ruta), channels=3)
    imagen = tf.image.convert_image_dtype(imagen, tf.float32)
    imagen = tf.image.resize_with_pad(imagen, TAMANO, TAMANO)
    if entrenamiento:
        imagen = tf.image.random_brightness(imagen, 0.04)
        imagen = tf.image.random_saturation(imagen, 0.90, 1.10)
        imagen = tf.clip_by_value(imagen, 0.0, 1.0)
    return imagen * 255.0, clase

def dataset(parte, entrenamiento=False):
    sub = tabla.loc[tabla["particion"].eq(parte)].copy()
    ds = tf.data.Dataset.from_tensor_slices((sub["ruta"].to_numpy(), sub["clase_id"].to_numpy(dtype="int32")))
    if entrenamiento:
        ds = ds.shuffle(len(sub), seed=SEMILLA, reshuffle_each_iteration=True)
    ds = ds.map(lambda ruta, clase: abrir(ruta, clase, entrenamiento), num_parallel_calls=AUTOTUNE)
    return ds.batch(LOTE).prefetch(AUTOTUNE), sub

train_ds, train_tabla = dataset("entrenamiento", True)
val_ds, val_tabla = dataset("validacion")
conteos = train_tabla["clase_id"].value_counts().sort_index()
pesos = {i: float(np.sqrt(conteos.max() / conteos[i])) for i in range(len(CLASES))}
media = np.mean(list(pesos.values()))
pesos = {i: valor / media for i, valor in pesos.items()}
print("Pesos:", pesos)
"""),
    celda("code", """from google.colab import drive
from sklearn.metrics import classification_report, f1_score, balanced_accuracy_score
drive.mount("/content/drive")
destino = Path("/content/drive/MyDrive/RETINAI_CNN_V2")
modelo_base_ruta = destino / "clasificador_odir_4clases_exploratorio.keras"
if not modelo_base_ruta.is_file():
    raise RuntimeError(f"No se encontró el modelo anterior: {modelo_base_ruta}")
modelo_base = keras.models.load_model(modelo_base_ruta)
reales = val_tabla["clase_id"].to_numpy()
predichas_base = modelo_base.predict(val_ds).argmax(axis=1)
base_f1 = float(f1_score(reales, predichas_base, average="macro"))
base_sensibilidad = float(balanced_accuracy_score(reales, predichas_base))
print("Modelo anterior, validación ODIR:")
print(classification_report(reales, predichas_base, labels=list(range(4)), target_names=CLASES, zero_division=0))
print("F1 macro:", base_f1, "balanced accuracy:", base_sensibilidad)
"""),
    celda("code", """base_cnn = next((capa for capa in modelo_base.layers if isinstance(capa, keras.Model) and "efficientnet" in capa.name.lower()), None)
if base_cnn is None:
    raise RuntimeError("No se encontró EfficientNet dentro del modelo anterior")
base_cnn.trainable = True
for capa in base_cnn.layers[:-40]:
    capa.trainable = False
for capa in base_cnn.layers:
    if isinstance(capa, keras.layers.BatchNormalization):
        capa.trainable = False

modelo_base.compile(
    optimizer=keras.optimizers.Adam(learning_rate=1e-5),
    loss=keras.losses.SparseCategoricalCrossentropy(),
    metrics=["accuracy"],
)
modelo_nuevo_ruta = destino / "clasificador_odir_sylhet_4clases_exploratorio.keras"
callbacks = [
    keras.callbacks.ModelCheckpoint(str(modelo_nuevo_ruta), monitor="val_loss", save_best_only=True),
    keras.callbacks.EarlyStopping(monitor="val_loss", patience=2, restore_best_weights=True),
]
historial = modelo_base.fit(
    train_ds, validation_data=val_ds, epochs=6,
    class_weight=pesos, callbacks=callbacks,
)
pd.DataFrame(historial.history).to_csv(destino / "historial_odir_sylhet_4clases.csv", index=False)
"""),
    celda("code", """import json
from sklearn.metrics import confusion_matrix
modelo_nuevo = keras.models.load_model(modelo_nuevo_ruta)
predichas_nuevo = modelo_nuevo.predict(val_ds).argmax(axis=1)
nuevo_f1 = float(f1_score(reales, predichas_nuevo, average="macro"))
nueva_sensibilidad = float(balanced_accuracy_score(reales, predichas_nuevo))
informe = {
    "tipo": "comparacion_en_validacion_odir_no_clinica",
    "clases": CLASES,
    "imagenes_odir_entrenamiento": 3309,
    "imagenes_sylhet_entrenamiento": 1500,
    "imagenes_validacion_odir": len(reales),
    "modelo_base": {
        "f1_macro": base_f1,
        "balanced_accuracy": base_sensibilidad,
        "classification_report": classification_report(reales, predichas_base, labels=list(range(4)), target_names=CLASES, output_dict=True, zero_division=0),
        "confusion_matrix": confusion_matrix(reales, predichas_base, labels=list(range(4))).tolist(),
    },
    "modelo_nuevo": {
        "f1_macro": nuevo_f1,
        "balanced_accuracy": nueva_sensibilidad,
        "classification_report": classification_report(reales, predichas_nuevo, labels=list(range(4)), target_names=CLASES, output_dict=True, zero_division=0),
        "confusion_matrix": confusion_matrix(reales, predichas_nuevo, labels=list(range(4))).tolist(),
    },
}
ruta_informe = destino / "comparacion_validacion_odir_sylhet.json"
ruta_informe.write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
print("F1 macro anterior:", base_f1, "nuevo:", nuevo_f1)
print("Balanced accuracy anterior:", base_sensibilidad, "nuevo:", nueva_sensibilidad)
print(classification_report(reales, predichas_nuevo, labels=list(range(4)), target_names=CLASES, zero_division=0))
print("Modelo nuevo:", modelo_nuevo_ruta)
print("Informe:", ruta_informe)
"""),
    celda("code", """from google.colab import files
files.download(str(ruta_informe))
"""),
]

notebook = {
    "cells": celdas,
    "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
    "nbformat": 4,
    "nbformat_minor": 5,
}
destino = base / "RetinAI_CNN_V2_Mejora_Sylhet.ipynb"
destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
