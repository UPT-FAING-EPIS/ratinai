"""Genera una libreta de inventario para las seis fuentes candidatas."""

import json
from pathlib import Path


def celda(tipo, texto):
    datos = {"cell_type": tipo, "metadata": {}, "source": texto.splitlines(keepends=True)}
    if tipo == "code":
        datos.update(execution_count=None, outputs=[])
    return datos


notebook = {
    "cells": [
        celda("markdown", """# RetinAI CNN v2: comparación de seis fuentes

Ejecuta todas las celdas en orden y envía `retinai_fuentes_v2.csv`. Esta libreta inventaría los archivos y calcula una huella visual aproximada para buscar copias redimensionadas o recoloreadas. La huella **no confirma** que dos imágenes sean iguales ni valida sus diagnósticos.

Usa un entorno de ejecución estándar, sin GPU. La descarga de los seis conjuntos puede tardar y consumir espacio temporal de Colab.
"""),
        celda("code", "%pip -q install kagglehub pillow imagehash\n"),
        celda("code", """from pathlib import Path
import kagglehub

FUENTES = {
    "odir_original": "andrewmvd/ocular-disease-recognition-odir5k",
    "sylhet": "mahin661/eye-disease-classification-fundus-image-dataset",
    "cuatro_original": "gunavenkatdoddi/eye-diseases-classification",
    "cuatro_procesado": "gunavenkatdoddi/preprocessed-eye-diseases-fundus-images",
    "odir_carpetas": "tanjemahamed/odir5k-classification",
    "odir_clahe": "efiyearcan/odir-5k-preprocessed-with-clahe",
}
rutas = {}
for nombre, referencia in FUENTES.items():
    print("Descargando:", nombre, flush=True)
    rutas[nombre] = Path(kagglehub.dataset_download(referencia))
    print("Disponible:", rutas[nombre], flush=True)
"""),
        celda("code", """import csv
import hashlib
import re
from PIL import Image, ImageStat
import imagehash

salida = Path("/content/retinai_fuentes_v2.csv")
campos = [
    "fuente", "relativo", "archivo", "etiqueta_carpeta", "id_paciente_sugerido",
    "bytes", "sha256", "phash", "ancho", "alto", "brillo_medio",
    "rojo_medio", "verde_medio", "azul_medio", "error"
]
totales = {}
with salida.open("w", encoding="utf-8", newline="") as destino:
    escritor = csv.DictWriter(destino, fieldnames=campos)
    escritor.writeheader()
    for fuente, raiz in rutas.items():
        imagenes = sorted(p for p in raiz.rglob("*") if p.is_file() and p.suffix.lower() in {".jpg", ".jpeg", ".png"})
        totales[fuente] = len(imagenes)
        print("Inventariando", fuente, len(imagenes), flush=True)
        for indice, ruta in enumerate(imagenes, 1):
            nombre = ruta.name
            fila = dict.fromkeys(campos, "")
            fila.update(
                fuente=fuente, relativo=str(ruta.relative_to(raiz)), archivo=nombre,
                etiqueta_carpeta=ruta.parent.name, bytes=ruta.stat().st_size,
                id_paciente_sugerido=(ruta.stem.split("_")[0]
                    if re.match(r"^\\d+_(?:left|right)$", ruta.stem, re.I) else ""),
            )
            try:
                digest = hashlib.sha256()
                with ruta.open("rb") as entrada:
                    for bloque in iter(lambda: entrada.read(1024 * 1024), b""):
                        digest.update(bloque)
                fila["sha256"] = digest.hexdigest()
                with Image.open(ruta) as imagen:
                    imagen.load()
                    fila["ancho"], fila["alto"] = imagen.size
                    rgb = imagen.convert("RGB")
                    fila["phash"] = str(imagehash.phash(rgb))
                    rgb.thumbnail((256, 256))
                    rojo, verde, azul = ImageStat.Stat(rgb).mean
                    fila["rojo_medio"] = round(rojo, 2)
                    fila["verde_medio"] = round(verde, 2)
                    fila["azul_medio"] = round(azul, 2)
                    fila["brillo_medio"] = round(ImageStat.Stat(rgb.convert("L")).mean[0], 2)
            except (OSError, ValueError) as error:
                fila["error"] = str(error)[:200]
            escritor.writerow(fila)
            if indice % 2000 == 0:
                print(fuente, indice, "/", len(imagenes), flush=True)
print("Totales:", totales)
print("Archivo:", salida)
"""),
        celda("code", """from google.colab import files
files.download("/content/retinai_fuentes_v2.csv")
"""),
    ],
    "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
    "nbformat": 4,
    "nbformat_minor": 5,
}

destino = Path(__file__).parent / "RetinAI_CNN_V2_Seis_Fuentes.ipynb"
destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
