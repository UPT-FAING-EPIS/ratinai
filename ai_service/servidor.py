"""API privada para la CNN de validación y clasificación de RetinAI."""

import hmac
import io
import json
import os
import threading
import time
from pathlib import Path

import numpy as np
import tensorflow as tf
from fastapi import FastAPI, File, Header, HTTPException, UploadFile
from PIL import Image, UnidentifiedImageError

DIRECTORIO_BASE = Path(__file__).resolve().parent
RUTA_MODELO = Path(os.getenv("RETINAI_RUTA_MODELO", DIRECTORIO_BASE / "modelo" / "modelo_retinai.tflite"))
RUTA_INFORMACION_MODELO = Path(os.getenv(
    "RETINAI_RUTA_INFORMACION_MODELO",
    DIRECTORIO_BASE / "modelo" / "informacion_modelo.json",
))
CLAVE_API = os.getenv("RETINAI_CLAVE_API", "")
TAMANO_MAXIMO_ARCHIVO = 10 * 1024 * 1024
TIPOS_CONTENIDO_PERMITIDOS = {"image/jpeg", "image/png"}
CLASES_CLINICAS_ESPERADAS = ["normal", "diabetes", "glaucoma", "catarata"]
CANTIDAD_SALIDAS_VALIDACION = 2

if not RUTA_MODELO.is_file() or not RUTA_INFORMACION_MODELO.is_file():
    raise RuntimeError("Faltan modelo_retinai.tflite o informacion_modelo.json en el directorio modelo.")
if not CLAVE_API:
    raise RuntimeError("RETINAI_CLAVE_API debe estar definida en el entorno del servicio.")

with RUTA_INFORMACION_MODELO.open("r", encoding="utf-8") as archivo_informacion:
    INFORMACION_MODELO = json.load(archivo_informacion)

CLASES_CLINICAS = INFORMACION_MODELO["clases_clinicas"]
VERSION_MODELO = str(INFORMACION_MODELO["version"]).strip()
UMBRAL_CLINICO = float(INFORMACION_MODELO["umbral_clinico"])
UMBRAL_RETINOGRAFIA = float(INFORMACION_MODELO["umbral_retinografia"])
UMBRAL_EVALUABLE = float(INFORMACION_MODELO["umbral_evaluable"])
TAMANO_ENTRADA = tuple(INFORMACION_MODELO["tamano_entrada"])

if not VERSION_MODELO:
    raise RuntimeError("La versión del modelo no puede estar vacía.")
if CLASES_CLINICAS != CLASES_CLINICAS_ESPERADAS or TAMANO_ENTRADA != (224, 224):
    raise RuntimeError("La información del modelo no coincide con el contrato RF-10 de RetinAI.")

INTERPRETE = tf.lite.Interpreter(model_path=str(RUTA_MODELO), num_threads=1)
INTERPRETE.allocate_tensors()
DETALLE_ENTRADA = INTERPRETE.get_input_details()[0]
DETALLE_SALIDA = INTERPRETE.get_output_details()[0]
BLOQUEO_INTERPRETE = threading.Lock()

aplicacion = FastAPI(
    title="API de inferencia RetinAI",
    version=VERSION_MODELO,
    docs_url=None,
    redoc_url=None,
)


def exigir_clave_api(clave_recibida: str | None) -> None:
    """Rechaza la solicitud antes de procesar archivos cuando la clave no coincide."""
    if not clave_recibida or not hmac.compare_digest(clave_recibida, CLAVE_API):
        raise HTTPException(status_code=401, detail="No autorizado")


def preparar_entrada(contenido: bytes) -> np.ndarray:
    """Valida y transforma la imagen según el contrato de entrada del modelo."""
    try:
        with Image.open(io.BytesIO(contenido)) as imagen:
            imagen.verify()
        with Image.open(io.BytesIO(contenido)) as imagen:
            imagen = imagen.convert("RGB").resize(TAMANO_ENTRADA, Image.Resampling.LANCZOS)
            valores = np.asarray(imagen, dtype=np.float32)
    except (UnidentifiedImageError, OSError, Image.DecompressionBombError) as error:
        raise HTTPException(status_code=422, detail="La imagen JPG o PNG no es válida.") from error

    lote = np.expand_dims(valores, axis=0)
    if DETALLE_ENTRADA["dtype"] in (np.uint8, np.int8):
        escala, punto_cero = DETALLE_ENTRADA["quantization"]
        if escala == 0:
            raise RuntimeError("La cuantización de entrada del modelo es inválida.")
        limites = np.iinfo(DETALLE_ENTRADA["dtype"])
        lote = np.clip(np.round(lote / escala + punto_cero), limites.min, limites.max)
        lote = lote.astype(DETALLE_ENTRADA["dtype"])
    else:
        lote = lote.astype(DETALLE_ENTRADA["dtype"])
    return lote


def inferir(lote: np.ndarray) -> np.ndarray:
    """Ejecuta el intérprete bajo un bloqueo para evitar escrituras concurrentes."""
    with BLOQUEO_INTERPRETE:
        INTERPRETE.set_tensor(DETALLE_ENTRADA["index"], lote)
        INTERPRETE.invoke()
        salida = INTERPRETE.get_tensor(DETALLE_SALIDA["index"])[0].astype(np.float32)

    if DETALLE_SALIDA["dtype"] in (np.uint8, np.int8):
        escala, punto_cero = DETALLE_SALIDA["quantization"]
        if escala == 0:
            raise RuntimeError("La cuantización de salida del modelo es inválida.")
        salida = (salida - punto_cero) * escala

    cantidad_esperada = CANTIDAD_SALIDAS_VALIDACION + len(CLASES_CLINICAS)
    if salida.shape[0] != cantidad_esperada:
        raise RuntimeError("La CNN no contiene las dos salidas de validación y las cuatro clases clínicas.")
    return np.clip(salida, 0.0, 1.0)


@aplicacion.get("/health")
def comprobar_estado():
    return {
        "estado": "correcto",
        "modelo_version": VERSION_MODELO,
        "clases_clinicas": CLASES_CLINICAS,
        "valida_retinografia": True,
        "valida_calidad": True,
    }


@aplicacion.post("/api/analizar")
async def analizar(archivo: UploadFile = File(...), x_api_key: str | None = Header(default=None)):
    exigir_clave_api(x_api_key)
    if archivo.content_type not in TIPOS_CONTENIDO_PERMITIDOS:
        raise HTTPException(status_code=415, detail="Solo se admiten imágenes JPG o PNG.")

    contenido = await archivo.read(TAMANO_MAXIMO_ARCHIVO + 1)
    if not contenido or len(contenido) > TAMANO_MAXIMO_ARCHIVO:
        raise HTTPException(status_code=413, detail="La imagen debe pesar como máximo 10 MB.")

    inicio = time.perf_counter()
    salida = inferir(preparar_entrada(contenido))
    probabilidad_retinografia = float(salida[0])
    probabilidad_evaluable = float(salida[1])
    es_retinografia = probabilidad_retinografia >= UMBRAL_RETINOGRAFIA
    es_evaluable = es_retinografia and probabilidad_evaluable >= UMBRAL_EVALUABLE

    motivo_rechazo = None
    if not es_retinografia:
        motivo_rechazo = "La imagen no fue identificada como una retinografía."
    elif not es_evaluable:
        motivo_rechazo = "La retinografía no alcanza la calidad mínima para una interpretación clínica."

    respuesta = {
        "modelo_version": VERSION_MODELO,
        "validacion": {
            "es_retinografia": es_retinografia,
            "probabilidad_retinografia": round(probabilidad_retinografia * 100, 2),
            "es_evaluable": es_evaluable,
            "probabilidad_calidad": round(probabilidad_evaluable * 100, 2),
            "motivo_rechazo": motivo_rechazo,
        },
        "tiempo_analisis": round(time.perf_counter() - inicio, 3),
    }
    if not es_evaluable:
        return respuesta

    salidas_clinicas = salida[CANTIDAD_SALIDAS_VALIDACION:]
    probabilidades = {
        etiqueta: round(float(valor) * 100, 2)
        for etiqueta, valor in zip(CLASES_CLINICAS, salidas_clinicas)
    }
    enfermedades = [etiqueta for etiqueta in CLASES_CLINICAS if etiqueta != "normal"]
    hallazgos = [
        etiqueta
        for etiqueta in enfermedades
        if salidas_clinicas[CLASES_CLINICAS.index(etiqueta)] >= UMBRAL_CLINICO
    ]
    resultado = max(hallazgos, key=lambda etiqueta: salidas_clinicas[CLASES_CLINICAS.index(etiqueta)]) \
        if hallazgos else "normal"

    respuesta.update({
        "resultado_principal": resultado,
        "probabilidad_principal": probabilidades[resultado],
        "probabilidades": probabilidades,
        "hallazgos": hallazgos,
        "alerta_anomalia": bool(hallazgos),
        "es_referencial": True,
        "umbral_clinico": UMBRAL_CLINICO,
    })
    return respuesta
