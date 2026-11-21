"""Crea entrenamiento del filtro de calidad con MSHF depurado."""

import base64
import csv
import gzip
import io
import json
from pathlib import Path


base = Path(__file__).parent
filas = list(csv.DictReader((base / 'data' / 'mshf_manifest_calidad.csv').open(encoding='utf-8')))
campos = ['archivo', 'ruta_zip', 'fuente', 'particion_publicada', 'calidad_evaluable', 'sha256', 'formato_real']
texto = io.StringIO()
escritor = csv.DictWriter(texto, fieldnames=campos)
escritor.writeheader()
escritor.writerows({c: f[c] for c in campos} for f in filas)
paquete = base64.b64encode(gzip.compress(texto.getvalue().encode(), 9)).decode()


def celda(tipo, fuente):
    resultado = {'cell_type': tipo, 'metadata': {}, 'source': fuente.splitlines(keepends=True)}
    if tipo == 'code':
        resultado.update(execution_count=None, outputs=[])
    return resultado


celdas = [
    celda('markdown', '''# RetinAI CNN v2: entrenar filtro de calidad

Esta libreta entrena **solo el filtro de calidad**: retinografía evaluable frente a no evaluable. No intenta diagnosticar enfermedades. Usa el conjunto MSHF de 1.302 retinografías con tres votos por imagen; la etiqueta es la mayoría de votos. Descarta dos imágenes sin partición publicada, seis copias exactas que cruzan entrenamiento y prueba, y copias internas. Quedan 1.038 imágenes para entrenamiento/validación y 252 para prueba. Las imágenes vienen de cámaras convencionales, portátiles y de campo amplio. No hay identificadores de paciente, así que la evaluación sigue siendo exploratoria.

Fuente y condiciones: https://doi.org/10.6084/m9.figshare.21507564

**Ejecuta todo con GPU T4.** Se descargará aproximadamente 1,14 GB. Al final recibirás `informe_filtro_calidad_mshf.json`; envíame solo ese archivo. El modelo queda guardado en `Mi unidad/RETINAI_CNN_V2/` como candidato separado. No está aprobado para el servicio médico.
'''),
    celda('code', '%pip -q install scikit-learn pillow\n'),
    celda('code', f'''import base64, gzip, hashlib, io, json, urllib.request, zipfile
from pathlib import Path
import numpy as np
import pandas as pd
import tensorflow as tf
from tensorflow import keras
from PIL import Image
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, confusion_matrix, f1_score, roc_auc_score

SEMILLA = 325
tf.keras.utils.set_random_seed(SEMILLA)
DATOS_B64 = '{paquete}'
tabla = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(DATOS_B64))))
assert len(tabla) == 1302 and tabla['archivo'].is_unique
assert set(tabla['calidad_evaluable']) == {{0, 1}}
print('GPU:', tf.config.list_physical_devices('GPU'))
if not tf.config.list_physical_devices('GPU'):
    raise RuntimeError('Selecciona GPU T4 en Entorno de ejecución > Cambiar tipo de entorno.')
originales = tabla[tabla['particion_publicada'].eq('train')].drop_duplicates('sha256').copy()
prueba = tabla[tabla['particion_publicada'].eq('test')].copy()
prueba = prueba[~prueba['sha256'].isin(originales['sha256'])].drop_duplicates('sha256').copy()
assert len(originales) == 1038 and len(prueba) == 252
assert not set(originales['sha256']) & set(prueba['sha256'])
entrenamiento, validacion = train_test_split(
    originales, test_size=0.20, random_state=SEMILLA,
    stratify=originales['fuente'].astype(str) + '_' + originales['calidad_evaluable'].astype(str)
)
assert not set(entrenamiento['sha256']) & set(validacion['sha256'])
print('Entrenamiento', len(entrenamiento), 'validación', len(validacion), 'prueba', len(prueba))
display(pd.crosstab(prueba['fuente'], prueba['calidad_evaluable']))
'''),
    celda('code', '''zip_ruta = Path('/content/mshf_dataset_2.zip')
if not zip_ruta.is_file():
    print('Descargando MSHF (~1,14 GB)...', flush=True)
    urllib.request.urlretrieve('https://ndownloader.figshare.com/files/39485878', zip_ruta)
with zip_ruta.open('rb') as f:
    sha = hashlib.file_digest(f, 'sha256').hexdigest()
if sha != '115def6513bc1516d77ceb4ac2e90a9fcf582e825604c08be9524f5fd6c4a219':
    raise RuntimeError(f'El ZIP de MSHF cambió: {sha}. Detén la ejecución y avísame.')
carpeta = Path('/content/mshf_imagenes')
carpeta.mkdir(exist_ok=True)
elegidas = pd.concat([entrenamiento, validacion, prueba]).drop_duplicates('archivo')
with zipfile.ZipFile(zip_ruta) as z:
    for fila in elegidas.itertuples():
        contenido = z.read(fila.ruta_zip)
        if fila.formato_real == 'TIFF':
            with Image.open(io.BytesIO(contenido)) as imagen:
                imagen.convert('RGB').save(carpeta / (Path(fila.archivo).stem + '.png'), format='PNG')
        elif fila.formato_real == 'JPEG':
            (carpeta / fila.archivo).write_bytes(contenido)
        else:
            raise RuntimeError(f'Formato inesperado: {fila.archivo} ({fila.formato_real})')
for grupo in (entrenamiento, validacion, prueba):
    grupo['ruta'] = grupo.apply(lambda r: str(carpeta / (Path(r['archivo']).stem + '.png'))
                                if r['formato_real'] == 'TIFF' else str(carpeta / r['archivo']), axis=1)
print('Imágenes extraídas:', len(elegidas))
'''),
    celda('code', '''TAMANO, LOTE = 224, 16
AUTO = tf.data.AUTOTUNE
def abrir(ruta, etiqueta, entrenar):
    imagen = tf.io.decode_image(tf.io.read_file(ruta), channels=3, expand_animations=False)
    imagen.set_shape((None, None, 3))
    imagen = tf.image.convert_image_dtype(imagen, tf.float32)
    imagen = tf.image.resize_with_pad(imagen, TAMANO, TAMANO)
    if entrenar:
        imagen = tf.image.random_flip_left_right(imagen)
    return imagen * 255., tf.cast(etiqueta, tf.float32)

def dataset(filas, entrenar=False):
    ds = tf.data.Dataset.from_tensor_slices((filas['ruta'].to_numpy(), filas['calidad_evaluable'].to_numpy(dtype='int32')))
    if entrenar:
        ds = ds.shuffle(len(filas), seed=SEMILLA, reshuffle_each_iteration=True)
    return ds.map(lambda r, y: abrir(r, y, entrenar), num_parallel_calls=AUTO).batch(LOTE).prefetch(AUTO)

train_ds = dataset(entrenamiento, True)
val_ds = dataset(validacion)
test_ds = dataset(prueba)
y_val = validacion['calidad_evaluable'].to_numpy(dtype='int32')
y_test = prueba['calidad_evaluable'].to_numpy(dtype='int32')
'''),
    celda('code', '''from google.colab import drive
drive.mount('/content/drive')
destino = Path('/content/drive/MyDrive/RETINAI_CNN_V2')
destino.mkdir(parents=True, exist_ok=True)
modelo_ruta = destino / 'filtro_calidad_mshf_candidato.keras'
base_cnn = keras.applications.EfficientNetB0(include_top=False, weights='imagenet', input_shape=(TAMANO, TAMANO, 3), pooling='avg')
base_cnn.trainable = False
entrada = keras.Input(shape=(TAMANO, TAMANO, 3))
x = base_cnn(entrada, training=False)
x = keras.layers.Dropout(0.25)(x)
salida = keras.layers.Dense(1, activation='sigmoid')(x)
modelo = keras.Model(entrada, salida)
modelo.compile(optimizer=keras.optimizers.Adam(1e-3), loss='binary_crossentropy', metrics=['accuracy'])

class GuardarMejor(keras.callbacks.Callback):
    def __init__(self):
        super().__init__()
        self.mejor, self.espera, self.historial = -1., 0, []
    def on_epoch_end(self, epoch, logs=None):
        p = self.model.predict(val_ds, verbose=0).reshape(-1)
        f1 = float(f1_score(1 - y_val, p < 0.5))
        self.historial.append({'epoca': epoch + 1, 'f1_rechazo_validacion': f1})
        print('F1 rechazo validación:', round(f1, 4))
        if f1 > self.mejor + 0.002:
            self.mejor, self.espera = f1, 0
            self.model.save(modelo_ruta)
        else:
            self.espera += 1
            if self.espera >= 3:
                self.model.stop_training = True

guardar = GuardarMejor()
cuenta = entrenamiento['calidad_evaluable'].value_counts()
pesos = {i: float(len(entrenamiento) / (2 * cuenta[i])) for i in (0, 1)}
historia = modelo.fit(train_ds, validation_data=val_ds, epochs=10, class_weight=pesos, callbacks=[guardar])
pd.DataFrame(historia.history).to_csv(destino / 'historial_filtro_calidad_mshf.csv', index=False)
'''),
    celda('code', '''mejor = keras.models.load_model(modelo_ruta)
p = mejor.predict(test_ds, verbose=1).reshape(-1)
pred = (p >= 0.5).astype('int32')
matriz = confusion_matrix(y_test, pred, labels=[0, 1])
resultado = {
    'tipo': 'prueba_exploratoria_filtro_calidad_mshf',
    'clases': {'0': 'no_evaluable', '1': 'evaluable'},
    'imagenes_entrenamiento': len(entrenamiento),
    'imagenes_validacion': len(validacion),
    'imagenes_prueba_sin_duplicados': len(prueba),
    'zip_sha256': sha,
    'f1_evaluable': float(f1_score(y_test, pred)),
    'f1_no_evaluable': float(f1_score(1 - y_test, 1 - pred)),
    'no_evaluables_aceptadas': int(((y_test == 0) & (pred == 1)).sum()),
    'auc_evaluable': float(roc_auc_score(y_test, p)),
    'matriz_confusion': matriz.tolist(),
    'reporte': classification_report(y_test, pred, labels=[0,1], target_names=['no_evaluable','evaluable'], output_dict=True, zero_division=0),
    'por_fuente': {},
    'historial_validacion': guardar.historial,
    'nota': 'Etiquetas por mayoría de tres evaluadores; no hay IDs de paciente. No es aprobación clínica.'
}
convertidor = tf.lite.TFLiteConverter.from_keras_model(mejor)
ruta_tflite = destino / 'filtro_calidad_mshf_candidato.tflite'
ruta_tflite.write_bytes(convertidor.convert())
interprete = tf.lite.Interpreter(model_path=str(ruta_tflite))
interprete.allocate_tensors()
assert tuple(interprete.get_input_details()[0]['shape']) == (1, 224, 224, 3)
assert tuple(interprete.get_output_details()[0]['shape']) == (1, 1)
resultado['modelo_tflite'] = str(ruta_tflite)
for fuente, grupo in prueba.groupby('fuente'):
    indices = prueba.index.get_indexer(grupo.index)
    resultado['por_fuente'][fuente] = {
        'imagenes': len(grupo),
        'matriz_confusion': confusion_matrix(y_test[indices], pred[indices], labels=[0,1]).tolist()
    }
salida = destino / 'informe_filtro_calidad_mshf.json'
salida.write_text(json.dumps(resultado, ensure_ascii=False, indent=2), encoding='utf-8')
print('Matriz:', matriz)
print('F1 evaluable:', resultado['f1_evaluable'], 'AUC:', resultado['auc_evaluable'])
print('Informe:', salida)
from google.colab import files
files.download(str(salida))
'''),
]

destino = base / 'RetinAI_CNN_V2_Entrenar_Filtro_Calidad.ipynb'
destino.write_text(json.dumps({'cells': celdas, 'metadata': {'kernelspec': {'display_name': 'Python 3', 'language': 'python', 'name': 'python3'}}, 'nbformat': 4, 'nbformat_minor': 5}, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
print(destino)
