"""Crea una libreta para inspeccionar los errores de validación de CNN v2."""

import base64
import csv
import gzip
import io
import json
from pathlib import Path


base = Path(__file__).parent
filas = list(csv.DictReader((base / "odir_manifest_v2_split.csv").open(encoding="utf-8-sig", newline="")))
filas = [fila for fila in filas if fila["particion"] == "validacion" and fila["estado"] == "candidato_clase_unica"]
campos = ["archivo", "id_paciente", "etiqueta_provisional"]
texto = io.StringIO()
escritor = csv.DictWriter(texto, fieldnames=campos)
escritor.writeheader()
escritor.writerows({campo: fila[campo] for campo in campos} for fila in filas)
datos_b64 = base64.b64encode(gzip.compress(texto.getvalue().encode(), 9)).decode()


def celda(tipo, fuente):
    datos = {"cell_type": tipo, "metadata": {}, "source": fuente.splitlines(keepends=True)}
    if tipo == "code":
        datos.update(execution_count=None, outputs=[])
    return datos


celdas = [
    celda("markdown", """# RetinAI CNN v2 — revisión de errores

Esta libreta **no entrena**. Calcula predicciones de los dos modelos ya guardados sobre las mismas 673 imágenes de validación ODIR y crea láminas de ejemplos confundidos con «normal». Sirve para revisar imágenes y anotaciones antes de modificar otra vez el entrenamiento. Puede ejecutarse sin GPU.

Ejecuta todas las celdas y comparte `retinai_errores_validacion.zip`.
"""),
    celda("code", "%pip -q install kagglehub pillow\n"),
    celda("code", f"""import base64
import gzip
import io
from pathlib import Path
import kagglehub
import numpy as np
import pandas as pd
import tensorflow as tf
from tensorflow import keras

CLASES = ["normal", "retinopatia_diabetica", "glaucoma", "catarata"]
DATOS_B64 = "{datos_b64}"
tabla = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(DATOS_B64))), dtype={{"id_paciente": str}})
assert len(tabla) == 673 and tabla["archivo"].is_unique
tabla = tabla.sort_values("archivo").reset_index(drop=True)
tabla["clase_id"] = tabla["etiqueta_provisional"].map({{c: i for i, c in enumerate(CLASES)}})
raiz = Path(kagglehub.dataset_download("andrewmvd/ocular-disease-recognition-odir5k"))
carpetas = [p for p in raiz.rglob("Training Images") if p.is_dir()]
if len(carpetas) != 1:
    raise RuntimeError(f"Carpetas Training Images encontradas: {{carpetas}}")
tabla["ruta"] = tabla["archivo"].map(lambda x: str(carpetas[0] / x))
if not tabla["ruta"].map(lambda x: Path(x).is_file()).all():
    raise RuntimeError("Faltan imágenes de validación")
print("Imágenes:", len(tabla))
"""),
    celda("code", """def abrir(ruta, clase):
    imagen = tf.io.decode_jpeg(tf.io.read_file(ruta), channels=3)
    imagen = tf.image.convert_image_dtype(imagen, tf.float32)
    imagen = tf.image.resize_with_pad(imagen, 224, 224)
    return imagen * 255.0, clase

ds = tf.data.Dataset.from_tensor_slices((
    tabla["ruta"].to_numpy(), tabla["clase_id"].to_numpy(dtype="int32")
)).map(abrir, num_parallel_calls=tf.data.AUTOTUNE).batch(24).prefetch(tf.data.AUTOTUNE)

from google.colab import drive
drive.mount("/content/drive")
destino = Path("/content/drive/MyDrive/RETINAI_CNN_V2")
rutas_modelos = {
    "base": destino / "clasificador_odir_4clases_exploratorio.keras",
    "nuevo": destino / "clasificador_odir_sylhet_4clases_exploratorio.keras",
}
if any(not ruta.is_file() for ruta in rutas_modelos.values()):
    raise RuntimeError(f"Faltan archivos de modelo: {rutas_modelos}")
probabilidades = {}
for nombre, ruta in rutas_modelos.items():
    print("Evaluando:", nombre)
    probabilidades[nombre] = keras.models.load_model(ruta).predict(ds, verbose=1)
    tabla[f"pred_{nombre}"] = [CLASES[i] for i in probabilidades[nombre].argmax(axis=1)]
    tabla[f"conf_{nombre}"] = probabilidades[nombre].max(axis=1).round(4)
    for i, clase in enumerate(CLASES):
        tabla[f"p_{clase}_{nombre}"] = probabilidades[nombre][:, i].round(4)

salida_csv = Path("/content/retinai_errores_validacion.csv")
tabla.drop(columns=["ruta", "clase_id"]).to_csv(salida_csv, index=False)
print("Errores de retinopatía como normal:", int((tabla["etiqueta_provisional"].eq("retinopatia_diabetica") & tabla["pred_nuevo"].eq("normal")).sum()))
print("Errores de glaucoma como normal:", int((tabla["etiqueta_provisional"].eq("glaucoma") & tabla["pred_nuevo"].eq("normal")).sum()))
"""),
    celda("code", """from PIL import Image, ImageDraw, ImageOps
from zipfile import ZipFile, ZIP_DEFLATED

def lamina(filas, titulo, salida, limite=24):
    filas = filas.head(limite)
    columnas = 4
    ancho_celda, alto_celda = 260, 270
    filas_n = max(1, (len(filas) + columnas - 1) // columnas)
    lienzo = Image.new("RGB", (columnas * ancho_celda, 45 + filas_n * alto_celda), "white")
    dibujo = ImageDraw.Draw(lienzo)
    dibujo.text((10, 10), f"{titulo} (n={len(filas)})", fill="black")
    for pos, (_, fila) in enumerate(filas.iterrows()):
        x = (pos % columnas) * ancho_celda
        y = 45 + (pos // columnas) * alto_celda
        with Image.open(fila["ruta"]) as original:
            imagen = ImageOps.contain(original.convert("RGB"), (248, 218))
            lienzo.paste(imagen, (x + (ancho_celda - imagen.width) // 2, y))
        dibujo.text((x + 5, y + 220), str(fila["archivo"]), fill="black")
        dibujo.text((x + 5, y + 237), f"Real:{fila['etiqueta_provisional'][:6]} B:{fila['pred_base'][:6]} N:{fila['pred_nuevo'][:6]}", fill="black")
        dibujo.text((x + 5, y + 252), f"P(normal) nuevo: {fila['p_normal_nuevo']:.2f}", fill="black")
    lienzo.save(salida)

carpeta = Path("/content/retinai_laminas")
carpeta.mkdir(exist_ok=True)
dr = tabla.loc[
    tabla["etiqueta_provisional"].eq("retinopatia_diabetica") &
    tabla["pred_nuevo"].eq("normal")
].sort_values("p_normal_nuevo", ascending=False)
glaucoma = tabla.loc[
    tabla["etiqueta_provisional"].eq("glaucoma") &
    tabla["pred_nuevo"].eq("normal")
].sort_values("p_normal_nuevo", ascending=False)
aciertos_glaucoma = tabla.loc[
    tabla["etiqueta_provisional"].eq("glaucoma") &
    tabla["pred_nuevo"].eq("glaucoma")
].sort_values("p_glaucoma_nuevo", ascending=False)

lamina(dr, "Retinopatía confundida con normal", carpeta / "retinopatia_como_normal.png")
lamina(glaucoma, "Glaucoma confundido con normal", carpeta / "glaucoma_como_normal.png")
lamina(aciertos_glaucoma, "Glaucoma reconocido", carpeta / "glaucoma_reconocido.png")

paquete = Path("/content/retinai_errores_validacion.zip")
with ZipFile(paquete, "w", ZIP_DEFLATED) as zipf:
    zipf.write(salida_csv, arcname=salida_csv.name)
    for imagen in carpeta.glob("*.png"):
        zipf.write(imagen, arcname=imagen.name)
print("Paquete:", paquete)
"""),
    celda("code", """from google.colab import files
files.download(str(paquete))
"""),
]

notebook = {
    "cells": celdas,
    "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
    "nbformat": 4,
    "nbformat_minor": 5,
}
destino = base / "RetinAI_CNN_V2_Revision_Errores.ipynb"
destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
