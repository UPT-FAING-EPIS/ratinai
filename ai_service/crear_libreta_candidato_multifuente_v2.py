"""Genera un entrenamiento nuevo de cuatro clases con ODIR y Sylhet auditados."""

import base64
import csv
import gzip
import io
import json
from pathlib import Path


base = Path(__file__).parent
odir = [r for r in csv.DictReader((base / 'odir_manifest_v2_split.csv').open(encoding='utf-8-sig', newline='')) if r['estado'] == 'candidato_clase_unica']
sylhet_bruto = [r for r in csv.DictReader((base / 'sylhet_manifest_v2.csv').open(encoding='utf-8-sig', newline='')) if r['estado'] == 'candidato_adicional_no_validado']
sylhet = list({r['sha256']: r for r in sylhet_bruto}.values())


def paquete(filas, campos):
    salida = io.StringIO()
    escritor = csv.DictWriter(salida, fieldnames=campos)
    escritor.writeheader()
    escritor.writerows({c: r[c] for c in campos} for r in filas)
    return base64.b64encode(gzip.compress(salida.getvalue().encode(), 9)).decode()


odir_b64 = paquete(odir, ['archivo', 'id_paciente', 'etiqueta_provisional', 'particion'])
sylhet_b64 = paquete(sylhet, ['relativo', 'etiqueta_carpeta', 'sha256'])


def celda(tipo, fuente):
    c = {'cell_type': tipo, 'metadata': {}, 'source': fuente.splitlines(keepends=True)}
    if tipo == 'code':
        c.update(execution_count=None, outputs=[])
    return c


celdas = [
    celda('markdown', '''# RetinAI CNN v2: nuevo entrenamiento de cuatro clases

Esta libreta entrena **un nuevo candidato** de normal, retinopatía diabética, glaucoma y catarata. Usa ODIR original, separado por paciente, y 4.410 imágenes Sylhet depuradas de tres clases **solo en entrenamiento**. Descarta las cataratas Sylhet por sus fotografías externas del ojo, las etiquetas contradictorias y las posibles copias de ODIR. Sylhet no aporta identificadores de paciente; sus etiquetas siguen siendo provisionales.

El entrenamiento equilibra las cuatro clases en los lotes y varía moderadamente color y brillo. La selección del modelo usa solo la validación ODIR; **no consulta la prueba interna**. Guarda otro archivo para conservar los modelos anteriores. Que esta libreta termine no autoriza integración clínica: debemos ver su sensibilidad por clase y probarlo en fuentes externas antes de activarlo para el médico.

**Pasos:** selecciona GPU T4 en Colab, ejecuta todo, autoriza el montaje de Drive y envíame `informe_candidato_multifuente_v2.json` que se descargará al final. Puede tardar bastante por el entrenamiento y las descargas de datasets.
'''),
    celda('code', '%pip -q install kagglehub scikit-learn\n'),
    celda('code', f'''import base64, gzip, io, json
from pathlib import Path
import kagglehub
import numpy as np
import pandas as pd
import tensorflow as tf
from tensorflow import keras
from sklearn.metrics import classification_report, confusion_matrix, f1_score

SEMILLA = 325
tf.keras.utils.set_random_seed(SEMILLA)
CLASES = ['normal', 'retinopatia_diabetica', 'glaucoma', 'catarata']
ODIR_B64 = '{odir_b64}'
SYLHET_B64 = '{sylhet_b64}'
odir = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(ODIR_B64))), dtype={{'id_paciente': str}})
sylhet = pd.read_csv(io.BytesIO(gzip.decompress(base64.b64decode(SYLHET_B64))))
assert len(odir) == 4669 and len(sylhet) == 4410
assert sylhet['sha256'].is_unique
grupos = {{p: set(g['id_paciente']) for p, g in odir.groupby('particion')}}
assert not (grupos['entrenamiento'] & grupos['validacion'])
assert not (grupos['entrenamiento'] & grupos['prueba_interna'])
assert not (grupos['validacion'] & grupos['prueba_interna'])
print('GPU:', tf.config.list_physical_devices('GPU'))
if not tf.config.list_physical_devices('GPU'):
    raise RuntimeError('Activa GPU T4 en Entorno de ejecución > Cambiar tipo de entorno.')
display(pd.crosstab(odir['particion'], odir['etiqueta_provisional']))
display(sylhet['etiqueta_carpeta'].value_counts())
'''),
    celda('code', '''raiz_odir = Path(kagglehub.dataset_download('andrewmvd/ocular-disease-recognition-odir5k'))
raiz_sylhet = Path(kagglehub.dataset_download('mahin661/eye-disease-classification-fundus-image-dataset'))
carpetas = [p for p in raiz_odir.rglob('Training Images') if p.is_dir()]
if len(carpetas) != 1:
    raise RuntimeError(f'Carpeta ODIR inesperada: {carpetas}')
odir['ruta'] = odir['archivo'].map(lambda a: str(carpetas[0] / a))
sylhet['ruta'] = sylhet['relativo'].map(lambda a: str(raiz_sylhet / a))
sylhet['etiqueta_provisional'] = sylhet['etiqueta_carpeta'].replace({'diabetic_retinopathy': 'retinopatia_diabetica'})
sylhet['particion'] = 'entrenamiento'
faltan = [r for r in list(odir['ruta']) + list(sylhet['ruta']) if not Path(r).is_file()]
if faltan:
    raise RuntimeError(f'Faltan {len(faltan)} imágenes, por ejemplo {faltan[:3]}')
tabla = pd.concat([odir[['ruta','etiqueta_provisional','particion']], sylhet[['ruta','etiqueta_provisional','particion']]], ignore_index=True)
tabla['clase_id'] = tabla['etiqueta_provisional'].map({c: i for i, c in enumerate(CLASES)})
assert tabla['clase_id'].notna().all()
train = tabla[tabla['particion'].eq('entrenamiento')].copy()
val = tabla[tabla['particion'].eq('validacion')].copy()
assert len(val) == 673
print('Entrenamiento:', len(train), 'Validación:', len(val))
display(pd.crosstab(tabla['particion'], tabla['etiqueta_provisional']))
'''),
    celda('code', '''TAMANO, LOTE = 224, 24
AUTO = tf.data.AUTOTUNE

def abrir(ruta, clase, entrenamiento):
    imagen = tf.io.decode_jpeg(tf.io.read_file(ruta), channels=3)
    imagen = tf.image.convert_image_dtype(imagen, tf.float32)
    imagen = tf.image.resize_with_pad(imagen, TAMANO, TAMANO)
    if entrenamiento:
        imagen = tf.image.random_brightness(imagen, 0.06)
        imagen = tf.image.random_saturation(imagen, 0.75, 1.25)
        imagen = tf.clip_by_value(imagen, 0., 1.)
        imagen = tf.image.random_hue(imagen, 0.03)
        imagen = tf.clip_by_value(imagen, 0., 1.)
    return imagen * 255., clase

por_clase = []
for i in range(4):
    sub = train[train['clase_id'].eq(i)]
    ds = tf.data.Dataset.from_tensor_slices((sub['ruta'].to_numpy(), sub['clase_id'].to_numpy(dtype='int32')))
    ds = ds.shuffle(len(sub), seed=SEMILLA, reshuffle_each_iteration=True).repeat()
    por_clase.append(ds)
train_ds = tf.data.Dataset.sample_from_datasets(por_clase, weights=[0.25]*4, seed=SEMILLA)
train_ds = train_ds.map(lambda r, c: abrir(r, c, True), num_parallel_calls=AUTO).batch(LOTE).prefetch(AUTO)
val_ds = tf.data.Dataset.from_tensor_slices((val['ruta'].to_numpy(), val['clase_id'].to_numpy(dtype='int32')))
val_ds = val_ds.map(lambda r, c: abrir(r, c, False), num_parallel_calls=AUTO).batch(LOTE).prefetch(AUTO)
reales = val['clase_id'].to_numpy()
'''),
    celda('code', '''from google.colab import drive
drive.mount('/content/drive')
destino = Path('/content/drive/MyDrive/RETINAI_CNN_V2')
destino.mkdir(parents=True, exist_ok=True)
base_ruta = destino / 'clasificador_odir_4clases_exploratorio.keras'
if not base_ruta.is_file():
    raise RuntimeError(f'Falta el modelo inicial de 224 px: {base_ruta}')
modelo = keras.models.load_model(base_ruta)
base_cnn = next((c for c in modelo.layers if isinstance(c, keras.Model) and 'efficientnet' in c.name.lower()), None)
if base_cnn is None:
    raise RuntimeError('No encontré EfficientNet dentro del modelo inicial.')
base_cnn.trainable = True
for capa in base_cnn.layers[:-80]:
    capa.trainable = False
for capa in base_cnn.layers:
    if isinstance(capa, keras.layers.BatchNormalization):
        capa.trainable = False
modelo.compile(optimizer=keras.optimizers.Adam(learning_rate=1e-5), loss=keras.losses.SparseCategoricalCrossentropy(), metrics=['accuracy'])
modelo_ruta = destino / 'clasificador_multifuente_4clases_candidato.keras'

class GuardarMejorF1(keras.callbacks.Callback):
    def __init__(self):
        super().__init__()
        self.mejor = -1.
        self.espera = 0
        self.historial = []
    def on_epoch_end(self, epoch, logs=None):
        pred = self.model.predict(val_ds, verbose=0).argmax(axis=1)
        f1 = float(f1_score(reales, pred, average='macro'))
        self.historial.append({'epoca': epoch + 1, 'f1_macro_validacion': f1})
        print(f'F1 macro validación: {f1:.4f}')
        if f1 > self.mejor + 0.002:
            self.mejor, self.espera = f1, 0
            self.model.save(modelo_ruta)
            print('Mejor modelo guardado:', modelo_ruta)
        else:
            self.espera += 1
            if self.espera >= 3:
                self.model.stop_training = True

guardar = GuardarMejorF1()
historia = modelo.fit(train_ds, steps_per_epoch=300, validation_data=val_ds,
                      epochs=12, callbacks=[guardar], verbose=1)
pd.DataFrame(historia.history).to_csv(destino / 'historial_multifuente_4clases.csv', index=False)
'''),
    celda('code', '''mejor = keras.models.load_model(modelo_ruta)
prob = mejor.predict(val_ds, verbose=1)
pred = prob.argmax(axis=1)
informe = {
    'tipo': 'validacion_odir_candidato_multifuente_no_clinico',
    'clases': CLASES,
    'imagenes_entrenamiento_odir': int((odir['particion'] == 'entrenamiento').sum()),
    'imagenes_entrenamiento_sylhet': len(sylhet),
    'imagenes_validacion_odir': len(val),
    'f1_macro': float(f1_score(reales, pred, average='macro')),
    'matriz_confusion': confusion_matrix(reales, pred, labels=list(range(4))).tolist(),
    'reporte_por_clase': classification_report(reales, pred, labels=list(range(4)), target_names=CLASES, output_dict=True, zero_division=0),
    'historial_f1': guardar.historial,
    'modelo': str(modelo_ruta),
}
salida = destino / 'informe_candidato_multifuente_v2.json'
salida.write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding='utf-8')
print(classification_report(reales, pred, labels=list(range(4)), target_names=CLASES, zero_division=0))
print('Matriz (filas reales):', np.array(informe['matriz_confusion']))
print('Informe:', salida)
from google.colab import files
files.download(str(salida))
'''),
]

destino = base / 'RetinAI_CNN_V2_Entrenar_Candidato_Multifuente.ipynb'
destino.write_text(json.dumps({'cells': celdas, 'metadata': {'kernelspec': {'display_name': 'Python 3', 'language': 'python', 'name': 'python3'}}, 'nbformat': 4, 'nbformat_minor': 5}, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
print(destino)
