"""Genera la libreta integral de contenido, calidad y clasificación RetinAI v2."""

import json
from pathlib import Path


BASE = Path(__file__).parent


def cell(kind: str, source: str) -> dict:
    result = {"cell_type": kind, "metadata": {}, "source": source.splitlines(keepends=True)}
    if kind == "code":
        result.update(execution_count=None, outputs=[])
    return result


CELLS = [
    cell(
        "markdown",
        """# RetinAI CNN v2: pipeline integral

Esta libreta completa los tres pasos del sistema académico:

1. **Contenido:** reentrena el filtro retinografía/no retinografía con retinas de ODIR, Sylhet, Four-Class, IDRiD y RIM-ONE; usa animales, objetos y regiones perioculares como negativos.
2. **Calidad:** reutiliza `filtro_calidad_mshf_candidato.tflite`.
3. **Clasificación clínica:** reutiliza `clasificador_multitarea_enmascarado_v2_candidato.keras` y muestra normal, retinopatía diabética, glaucoma y catarata.

La prueba manual detiene el flujo si la entrada no es una retinografía o si no es evaluable. Es un prototipo académico; no sustituye el criterio médico.

**Selecciona GPU T4 y ejecuta todo.**
""",
    ),
    cell("code", "%pip -q install kagglehub gdown tensorflow-datasets scikit-learn pandas pillow importlib-resources\n"),
    cell(
        "code",
        """from pathlib import Path
import json, sys
import tensorflow as tf
from google.colab import drive

print('GPU:', tf.config.list_physical_devices('GPU'))
if not tf.config.list_physical_devices('GPU'):
    raise RuntimeError('Selecciona GPU T4 en Entorno de ejecución > Cambiar tipo de entorno.')

drive.mount('/content/drive')
destino = Path('/content/drive/MyDrive/RETINAI_CNN_V2')
requeridos = [
    'entrenar_filtro_contenido_multifuente_pipeline_v2.py',
    'entrenar_clasificador_multifuente_real_v2.py',
    'entrenar_multitarea_enmascarado_v2.py',
    'retinai_fuentes_v2.csv',
    'odir_manifest_v2_split.csv',
    'sylhet_manifest_v2.csv',
    'filtro_calidad_mshf_candidato.tflite',
    'clasificador_multitarea_enmascarado_v2_candidato.keras',
]
faltantes = [nombre for nombre in requeridos if not (destino / nombre).is_file()]
if faltantes:
    raise FileNotFoundError('Faltan archivos en RETINAI_CNN_V2: ' + ', '.join(faltantes))
sys.path.insert(0, str(destino))
print('Archivos requeridos verificados.')
""",
    ),
    cell(
        "code",
        """import runpy
script = destino / 'entrenar_filtro_contenido_multifuente_pipeline_v2.py'
sys.argv = [str(script), str(destino)]
_resultado_ejecucion = runpy.run_path(str(script), run_name='__main__')
""",
    ),
    cell(
        "code",
        """import pandas as pd
from IPython.display import display
from google.colab import files

informe_ruta = destino / 'informe_filtro_contenido_multifuente_pipeline_v2.json'
informe = json.loads(informe_ruta.read_text(encoding='utf-8'))
print('DECISIÓN:', informe['decision'])
print('Umbral contenido:', round(informe['threshold'], 4))
print('Sensibilidad retinografías:', round(informe['test_retina_sensitivity'], 4))
print('Especificidad no retinografías:', round(informe['test_negative_specificity'], 4))
display(pd.DataFrame([
    {'fuente': fuente, **metricas}
    for fuente, metricas in informe['test_by_source'].items()
]).fillna(''))
display(pd.DataFrame([
    {'criterio': criterio, 'cumple': cumple}
    for criterio, cumple in informe['criteria'].items()
]))
files.download(str(informe_ruta))
""",
    ),
    cell(
        "markdown",
        """## Prueba manual completa

Sube una o varias imágenes. Para cada una se ejecutará:

`contenido → calidad → clasificación clínica`

Una fotografía común se detendrá en contenido. Una retinografía no evaluable se detendrá en calidad. Solo una retinografía evaluable mostrará los cuatro porcentajes clínicos.
""",
    ),
    cell(
        "code",
        """from google.colab import files
from PIL import Image, ImageOps
import io, numpy as np, matplotlib.pyplot as plt
from tensorflow import keras

import entrenar_multitarea_enmascarado_v2  # registra las clases personalizadas del modelo clínico

contenido_ruta = destino / 'filtro_retinografia_multifuente_v2_candidato.tflite'
calidad_ruta = destino / 'filtro_calidad_mshf_candidato.tflite'
clinico_ruta = destino / 'clasificador_multitarea_enmascarado_v2_candidato.keras'

def cargar_tflite(ruta):
    interprete = tf.lite.Interpreter(model_path=str(ruta), num_threads=2)
    interprete.allocate_tensors()
    return interprete

def ejecutar_tflite(interprete, imagen):
    entrada = interprete.get_input_details()[0]
    salida = interprete.get_output_details()[0]
    tamano = int(entrada['shape'][1])
    preparada = ImageOps.pad(imagen.convert('RGB'), (tamano, tamano), method=Image.Resampling.BILINEAR, color=(0, 0, 0))
    lote = np.expand_dims(np.asarray(preparada, dtype=np.float32), 0)
    interprete.set_tensor(entrada['index'], lote.astype(entrada['dtype']))
    interprete.invoke()
    return float(interprete.get_tensor(salida['index']).reshape(-1)[0])

contenido = cargar_tflite(contenido_ruta)
calidad = cargar_tflite(calidad_ruta)
clinico = keras.models.load_model(clinico_ruta, compile=False)
umbral_contenido = float(informe['threshold'])
umbral_calidad = 0.50

subidas = files.upload()
for nombre, bytes_imagen in subidas.items():
    imagen = Image.open(io.BytesIO(bytes_imagen)).convert('RGB')
    p_contenido = ejecutar_tflite(contenido, imagen)
    print(f'\\n{nombre}')
    print(f'1. Contenido: {p_contenido * 100:.1f}% retinografía (umbral {umbral_contenido * 100:.1f}%)')
    if p_contenido < umbral_contenido:
        print('RESULTADO: RECHAZADA — la imagen no parece una retinografía.')
        plt.figure(figsize=(4, 4)); plt.imshow(imagen); plt.axis('off'); plt.title('No retinografía'); plt.show()
        continue

    p_calidad = ejecutar_tflite(calidad, imagen)
    print(f'2. Calidad: {p_calidad * 100:.1f}% evaluable (umbral {umbral_calidad * 100:.1f}%)')
    if p_calidad < umbral_calidad:
        print('RESULTADO: RECHAZADA — retinografía no evaluable.')
        plt.figure(figsize=(4, 4)); plt.imshow(imagen); plt.axis('off'); plt.title('Retinografía no evaluable'); plt.show()
        continue

    preparada = ImageOps.pad(imagen, (384, 384), method=Image.Resampling.BILINEAR, color=(0, 0, 0))
    lote = np.expand_dims(np.asarray(preparada, dtype=np.float32), 0)
    enfermedades = clinico.predict(lote, verbose=0)[0]
    porcentajes = np.concatenate(([1.0 - enfermedades.max()], enfermedades)) * 100.0
    etiquetas = ['Normal', 'Retinopatía diabética', 'Glaucoma', 'Catarata']
    print('3. Clasificación clínica completada.')
    fig, ejes = plt.subplots(1, 2, figsize=(12, 4))
    ejes[0].imshow(imagen); ejes[0].axis('off'); ejes[0].set_title(nombre)
    colores = ['#163a4a', '#dc2626', '#ea580c', '#2563eb']
    ejes[1].barh(etiquetas, porcentajes, color=colores)
    ejes[1].set_xlim(0, 100); ejes[1].set_xlabel('Porcentaje')
    for indice, valor in enumerate(porcentajes):
        ejes[1].text(min(valor + 1, 94), indice, f'{valor:.1f}%', va='center')
    plt.tight_layout(); plt.show()
""",
    ),
]


def main() -> None:
    destination = BASE / "RetinAI_CNN_V2_Pipeline_Integral.ipynb"
    notebook = {
        "cells": CELLS,
        "metadata": {
            "kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"},
            "accelerator": "GPU",
        },
        "nbformat": 4,
        "nbformat_minor": 5,
    }
    destination.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(destination)


if __name__ == "__main__":
    main()
