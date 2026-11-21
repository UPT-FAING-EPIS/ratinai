"""Crea una validacion externa del TFLite del filtro de retinografia."""

import json
from pathlib import Path


BASE = Path(__file__).parent


def celda(tipo: str, fuente: str) -> dict:
    resultado = {"cell_type": tipo, "metadata": {}, "source": fuente.splitlines(keepends=True)}
    if tipo == "code":
        resultado.update(execution_count=None, outputs=[])
    return resultado


CELDAS = [
    celda(
        "markdown",
        """# RetinAI CNN v2: validación externa del filtro de retinografía

Esta libreta **no entrena**. Evalúa el TFLite candidato con el umbral ya calibrado (`0,951782`) sobre fuentes que no participaron en su entrenamiento:

- IDRiD: 102 retinografías de prueba con grado de retinopatía.
- RIM-ONE DL: 174 retinografías de prueba por hospital, normales o con glaucoma.
- OCTMNIST: 1.000 imágenes de tomografía de coherencia óptica, que deben rechazarse porque no son retinografías de fondo de ojo.

El objetivo es detectar si el 100 % anterior fue un atajo de procedencia. Los datos de RIM-ONE se usan según sus condiciones de investigación y no se redistribuyen. Al finalizar se guarda `informe_validacion_externa_filtro_retinografia_v2.json` en Drive.
""",
    ),
    celda("code", "%pip -q install gdown medmnist pillow\n"),
    celda(
        "code",
        '''import hashlib, json, urllib.request, zipfile
from pathlib import Path
import numpy as np
import tensorflow as tf
from PIL import Image, ImageOps
import gdown
import medmnist
from medmnist import OCTMNIST

UMBRAL = 0.9517820477485657
TAMANO = 224
raiz = Path("/content/validacion_filtro_retina")
raiz.mkdir(exist_ok=True)

# IDRiD: se excluye IDRiD_064 por ser copia exacta entre particiones con grado contradictorio.
idrid_zip = Path("/content/idrid_grading.zip")
if not idrid_zip.is_file():
    urllib.request.urlretrieve("https://zenodo.org/records/17219542/files/B.%20Disease%20Grading.zip?download=1", idrid_zip)
if hashlib.md5(idrid_zip.read_bytes()).hexdigest() != "b9239a4b956021a1cf0225522f11f58f":
    raise RuntimeError("La copia IDRiD cambió")
idrid_dir = raiz / "idrid"
idrid_dir.mkdir(exist_ok=True)
with zipfile.ZipFile(idrid_zip) as z:
    nombres = [n for n in z.namelist() if "Testing Set/" in n and "Original Images" in n and n.lower().endswith(".jpg")]
    for nombre in nombres:
        if Path(nombre).name == "IDRiD_064.jpg":
            continue
        (idrid_dir / Path(nombre).name).write_bytes(z.read(nombre))
idrid = sorted(idrid_dir.glob("*.jpg"))
assert len(idrid) == 102, len(idrid)

# RIM-ONE DL: partición de prueba separada por hospital.
rim_zip = Path("/content/rim_one_dl_images.zip")
if not rim_zip.is_file():
    gdown.download(id="1teYi_smpLiNZNJcTWdxXgKKLW2fkUQr4", output=str(rim_zip), quiet=False)
with rim_zip.open("rb") as archivo:
    sha_rim = hashlib.file_digest(archivo, "sha256").hexdigest()
if sha_rim != "85aed5f95c794f52d11b6ed953032108a66576ffcff4b587365df467491b603d":
    raise RuntimeError("La copia RIM-ONE DL cambió")
rim_dir = raiz / "rim_one"
rim_dir.mkdir(exist_ok=True)
with zipfile.ZipFile(rim_zip) as z:
    nombres = [n for n in z.namelist() if "/partitioned_by_hospital/test_set/" in n and n.lower().endswith(".png")]
    for nombre in nombres:
        (rim_dir / Path(nombre).name).write_bytes(z.read(nombre))
rim = sorted(rim_dir.glob("*.png"))
assert len(rim) == 174, len(rim)

# OCTMNIST es otra modalidad oftálmica; se usa la partición oficial de prueba completa.
oct_ds = OCTMNIST(split="test", download=True, size=224)
assert len(oct_ds) == 1000, len(oct_ds)
print("IDRiD", len(idrid), "RIM-ONE", len(rim), "OCT", len(oct_ds))
''',
    ),
    celda(
        "code",
        '''from google.colab import drive
drive.mount("/content/drive")
destino = Path("/content/drive/MyDrive/RETINAI_CNN_V2")
modelo_ruta = destino / "filtro_retinografia_v2_candidato.tflite"
if not modelo_ruta.is_file():
    raise RuntimeError(f"Falta el TFLite: {modelo_ruta}")
interprete = tf.lite.Interpreter(model_path=str(modelo_ruta), num_threads=2)
interprete.allocate_tensors()
entrada = interprete.get_input_details()[0]
salida = interprete.get_output_details()[0]
assert tuple(entrada["shape"]) == (1, TAMANO, TAMANO, 3)
assert tuple(salida["shape"]) == (1, 1)

def predecir_pil(imagen):
    preparada = ImageOps.pad(imagen.convert("RGB"), (TAMANO, TAMANO), method=Image.Resampling.BILINEAR, color=(0, 0, 0))
    lote = np.expand_dims(np.asarray(preparada, dtype=np.float32), 0).astype(entrada["dtype"])
    interprete.set_tensor(entrada["index"], lote)
    interprete.invoke()
    return float(interprete.get_tensor(salida["index"])[0, 0])

def predecir_rutas(rutas):
    valores = []
    for ruta in rutas:
        with Image.open(ruta) as imagen:
            valores.append(predecir_pil(imagen))
    return np.asarray(valores)

p_idrid = predecir_rutas(idrid)
p_rim = predecir_rutas(rim)
p_oct = np.asarray([predecir_pil(Image.fromarray(np.asarray(oct_ds[i][0]).squeeze())) for i in range(len(oct_ds))])
''',
    ),
    celda(
        "code",
        '''def resumen(probabilidades, debe_aceptar):
    aceptadas = probabilidades >= UMBRAL
    return {
        "imagenes": int(len(probabilidades)),
        "debe_aceptar": bool(debe_aceptar),
        "aceptadas": int(aceptadas.sum()),
        "rechazadas": int((~aceptadas).sum()),
        "tasa_correcta": float(aceptadas.mean() if debe_aceptar else (~aceptadas).mean()),
        "probabilidad_media": float(probabilidades.mean()),
        "probabilidad_minima": float(probabilidades.min()),
        "probabilidad_maxima": float(probabilidades.max()),
        "percentiles_05_50_95": [float(x) for x in np.percentile(probabilidades, [5, 50, 95])],
    }

fuentes = {
    "idrid_retinografias": resumen(p_idrid, True),
    "rim_one_retinografias": resumen(p_rim, True),
    "octmnist_no_retinografias": resumen(p_oct, False),
}
verdaderos_positivos = fuentes["idrid_retinografias"]["aceptadas"] + fuentes["rim_one_retinografias"]["aceptadas"]
total_positivos = len(p_idrid) + len(p_rim)
falsos_positivos = fuentes["octmnist_no_retinografias"]["aceptadas"]
informe = {
    "tipo": "validacion_externa_filtro_retinografia_v2",
    "modelo": modelo_ruta.name,
    "umbral": UMBRAL,
    "fuentes": fuentes,
    "sensibilidad_retinografias_externas": float(verdaderos_positivos / total_positivos),
    "especificidad_oct": float((len(p_oct) - falsos_positivos) / len(p_oct)),
    "nota": "No es aprobación clínica; faltan capturas reales del flujo y más modalidades no retinianas.",
}
salida_json = destino / "informe_validacion_externa_filtro_retinografia_v2.json"
salida_json.write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
print(json.dumps(informe, ensure_ascii=False, indent=2))
print("Informe:", salida_json)
from google.colab import files
files.download(str(salida_json))
''',
    ),
]


def main() -> None:
    destino = BASE / "RetinAI_CNN_V2_Validar_Filtro_Retinografia_Externo.ipynb"
    destino.write_text(
        json.dumps(
            {
                "cells": CELDAS,
                "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
                "nbformat": 4,
                "nbformat_minor": 5,
            },
            ensure_ascii=False,
            indent=1,
        ) + "\n",
        encoding="utf-8",
    )
    print(destino)


if __name__ == "__main__":
    main()
