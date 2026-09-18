"""API privada de inferencia para el modelo RetinAI TFLite.

El servicio recibe una retinografía, ejecuta el modelo multietiqueta y retorna
probabilidades en porcentaje. No sustituye una valoración clínica.
"""

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

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = Path(os.getenv("RETINAI_MODEL_PATH", BASE_DIR / "model" / "retinai_model.tflite"))
CLASS_INFO_PATH = Path(os.getenv("RETINAI_CLASS_INFO_PATH", BASE_DIR / "model" / "class_info.json"))
API_KEY = os.getenv("RETINAI_API_KEY", "")
MAX_FILE_SIZE = 10 * 1024 * 1024
ALLOWED_CONTENT_TYPES = {"image/jpeg", "image/png"}

if not MODEL_PATH.is_file() or not CLASS_INFO_PATH.is_file():
    raise RuntimeError("Faltan retinai_model.tflite o class_info.json en el directorio model.")
if not API_KEY:
    raise RuntimeError("RETINAI_API_KEY debe estar definida en el entorno del servicio.")

with CLASS_INFO_PATH.open("r", encoding="utf-8") as file:
    CLASS_INFO = json.load(file)

CLASSES = CLASS_INFO["classes"]
THRESHOLD = float(CLASS_INFO["threshold"])
INPUT_SIZE = tuple(CLASS_INFO["input_size"])
if CLASSES != ["normal", "diabetes", "glaucoma", "catarata"] or INPUT_SIZE != (224, 224):
    raise RuntimeError("class_info.json no coincide con el contrato esperado de RetinAI.")

interpreter = tf.lite.Interpreter(model_path=str(MODEL_PATH), num_threads=1)
interpreter.allocate_tensors()
INPUT_DETAILS = interpreter.get_input_details()[0]
OUTPUT_DETAILS = interpreter.get_output_details()[0]
INTERPRETER_LOCK = threading.Lock()

app = FastAPI(title="RetinAI Inference API", version=str(CLASS_INFO.get("version", "1.0")), docs_url=None, redoc_url=None)


def require_api_key(x_api_key: str | None) -> None:
    if not x_api_key or not __import__("hmac").compare_digest(x_api_key, API_KEY):
        raise HTTPException(status_code=401, detail="No autorizado")


def prepare_input(content: bytes) -> np.ndarray:
    try:
        with Image.open(io.BytesIO(content)) as image:
            image.verify()
        with Image.open(io.BytesIO(content)) as image:
            image = image.convert("RGB").resize(INPUT_SIZE, Image.Resampling.LANCZOS)
            values = np.asarray(image, dtype=np.float32)
    except (UnidentifiedImageError, OSError, Image.DecompressionBombError) as error:
        raise HTTPException(status_code=422, detail="La imagen no es una retinografía JPG o PNG válida.") from error

    # El entrenamiento empleó EfficientNetB0 preprocess_input. En TensorFlow/Keras
    # EfficientNet incorpora su propia capa Rescaling, por lo que recibe píxeles 0-255.
    batch = np.expand_dims(values, axis=0)
    if INPUT_DETAILS["dtype"] in (np.uint8, np.int8):
        scale, zero_point = INPUT_DETAILS["quantization"]
        if scale == 0:
            raise RuntimeError("Cuantización de entrada inválida en el modelo TFLite.")
        limits = np.iinfo(INPUT_DETAILS["dtype"])
        batch = np.clip(np.round(batch / scale + zero_point), limits.min, limits.max).astype(INPUT_DETAILS["dtype"])
    else:
        batch = batch.astype(INPUT_DETAILS["dtype"])
    return batch


def infer(batch: np.ndarray) -> np.ndarray:
    with INTERPRETER_LOCK:
        interpreter.set_tensor(INPUT_DETAILS["index"], batch)
        interpreter.invoke()
        output = interpreter.get_tensor(OUTPUT_DETAILS["index"])[0].astype(np.float32)

    if OUTPUT_DETAILS["dtype"] in (np.uint8, np.int8):
        scale, zero_point = OUTPUT_DETAILS["quantization"]
        if scale == 0:
            raise RuntimeError("Cuantización de salida inválida en el modelo TFLite.")
        output = (output - zero_point) * scale
    if output.shape[0] != len(CLASSES):
        raise RuntimeError("La salida del modelo no coincide con las clases configuradas.")
    return np.clip(output, 0.0, 1.0)


@app.get("/health")
def health():
    return {
        "status": "ok",
        "model_version": CLASS_INFO.get("version", "1.0"),
        "classes": CLASSES,
    }


@app.post("/api/analizar")
async def analizar(file: UploadFile = File(...), x_api_key: str | None = Header(default=None)):
    require_api_key(x_api_key)
    if file.content_type not in ALLOWED_CONTENT_TYPES:
        raise HTTPException(status_code=415, detail="Solo se admiten imágenes JPG o PNG.")

    content = await file.read(MAX_FILE_SIZE + 1)
    if not content or len(content) > MAX_FILE_SIZE:
        raise HTTPException(status_code=413, detail="La imagen debe pesar como máximo 10 MB.")

    start = time.perf_counter()
    probabilities_raw = infer(prepare_input(content))
    probabilities = {label: round(float(value) * 100, 2) for label, value in zip(CLASSES, probabilities_raw)}

    disease_labels = [label for label in CLASSES if label != "normal"]
    detected = [label for label in disease_labels if probabilities_raw[CLASSES.index(label)] >= THRESHOLD]
    if detected:
        result = max(detected, key=lambda label: probabilities_raw[CLASSES.index(label)])
        anomaly = True
    else:
        result = "normal"
        anomaly = False

    return {
        "resultado_principal": result,
        "probabilidad_principal": probabilities[result],
        "probabilidades": probabilities,
        "hallazgos": detected,
        "alerta_anomalia": anomaly,
        "es_referencial": True,
        "threshold": THRESHOLD,
        "modelo_version": CLASS_INFO.get("version", "1.0"),
        "tiempo_analisis": round(time.perf_counter() - start, 3),
    }
