"""Genera una libreta breve para auditar las anotaciones originales de ODIR."""

import json
from pathlib import Path


base = Path(__file__).parent
destino = base / "RetinAI_CNN_V2_ODIR_Por_Ojo.ipynb"


def celda(tipo, texto):
    datos = {"cell_type": tipo, "metadata": {}, "source": texto.splitlines(keepends=True)}
    if tipo == "code":
        datos.update(execution_count=None, outputs=[])
    return datos


notebook = {
    "cells": [
        celda(
            "markdown",
            """# RetinAI CNN v2: etiquetas originales por ojo

La unión anterior con `full_df.csv` cubrió 6.392 de 7.000 imágenes. Esta libreta lee `data.xlsx` del mismo ODIR, que contiene los archivos izquierdo y derecho y sus palabras diagnósticas. Exporta la asociación sin convertir automáticamente el texto en una clase clínica.
""",
        ),
        celda("code", "%pip -q install kagglehub pandas openpyxl\n"),
        celda(
            "code",
            """from pathlib import Path
import kagglehub
import pandas as pd

raiz = Path(kagglehub.dataset_download("andrewmvd/ocular-disease-recognition-odir5k"))
archivos = sorted(raiz.rglob("data.xlsx"))
print("data.xlsx encontrados:", [str(p) for p in archivos])
if len(archivos) != 1:
    raise RuntimeError("Se esperaba un solo data.xlsx; revisar la carpeta descargada.")

libro = pd.ExcelFile(archivos[0])
print("Hojas:", libro.sheet_names)
oficial = pd.read_excel(archivos[0], sheet_name=0)
print("Pacientes:", len(oficial))
print("Columnas:", oficial.columns.tolist())
display(oficial.head(3))
""",
        ),
        celda(
            "code",
            """esperadas = [
    "ID", "Left-Fundus", "Right-Fundus",
    "Left-Diagnostic Keywords", "Right-Diagnostic Keywords"
]
faltantes = [c for c in esperadas if c not in oficial.columns]
if faltantes:
    raise RuntimeError(f"Faltan columnas {faltantes}; revisar el esquema antes de unir.")

clases = [c for c in ["N", "D", "G", "C", "A", "H", "M", "O"] if c in oficial.columns]
partes = []
for lado, imagen_col, texto_col in [
    ("left", "Left-Fundus", "Left-Diagnostic Keywords"),
    ("right", "Right-Fundus", "Right-Diagnostic Keywords")
]:
    seleccion = oficial[["ID", imagen_col, texto_col] + clases].copy()
    seleccion = seleccion.rename(columns={
        "ID": "id_paciente", imagen_col: "archivo", texto_col: "diagnostico_texto_ojo"
    })
    seleccion["ojo"] = lado
    partes.append(seleccion)

por_ojo = pd.concat(partes, ignore_index=True)
por_ojo["archivo"] = por_ojo["archivo"].astype("string").map(
    lambda x: Path(x).name if pd.notna(x) else pd.NA
)
imagen_dir = next((p for p in raiz.rglob("Training Images") if p.is_dir()), None)
if imagen_dir is None:
    raise RuntimeError("No se encontró Training Images.")
imagenes = pd.DataFrame({"archivo": [p.name for p in imagen_dir.iterdir() if p.is_file() and p.suffix.lower() in {".jpg", ".jpeg", ".png"}]})
print("Imágenes originales:", len(imagenes))
print("Archivos repetidos en data.xlsx:", int(por_ojo["archivo"].duplicated().sum()))
if por_ojo["archivo"].duplicated().any():
    raise RuntimeError("Hay nombres de imagen repetidos en data.xlsx; no se puede unir sin revisar.")

union = imagenes.merge(por_ojo, on="archivo", how="left", indicator=True, validate="one_to_one")
print("Resultado de unión:")
print(union["_merge"].value_counts(dropna=False).to_string())
print("Pacientes unidos:", union.loc[union["_merge"].eq("both"), "id_paciente"].nunique())
print("Diagnóstico por ojo vacío:", int(union["diagnostico_texto_ojo"].isna().sum()))
print("Ejemplos sin unión:")
display(union.loc[union["_merge"].ne("both")].head(20))

salida = "/content/retinai_odir_por_ojo.csv"
union.to_csv(salida, index=False)
print("Guardado:", salida)
""",
        ),
        celda(
            "code",
            """from google.colab import files
files.download("/content/retinai_odir_por_ojo.csv")
""",
        ),
    ],
    "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
    "nbformat": 4,
    "nbformat_minor": 5,
}

destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
