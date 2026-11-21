"""Genera la evaluación externa exploratoria de ODIR 224/384 en Sylhet."""

import base64
import csv
import gzip
import io
import json
from pathlib import Path


base = Path(__file__).parent
filas = list(csv.DictReader((base / "sylhet_manifest_v2.csv").open(encoding="utf-8-sig", newline="")))
filas = [fila for fila in filas if fila["estado"] == "candidato_adicional_no_validado"]
unicos = {fila["sha256"]: fila for fila in filas}
campos = ["relativo", "etiqueta_carpeta", "rojo_medio", "azul_medio", "brillo_medio"]
texto = io.StringIO()
escritor = csv.DictWriter(texto, fieldnames=campos)
escritor.writeheader()
escritor.writerows({campo: fila[campo] for campo in campos} for fila in unicos.values())
datos = base64.b64encode(gzip.compress(texto.getvalue().encode(), 9)).decode()


def celda(tipo, fuente):
    datos_celda = {"cell_type": tipo, "metadata": {}, "source": fuente.splitlines(keepends=True)}
    if tipo == "code":
        datos_celda.update(execution_count=None, outputs=[])
    return datos_celda


celdas = [
    celda("markdown", """# RetinAI CNN v2: prueba externa de tonalidades

Ejecuta todas las celdas y envía el archivo `informe_sylhet_externo.json`. Esta libreta **no entrena**: compara los dos modelos entrenados solo con ODIR (224 y 384 px) en imágenes Sylhet. Se excluyeron catarata, etiquetas contradictorias, duplicados exactos y 15 imágenes visualmente cercanas a ODIR por pHash.

Sylhet no tiene aquí identificadores de paciente ni validación clínica individual. Este informe es una prueba exploratoria de cambio de fuente y color; no constituye una evaluación clínica independiente definitiva. Puede ejecutarse con GPU para mayor velocidad.
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

DATOS_B64 = "{datos}"
tabla = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(DATOS_B64))))
assert len(tabla) == {len(unicos)}
CLASES = ["normal", "retinopatia_diabetica", "glaucoma", "catarata"]
tabla["clase_real"] = tabla["etiqueta_carpeta"].replace({{"diabetic_retinopathy": "retinopatia_diabetica"}})
tabla["clase_id"] = tabla["clase_real"].map({{c: i for i, c in enumerate(CLASES)}})
tabla["azul_rojo"] = tabla["azul_medio"] / tabla["rojo_medio"].clip(lower=1)
tabla["cuartil_color"] = pd.qcut(tabla["azul_rojo"], 4, labels=False, duplicates="drop")
raiz = Path(kagglehub.dataset_download("mahin661/eye-disease-classification-fundus-image-dataset"))
tabla["ruta"] = tabla["relativo"].map(lambda x: str(raiz / x))
if not tabla["ruta"].map(lambda x: Path(x).is_file()).all():
    raise RuntimeError("Faltan imágenes Sylhet")
print("Imágenes:", len(tabla), "GPU:", tf.config.list_physical_devices("GPU"))
display(pd.crosstab(tabla["cuartil_color"], tabla["clase_real"]))
"""),
    celda("code", """from google.colab import drive
drive.mount("/content/drive")
modelos = {
    "odir_224": Path("/content/drive/MyDrive/RETINAI_CNN_V2/clasificador_odir_4clases_exploratorio.keras"),
    "odir_384": Path("/content/drive/MyDrive/RETINAI_CNN_V2/clasificador_odir_384_exploratorio.keras"),
}
if any(not ruta.is_file() for ruta in modelos.values()):
    raise RuntimeError(f"Falta alguno de los modelos guardados: {modelos}")

def dataset(tamano):
    def abrir(ruta, clase):
        imagen = tf.io.decode_jpeg(tf.io.read_file(ruta), channels=3)
        imagen = tf.image.convert_image_dtype(imagen, tf.float32)
        imagen = tf.image.resize_with_pad(imagen, tamano, tamano)
        return imagen * 255.0, clase
    ds = tf.data.Dataset.from_tensor_slices((
        tabla["ruta"].to_numpy(), tabla["clase_id"].to_numpy(dtype="int32")
    ))
    return ds.map(abrir, num_parallel_calls=tf.data.AUTOTUNE).batch(16).prefetch(tf.data.AUTOTUNE)

for nombre, ruta in modelos.items():
    tamano = 224 if nombre == "odir_224" else 384
    probabilidades = keras.models.load_model(ruta).predict(dataset(tamano), verbose=1)
    tabla[f"pred_{nombre}"] = probabilidades.argmax(axis=1)
    print(nombre, "evaluado:", len(tabla))
"""),
    celda("code", """import json
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix

reales = tabla["clase_id"].to_numpy()
resultados = {}
for nombre in modelos:
    pred = tabla[f"pred_{nombre}"].to_numpy()
    por_color = {}
    for cuartil, grupo in tabla.groupby("cuartil_color"):
        y = grupo["clase_id"].to_numpy()
        yp = grupo[f"pred_{nombre}"].to_numpy()
        por_color[str(int(cuartil))] = {
            "imagenes": len(grupo),
            "exactitud": float(accuracy_score(y, yp)),
            "sensibilidad_por_clase": {
                clase: float((yp[y == i] == i).mean()) if (y == i).any() else None
                for i, clase in enumerate(CLASES[:3])
            },
        }
    resultados[nombre] = {
        "exactitud": float(accuracy_score(reales, pred)),
        "classification_report": classification_report(
            reales, pred, labels=list(range(4)), target_names=CLASES,
            output_dict=True, zero_division=0,
        ),
        "confusion_matrix": confusion_matrix(reales, pred, labels=list(range(4))).tolist(),
        "por_cuartil_color": por_color,
    }
    print(nombre, "exactitud:", resultados[nombre]["exactitud"])
    print("Filas reales, columnas predichas:")
    print(np.array(resultados[nombre]["confusion_matrix"]))

informe = {
    "tipo": "prueba_externa_exploratoria_sylhet_sin_catarata",
    "fuente": "Sylhet",
    "imagenes": len(tabla),
    "nota": "No hay IDs de paciente; se quitaron duplicados SHA y 15 posibles copias pHash. No es validación clínica definitiva.",
    "modelos": resultados,
}
ruta_informe = Path("/content/drive/MyDrive/RETINAI_CNN_V2/informe_sylhet_externo.json")
ruta_informe.write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
print("Informe:", ruta_informe)
from google.colab import files
files.download(str(ruta_informe))
"""),
]

notebook = {
    "cells": celdas,
    "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
    "nbformat": 4,
    "nbformat_minor": 5,
}
destino = base / "RetinAI_CNN_V2_Prueba_Externa_Sylhet.ipynb"
destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
