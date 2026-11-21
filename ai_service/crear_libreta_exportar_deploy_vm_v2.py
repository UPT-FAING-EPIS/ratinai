"""Genera la libreta que exporta el clasificador clínico y completa el paquete VM."""

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
        """# RetinAI CNN v2: exportar paquete para la VM

Esta libreta **no entrena**. Convierte el clasificador clínico Keras a un TFLite con cuatro salidas en este orden:

`normal, diabetes, glaucoma, catarata`

Después valida dimensiones y valores y completa `RETINAI_CNN_V2/DEPLOY_VM_CNN_V2/`.

Puede ejecutarse con **CPU**; no requiere GPU.
""",
    ),
    cell(
        "code",
        """from pathlib import Path
import json, shutil, sys
import numpy as np
import tensorflow as tf
from tensorflow import keras
from google.colab import drive

drive.mount('/content/drive')
raiz = Path('/content/drive/MyDrive/RETINAI_CNN_V2')
deploy = raiz / 'DEPLOY_VM_CNN_V2'
modelos = deploy / 'modelo_v2'
requeridos = [
    raiz / 'entrenar_multitarea_enmascarado_v2.py',
    raiz / 'clasificador_multitarea_enmascarado_v2_candidato.keras',
    deploy / 'servidor_v2.py',
    deploy / 'requirements.txt',
    modelos / 'filtro_retinografia.tflite',
    modelos / 'filtro_calidad.tflite',
    modelos / 'informacion_modelo.json',
]
faltantes = [str(ruta) for ruta in requeridos if not ruta.is_file()]
if faltantes:
    raise FileNotFoundError('Faltan archivos: ' + ', '.join(faltantes))
sys.path.insert(0, str(raiz))
print('Archivos base verificados.')
""",
    ),
    cell(
        "code",
        """import entrenar_multitarea_enmascarado_v2 as entrenador

ruta_keras = raiz / 'clasificador_multitarea_enmascarado_v2_candidato.keras'
modelo_enfermedades = keras.models.load_model(ruta_keras, compile=False)
if tuple(modelo_enfermedades.output_shape) != (None, 3):
    raise RuntimeError(f'Se esperaban tres salidas de enfermedad: {modelo_enfermedades.output_shape}')

modelo_cuatro = entrenador.build_export_model(modelo_enfermedades)
if tuple(modelo_cuatro.output_shape) != (None, 4):
    raise RuntimeError(f'La exportación no produjo cuatro salidas: {modelo_cuatro.output_shape}')

ruta_tflite = modelos / 'clasificador_4clases.tflite'
convertidor = tf.lite.TFLiteConverter.from_keras_model(modelo_cuatro)
ruta_tflite.write_bytes(convertidor.convert())
print('TFLite clínico generado:', ruta_tflite)
""",
    ),
    cell(
        "code",
        """interprete = tf.lite.Interpreter(model_path=str(ruta_tflite), num_threads=2)
interprete.allocate_tensors()
entrada = interprete.get_input_details()[0]
salida = interprete.get_output_details()[0]
assert tuple(entrada['shape']) == (1, 384, 384, 3), entrada['shape']
assert tuple(salida['shape']) == (1, 4), salida['shape']

muestra = np.zeros((1, 384, 384, 3), dtype=np.float32)
esperado = modelo_cuatro.predict(muestra, verbose=0)
interprete.set_tensor(entrada['index'], muestra.astype(entrada['dtype']))
interprete.invoke()
obtenido = interprete.get_tensor(salida['index'])
error_maximo = float(np.max(np.abs(esperado - obtenido)))
if error_maximo > 1e-4:
    raise RuntimeError(f'La conversión cambió las probabilidades: error={error_maximo}')
if not np.all(np.isfinite(obtenido)) or np.any(obtenido < 0) or np.any(obtenido > 1):
    raise RuntimeError('El TFLite produjo probabilidades inválidas.')

configuracion = json.loads((modelos / 'informacion_modelo.json').read_text(encoding='utf-8'))
assert configuracion['clases_clinicas'] == ['normal', 'diabetes', 'glaucoma', 'catarata']
assert configuracion['modelos']['retinografia']['umbral'] == 0.38
assert configuracion['modelos']['clinico']['tamano_entrada'] == [384, 384]

faltantes_finales = [nombre for nombre in [
    'filtro_retinografia.tflite', 'filtro_calidad.tflite',
    'clasificador_4clases.tflite', 'informacion_modelo.json'
] if not (modelos / nombre).is_file()]
if faltantes_finales:
    raise RuntimeError('Paquete incompleto: ' + ', '.join(faltantes_finales))

zip_base = raiz / 'RetinAI_CNN_V2_DEPLOY_VM'
zip_ruta = Path(shutil.make_archive(str(zip_base), 'zip', root_dir=deploy.parent, base_dir=deploy.name))
print('VALIDACIÓN COMPLETADA')
print('Entrada clínica:', entrada['shape'].tolist())
print('Salida clínica:', salida['shape'].tolist())
print('Orden: normal, diabetes, glaucoma, catarata')
print('Error máximo Keras/TFLite:', error_maximo)
print('Carpeta final:', deploy)
print('ZIP final:', zip_ruta)
""",
    ),
]


def main() -> None:
    destination = BASE / "RetinAI_CNN_V2_Exportar_Deploy_VM.ipynb"
    notebook = {
        "cells": CELLS,
        "metadata": {"kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"}},
        "nbformat": 4,
        "nbformat_minor": 5,
    }
    destination.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(destination)


if __name__ == "__main__":
    main()
