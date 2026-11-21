"""Genera una prueba externa de glaucoma en RIM-ONE DL sin reentrenar."""

import json
from pathlib import Path


base = Path(__file__).parent


def celda(tipo, fuente):
    resultado = {"cell_type": tipo, "metadata": {}, "source": fuente.splitlines(keepends=True)}
    if tipo == "code":
        resultado.update(execution_count=None, outputs=[])
    return resultado


celdas = [
    celda("markdown", """# RetinAI CNN v2: prueba externa de glaucoma

Esta libreta **no entrena**. Evalúa los dos modelos ODIR existentes sobre las 174 imágenes del conjunto de prueba **separado por hospital** de RIM-ONE DL (118 normales y 56 con glaucoma). Las imágenes proceden de una fuente independiente. Esta prueba mide glaucoma frente a normal; no mide retinopatía ni catarata. El conjunto RIM-ONE DL tiene permiso de uso para investigación y educación, pero prohíbe su redistribución. Sus resultados publicados no son comparables directamente con nuestros modelos entrenados en ODIR.

Descarga original y condiciones: https://github.com/miag-ull/rim-one-dl

**Instrucción:** ejecuta todas las celdas; al final se descargará `informe_rim_one_glaucoma.json`. Envíame ese archivo. La descarga de imágenes es de aproximadamente 223 MB. CPU funciona, GPU T4 acelera la predicción.
"""),
    celda("code", "%pip -q install gdown scikit-learn\n"),
    celda("code", """import hashlib
import json
from pathlib import Path
import zipfile
import gdown
import numpy as np
import pandas as pd
import tensorflow as tf
from tensorflow import keras
from sklearn.metrics import confusion_matrix, roc_auc_score

archivo = Path('/content/rim_one_dl_images.zip')
if not archivo.is_file():
    gdown.download(id='1teYi_smpLiNZNJcTWdxXgKKLW2fkUQr4', output=str(archivo), quiet=False)
if not archivo.is_file():
    raise RuntimeError('No se pudo descargar RIM-ONE DL desde el enlace oficial.')
sha = hashlib.file_digest(archivo.open('rb'), 'sha256').hexdigest()
esperado = '85aed5f95c794f52d11b6ed953032108a66576ffcff4b587365df467491b603d'
if sha != esperado:
    raise RuntimeError(f'El ZIP cambió: SHA256 {sha}. Detén la evaluación y avísame.')

raiz = Path('/content/rim_one_test')
raiz.mkdir(exist_ok=True)
filas = []
with zipfile.ZipFile(archivo) as z:
    nombres = [n for n in z.namelist() if '/partitioned_by_hospital/test_set/' in n and n.lower().endswith('.png')]
    for nombre in sorted(nombres):
        clase = nombre.split('/')[-2]
        if clase not in ('normal', 'glaucoma'):
            raise RuntimeError(f'Clase desconocida: {nombre}')
        destino = raiz / clase / Path(nombre).name
        destino.parent.mkdir(exist_ok=True)
        destino.write_bytes(z.read(nombre))
        filas.append({'archivo': nombre, 'ruta': str(destino), 'clase': clase, 'glaucoma': int(clase == 'glaucoma')})
tabla = pd.DataFrame(filas)
if len(tabla) != 174 or tabla['clase'].value_counts().to_dict() != {'normal': 118, 'glaucoma': 56}:
    raise RuntimeError('La partición por hospital no coincide con la versión auditada.')
print('Imágenes:', len(tabla), tabla['clase'].value_counts().to_dict())
print('GPU:', tf.config.list_physical_devices('GPU'))
"""),
    celda("code", """from google.colab import drive
drive.mount('/content/drive')
modelos = {
    'odir_224': Path('/content/drive/MyDrive/RETINAI_CNN_V2/clasificador_odir_4clases_exploratorio.keras'),
    'odir_384': Path('/content/drive/MyDrive/RETINAI_CNN_V2/clasificador_odir_384_exploratorio.keras'),
}
faltan = [str(p) for p in modelos.values() if not p.is_file()]
if faltan:
    raise RuntimeError(f'No encuentro modelos en tu Drive: {faltan}')

def dataset(tamano):
    def abrir(ruta):
        imagen = tf.io.decode_png(tf.io.read_file(ruta), channels=3)
        imagen = tf.image.convert_image_dtype(imagen, tf.float32)
        imagen = tf.image.resize_with_pad(imagen, tamano, tamano)
        return imagen * 255.0
    ds = tf.data.Dataset.from_tensor_slices(tabla['ruta'].to_numpy())
    return ds.map(abrir, num_parallel_calls=tf.data.AUTOTUNE).batch(12).prefetch(tf.data.AUTOTUNE)

for nombre, ruta in modelos.items():
    tamano = 224 if nombre == 'odir_224' else 384
    prob = keras.models.load_model(ruta).predict(dataset(tamano), verbose=1)
    if prob.shape != (len(tabla), 4):
        raise RuntimeError(f'Salida inesperada de {nombre}: {prob.shape}')
    tabla[f'p_glaucoma_{nombre}'] = prob[:, 2]
    tabla[f'pred_clase_{nombre}'] = prob.argmax(axis=1)
"""),
    celda("code", """resultados = {}
y = tabla['glaucoma'].to_numpy()
for nombre in modelos:
    puntaje = tabla[f'p_glaucoma_{nombre}'].to_numpy()
    pred_clase = tabla[f'pred_clase_{nombre}'].to_numpy()
    pred_glaucoma = (pred_clase == 2).astype(int)
    tn, fp, fn, tp = confusion_matrix(y, pred_glaucoma, labels=[0, 1]).ravel()
    erroneas = {
        ['normal', 'retinopatia_diabetica', 'glaucoma', 'catarata'][i]: int((pred_clase[y == 1] == i).sum())
        for i in range(4)
    }
    resultados[nombre] = {
        'auc_glaucoma_vs_normal': float(roc_auc_score(y, puntaje)),
        'matriz_argmax_normal_glaucoma': [[int(tn), int(fp)], [int(fn), int(tp)]],
        'sensibilidad_glaucoma_argmax': float(tp / (tp + fn)),
        'especificidad_normal_argmax': float(tn / (tn + fp)),
        'predicciones_sobre_glaucomas_reales': erroneas,
    }
    print(nombre, resultados[nombre])

informe = {
    'tipo': 'prueba_externa_exploratoria_rim_one_glaucoma',
    'particion': 'partitioned_by_hospital/test_set',
    'imagenes': len(tabla),
    'zip_sha256': sha,
    'nota': 'Modelos entrenados en ODIR; no comparar métricas directamente con el benchmark propio de RIM-ONE DL.',
    'resultados': resultados,
}
salida = Path('/content/drive/MyDrive/RETINAI_CNN_V2/informe_rim_one_glaucoma.json')
salida.write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding='utf-8')
print('Informe:', salida)
from google.colab import files
files.download(str(salida))
"""),
]

destino = base / 'RetinAI_CNN_V2_Prueba_RIM_ONE_Glaucoma.ipynb'
destino.write_text(json.dumps({
    'cells': celdas,
    'metadata': {'kernelspec': {'display_name': 'Python 3', 'language': 'python', 'name': 'python3'}},
    'nbformat': 4,
    'nbformat_minor': 5,
}, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
print(destino)
