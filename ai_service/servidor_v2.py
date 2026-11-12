"""Servicio de inferencia para tres modelos RetinAI v2 independientes."""

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
from PIL import Image, ImageOps, UnidentifiedImageError

BASE = Path(__file__).resolve().parent
DIR_MODELOS = Path(os.getenv("RETINAI_DIRECTORIO_MODELOS", BASE / "modelo_v2"))
API_KEY = os.getenv("RETINAI_CLAVE_API", "")
CLASES = ["normal", "diabetes", "glaucoma", "catarata"]
MAX_BYTES = 10 * 1024 * 1024


class Modelo:
    def __init__(self, nombre: str, config: dict):
        self.nombre = nombre
        self.tamano = tuple(config["tamano_entrada"])
        self.umbral = config.get("umbral")
        ruta = (DIR_MODELOS / config["archivo"]).resolve()
        if ruta.parent != DIR_MODELOS.resolve() or not ruta.is_file():
            raise RuntimeError(f"Falta el modelo {nombre}: {ruta}")
        self.interprete = tf.lite.Interpreter(model_path=str(ruta), num_threads=1)
        self.interprete.allocate_tensors()
        entradas = self.interprete.get_input_details()
        salidas = self.interprete.get_output_details()
        if len(entradas) != 1 or len(salidas) != 1:
            raise RuntimeError(f"{nombre} debe tener una entrada y una salida")
        self.entrada, self.salida = entradas[0], salidas[0]
        esperado = 4 if nombre == "clinico" else 1
        if tuple(self.entrada["shape"]) != (1, *self.tamano, 3):
            raise RuntimeError(f"Tamaño de entrada incorrecto: {nombre}")
        if tuple(self.salida["shape"]) != (1, esperado):
            raise RuntimeError(f"Número de salidas incorrecto: {nombre}")
        self.bloqueo = threading.Lock()

    @staticmethod
    def convertir(valores, detalle):
        tipo = detalle["dtype"]
        if tipo in (np.uint8, np.int8):
            escala, cero = detalle["quantization"]
            if escala <= 0:
                raise RuntimeError("Cuantización inválida")
            limites = np.iinfo(tipo)
            valores = np.clip(np.round(valores / escala + cero), limites.min, limites.max)
        return valores.astype(tipo)

    def predecir(self, imagen: Image.Image) -> np.ndarray:
        alto, ancho = self.tamano
        preparada = ImageOps.pad(imagen, (ancho, alto), method=Image.Resampling.BILINEAR, color=(0, 0, 0))
        lote = self.convertir(np.expand_dims(np.asarray(preparada, dtype=np.float32), 0), self.entrada)
        with self.bloqueo:
            self.interprete.set_tensor(self.entrada["index"], lote)
            self.interprete.invoke()
            resultado = self.interprete.get_tensor(self.salida["index"])[0].astype(np.float32)
        if self.salida["dtype"] in (np.uint8, np.int8):
            escala, cero = self.salida["quantization"]
            if escala <= 0:
                raise RuntimeError("Cuantización de salida inválida")
            resultado = (resultado - cero) * escala
        if not np.all(np.isfinite(resultado)) or np.any(resultado < 0) or np.any(resultado > 1):
            raise RuntimeError(f"Probabilidades inválidas: {self.nombre}")
        return resultado


def iniciar():
    if not API_KEY:
        raise RuntimeError("Falta RETINAI_CLAVE_API")
    ruta = DIR_MODELOS / "informacion_modelo.json"
    if not ruta.is_file():
        raise RuntimeError(f"Falta {ruta}")
    info = json.loads(ruta.read_text(encoding="utf-8"))
    uso_academico = os.getenv("RETINAI_USO_ACADEMICO", "").lower() in {"1", "true", "si", "yes"}
    if info.get("aprobado_para_uso") is not True:
        if not (uso_academico and info.get("uso_academico") is True):
            raise RuntimeError("Los modelos no están aprobados para uso médico; falta habilitar el modo académico")
    if info.get("clases_clinicas") != CLASES or not str(info.get("version", "")).strip():
        raise RuntimeError("Contrato de clases o versión inválido")
    configuraciones = info.get("modelos", {})
    obligatorios = {"retinografia", "calidad", "clinico"}
    permitidos = obligatorios | {"glaucoma_especialista"}
    if not obligatorios.issubset(configuraciones) or not set(configuraciones).issubset(permitidos):
        raise RuntimeError("Se requieren los tres modelos y solo se admite el especialista de glaucoma opcional")
    if not 0 < float(info.get("umbral_clinico", 0)) < 1:
        raise RuntimeError("Umbral clínico inválido")
    for nombre, config in configuraciones.items():
        if not isinstance(config.get("tamano_entrada"), list) or len(config["tamano_entrada"]) != 2:
            raise RuntimeError(f"Tamaño inválido: {nombre}")
        if nombre != "clinico" and not 0 < float(config.get("umbral", 0)) < 1:
            raise RuntimeError(f"Umbral inválido: {nombre}")
    return info, {nombre: Modelo(nombre, configuraciones[nombre]) for nombre in configuraciones}


INFO, MODELOS = iniciar()
app = FastAPI(title="RetinAI v2", version=INFO["version"], docs_url=None, redoc_url=None)
aplicacion = app


def abrir_imagen(datos: bytes) -> Image.Image:
    try:
        with Image.open(io.BytesIO(datos)) as archivo:
            if archivo.format not in ("JPEG", "PNG"):
                raise HTTPException(status_code=415, detail="Solo JPG o PNG")
            archivo.verify()
        with Image.open(io.BytesIO(datos)) as archivo:
            return ImageOps.exif_transpose(archivo).convert("RGB")
    except (UnidentifiedImageError, OSError, Image.DecompressionBombError) as error:
        raise HTTPException(status_code=422, detail="Imagen inválida") from error


@app.get("/health")
def health():
    return {"estado": "correcto", "modelo_version": INFO["version"],
            "clases_clinicas": CLASES, "valida_retinografia": True, "valida_calidad": True,
            "uso_academico": INFO.get("aprobado_para_uso") is not True,
            "glaucoma_especialista": "glaucoma_especialista" in MODELOS}


async def recibir_imagen(file: UploadFile, x_api_key: str | None) -> Image.Image:
    """Autentica y decodifica una imagen sin ejecutar todavía ningún modelo."""
    if not x_api_key or not hmac.compare_digest(x_api_key, API_KEY):
        raise HTTPException(status_code=401, detail="No autorizado")
    if file.content_type not in ("image/jpeg", "image/png"):
        raise HTTPException(status_code=415, detail="Solo JPG o PNG")
    datos = await file.read(MAX_BYTES + 1)
    if not datos or len(datos) > MAX_BYTES:
        raise HTTPException(status_code=413, detail="Máximo 10 MB")
    return abrir_imagen(datos)


def ejecutar_validacion(imagen: Image.Image) -> tuple[dict, bool]:
    """Ejecuta únicamente los filtros de contenido y calidad."""
    p_retina = float(MODELOS["retinografia"].predecir(imagen)[0])
    es_retina = p_retina >= MODELOS["retinografia"].umbral
    p_calidad = float(MODELOS["calidad"].predecir(imagen)[0]) if es_retina else 0.0
    es_evaluable = es_retina and p_calidad >= MODELOS["calidad"].umbral
    motivo = None if es_evaluable else (
        "La imagen no fue identificada como una retinografía." if not es_retina
        else "La retinografía no alcanza la calidad mínima para una interpretación clínica."
    )
    return {
        "es_retinografia": es_retina,
        "probabilidad_retinografia": round(p_retina * 100, 2),
        "es_evaluable": es_evaluable,
        "probabilidad_calidad": round(p_calidad * 100, 2),
        "motivo_rechazo": motivo,
    }, es_evaluable


def respuesta_base(validacion: dict, tiempo_analisis: float) -> dict:
    return {
        "modelo_version": INFO["version"],
        "uso_academico": INFO.get("aprobado_para_uso") is not True,
        "advertencia_modelo": (
            "Prototipo académico con modelos reales; no usar para decisiones clínicas."
            if INFO.get("aprobado_para_uso") is not True else None
        ),
        "validacion": validacion,
        "tiempo_analisis": round(tiempo_analisis, 3),
    }


@app.post("/api/validar")
async def validar(file: UploadFile = File(...), x_api_key: str | None = Header(default=None)):
    """Paso 1: comprueba contenido retinal y calidad, sin clasificar patologías."""
    inicio = time.perf_counter()
    imagen = await recibir_imagen(file, x_api_key)
    validacion, _ = ejecutar_validacion(imagen)
    return respuesta_base(validacion, time.perf_counter() - inicio)


@app.post("/api/analizar")
async def analizar(file: UploadFile = File(...), x_api_key: str | None = Header(default=None)):
    """Paso 2: revalida la imagen y, si procede, ejecuta el clasificador clínico."""
    inicio = time.perf_counter()
    imagen = await recibir_imagen(file, x_api_key)
    validacion, es_evaluable = ejecutar_validacion(imagen)
    respuesta = respuesta_base(validacion, time.perf_counter() - inicio)
    if not es_evaluable:
        return respuesta
    valores = MODELOS["clinico"].predecir(imagen)
    principal_base = max((0, 1, 3), key=lambda indice: float(valores[indice]))
    if "glaucoma_especialista" in MODELOS:
        especialista = MODELOS["glaucoma_especialista"]
        probabilidad_glaucoma = float(especialista.predecir(imagen)[0])
        valores[2] = probabilidad_glaucoma
        principal_indice = 2 if probabilidad_glaucoma >= especialista.umbral else principal_base
    else:
        principal_indice = int(np.argmax(valores))
    probabilidades = {clase: round(float(p) * 100, 2) for clase, p in zip(CLASES, valores)}
    principal = CLASES[principal_indice]
    hallazgos = [] if principal == "normal" else [principal]
    respuesta.update({
        "resultado_principal": principal,
        "probabilidad_principal": probabilidades[principal],
        "probabilidades": probabilidades,
        "hallazgos": hallazgos,
        "alerta_anomalia": bool(hallazgos),
        "es_referencial": True,
        "umbral_clinico": float(INFO["umbral_clinico"]),
        "tiempo_analisis": round(time.perf_counter() - inicio, 3),
    })
    return respuesta
