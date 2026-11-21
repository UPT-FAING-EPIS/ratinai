"""Genera una prueba externa de retinopatía en IDRiD sin reentrenar."""

import base64
import csv
import gzip
import io
import json
from pathlib import Path


base = Path(__file__).parent
manifiesto = list(csv.DictReader((base / "data" / "idrid" / "manifest_idrid.csv").open(encoding="utf-8-sig", newline="")))
prueba = [r for r in manifiesto if r["particion_oficial"] == "prueba" and r["cruza_particiones_idrid"] == "0"]
if len(prueba) != 102:
    raise ValueError(f"Se esperaban 102 imágenes de prueba no duplicadas, hay {len(prueba)}")
campos = ["imagen", "grado_retinopatia"]
texto = io.StringIO()
escritor = csv.DictWriter(texto, fieldnames=campos)
escritor.writeheader()
escritor.writerows({campo: r[campo] for campo in campos} for r in prueba)
datos = base64.b64encode(gzip.compress(texto.getvalue().encode(), 9)).decode()


def celda(tipo, fuente):
    c = {"cell_type": tipo, "metadata": {}, "source": fuente.splitlines(keepends=True)}
    if tipo == "code":
        c.update(execution_count=None, outputs=[])
    return c


celdas = [
    celda("markdown", """# RetinAI CNN v2: prueba independiente de retinopatía

Esta libreta **no entrena**. Evalúa los modelos ODIR de 224 y 384 px en imágenes de prueba de IDRiD con grado de retinopatía anotado por expertos. IDRiD proporciona grados 0–4, no etiquetas de glaucoma o catarata; por tanto, el informe mide solamente **retinopatía frente a ausencia de retinopatía**, no exactitud de las cuatro clases.

Se excluye una imagen de prueba idéntica a otra del entrenamiento IDRiD con grado contradictorio. Quedan 102 imágenes. Ejecuta todas las celdas y envía `informe_idrid_externo.json`. Puede ejecutarse con CPU, aunque una GPU acelera la predicción. La descarga del conjunto de grading es de aproximadamente 212 MB.

Fuente original: Porwal et al., IDRiD (IEEE DataPort); copia verificada de Zenodo, DOI 10.5281/zenodo.17219542, CC BY 4.0.
"""),
    celda("code", "%pip -q install scikit-learn\n"),
    celda("code", f"""import base64
import gzip
import hashlib
import io
from pathlib import Path
import urllib.request
import zipfile
import numpy as np
import pandas as pd
import tensorflow as tf
from tensorflow import keras

DATOS_B64 = "{datos}"
tabla = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(DATOS_B64))))
assert len(tabla) == 102
assert not tabla["imagen"].duplicated().any()
destino_zip = Path("/content/idrid_grading.zip")
url = "https://zenodo.org/records/17219542/files/B.%20Disease%20Grading.zip?download=1"
if not destino_zip.is_file():
    print("Descargando IDRiD...", flush=True)
    urllib.request.urlretrieve(url, destino_zip)
md5 = hashlib.md5(destino_zip.read_bytes()).hexdigest()
if md5 != "b9239a4b956021a1cf0225522f11f58f":
    raise RuntimeError(f"MD5 inesperado: {{md5}}")
carpeta = Path("/content/idrid_prueba")
carpeta.mkdir(exist_ok=True)
with zipfile.ZipFile(destino_zip) as z:
    nombres = z.namelist()
    for imagen in tabla["imagen"]:
        candidatos = [n for n in nombres if n.endswith("/" + imagen) and "Testing Set/" in n and "Original Images" in n]
        if len(candidatos) != 1:
            raise RuntimeError(f"Imagen no encontrada de forma única: {{imagen}}")
        (carpeta / imagen).write_bytes(z.read(candidatos[0]))
tabla["ruta"] = tabla["imagen"].map(lambda x: str(carpeta / x))
tabla["con_retinopatia"] = tabla["grado_retinopatia"].gt(0).astype(int)
print("Prueba IDRiD:", len(tabla), "grados:", tabla["grado_retinopatia"].value_counts().sort_index().to_dict())
"""),
    celda("code", """from google.colab import drive
drive.mount("/content/drive")
modelos = {
    "odir_224": Path("/content/drive/MyDrive/RETINAI_CNN_V2/clasificador_odir_4clases_exploratorio.keras"),
    "odir_384": Path("/content/drive/MyDrive/RETINAI_CNN_V2/clasificador_odir_384_exploratorio.keras"),
}
if any(not p.is_file() for p in modelos.values()):
    raise RuntimeError(f"Falta alguno de los modelos guardados: {modelos}")

def dataset(tamano):
    def abrir(ruta, grado):
        imagen = tf.io.decode_jpeg(tf.io.read_file(ruta), channels=3)
        imagen = tf.image.convert_image_dtype(imagen, tf.float32)
        imagen = tf.image.resize_with_pad(imagen, tamano, tamano)
        return imagen * 255.0, grado
    ds = tf.data.Dataset.from_tensor_slices((
        tabla["ruta"].to_numpy(), tabla["grado_retinopatia"].to_numpy(dtype="int32")
    ))
    return ds.map(abrir, num_parallel_calls=tf.data.AUTOTUNE).batch(12).prefetch(tf.data.AUTOTUNE)

for nombre, ruta in modelos.items():
    tamano = 224 if nombre == "odir_224" else 384
    prob = keras.models.load_model(ruta).predict(dataset(tamano), verbose=1)
    tabla[f"p_dr_{nombre}"] = prob[:, 1]
    tabla[f"pred_clase_{nombre}"] = prob.argmax(axis=1)
"""),
    celda("code", """import json
from sklearn.metrics import roc_auc_score, confusion_matrix

y = tabla["con_retinopatia"].to_numpy()
resultados = {}
for nombre in modelos:
    puntaje = tabla[f"p_dr_{nombre}"].to_numpy()
    pred = tabla[f"pred_clase_{nombre}"].eq(1).astype(int).to_numpy()
    matriz = confusion_matrix(y, pred, labels=[0, 1])
    tn, fp, fn, tp = matriz.ravel()
    por_grado = {
        str(grado): {
            "imagenes": len(grupo),
            "predichas_como_dr": int(grupo[f"pred_clase_{nombre}"].eq(1).sum()),
        }
        for grado, grupo in tabla.groupby("grado_retinopatia")
    }
    resultados[nombre] = {
        "auc_dr_vs_sin_dr": float(roc_auc_score(y, puntaje)),
        "matriz_umbral_argmax": matriz.tolist(),
        "sensibilidad_dr_argmax": float(tp / (tp + fn)),
        "especificidad_sin_dr_argmax": float(tn / (tn + fp)),
        "por_grado": por_grado,
    }
    print(nombre, resultados[nombre])

informe = {
    "tipo": "prueba_externa_idrid_retinopatia_no_clinica",
    "imagenes": len(tabla),
    "exclusion": "IDRiD_064.jpg de prueba: SHA idéntico a IDRiD_118.jpg de entrenamiento, grado contradictorio",
    "resultados": resultados,
}
salida = Path("/content/drive/MyDrive/RETINAI_CNN_V2/informe_idrid_externo.json")
salida.write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
print("Informe:", salida)
from google.colab import files
files.download(str(salida))
"""),
]

notebook = {
    "cells": celdas,
    "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
    "nbformat": 4,
    "nbformat_minor": 5,
}
destino = base / "RetinAI_CNN_V2_Prueba_IDRiD.ipynb"
destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
