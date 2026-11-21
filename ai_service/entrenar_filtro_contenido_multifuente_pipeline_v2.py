"""Entrena el filtro de contenido multifuente y prepara el pipeline RetinAI v2.

Positivos: ODIR, Sylhet, Four-Class sin duplicados ODIR, IDRiD y RIM-ONE.
Negativos: imágenes naturales Imagenette y regiones perioculares de LFW.
El filtro de calidad y el clasificador clínico se conservan como modelos separados.
"""

from __future__ import annotations

import argparse
import json
import math
import sys
from collections import Counter
from pathlib import Path

import numpy as np
import tensorflow as tf
import tensorflow_datasets as tfds
from sklearn.metrics import confusion_matrix, f1_score, roc_auc_score
from tensorflow import keras


SEED = 73
SIZE = 224
BATCH = 24
STEPS = 500
POSITIVE_SOURCES = ["odir", "sylhet", "cuatro_clases_unico", "idrid", "rim_one"]


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("output_dir", type=Path)
    return parser.parse_args()


def prepare_retina(path: tf.Tensor, source: tf.Tensor, training: bool):
    image = tf.io.decode_image(tf.io.read_file(path), channels=3, expand_animations=False)
    image.set_shape((None, None, 3))
    image = tf.image.convert_image_dtype(image, tf.float32)
    image = tf.image.resize_with_pad(image, SIZE, SIZE)
    if training:
        image = tf.image.random_flip_left_right(image, seed=SEED)
        image = tf.image.random_brightness(image, 0.12, seed=SEED)
        image = tf.image.random_contrast(image, 0.70, 1.30, seed=SEED)
        image = tf.image.random_saturation(image, 0.65, 1.35, seed=SEED)
        image = tf.image.random_hue(image, 0.04, seed=SEED)
    image = tf.clip_by_value(image, 0.0, 1.0) * 255.0
    return image, tf.constant(1.0, tf.float32), source


def prepare_lfw(example: dict, training: bool):
    image = tf.image.convert_image_dtype(example["image"], tf.float32)
    image = tf.image.crop_to_bounding_box(image, 45, 20, 125, 210)
    image = tf.image.resize_with_pad(image, SIZE, SIZE)
    if training:
        image = tf.image.random_flip_left_right(image, seed=SEED)
        image = tf.image.random_brightness(image, 0.10, seed=SEED)
    return tf.clip_by_value(image, 0.0, 1.0) * 255.0, tf.constant(0.0), tf.constant("lfw_periocular")


def prepare_imagenette(example: dict, training: bool):
    image = tf.image.convert_image_dtype(example["image"], tf.float32)
    image = tf.image.resize_with_pad(image, SIZE, SIZE)
    if training:
        image = tf.image.random_flip_left_right(image, seed=SEED)
        image = tf.image.random_brightness(image, 0.10, seed=SEED)
        image = tf.image.random_contrast(image, 0.80, 1.20, seed=SEED)
    return tf.clip_by_value(image, 0.0, 1.0) * 255.0, tf.constant(0.0), tf.constant("imagenette")


def retina_dataset(rows: list[dict], training: bool = False) -> tf.data.Dataset:
    paths = np.asarray([row["path"] for row in rows])
    sources = np.asarray([row["source"] for row in rows])
    dataset = tf.data.Dataset.from_tensor_slices((paths, sources))
    if training:
        dataset = dataset.shuffle(len(rows), seed=SEED, reshuffle_each_iteration=True)
    return dataset.map(
        lambda path, source: prepare_retina(path, source, training),
        num_parallel_calls=tf.data.AUTOTUNE,
    )


def collect(model: keras.Model, dataset: tf.data.Dataset) -> tuple[np.ndarray, np.ndarray, np.ndarray]:
    actual, probabilities, sources = [], [], []
    for images, labels, source in dataset.batch(BATCH).prefetch(tf.data.AUTOTUNE):
        probabilities.extend(model.predict(images, verbose=0).reshape(-1).tolist())
        actual.extend(labels.numpy().astype("int32").tolist())
        sources.extend(value.decode() for value in source.numpy().tolist())
    return np.asarray(actual), np.asarray(probabilities), np.asarray(sources)


def source_metrics(actual: np.ndarray, probabilities: np.ndarray, sources: np.ndarray, threshold: float) -> dict:
    predicted = (probabilities >= threshold).astype("int32")
    result = {}
    for source in sorted(set(sources.tolist())):
        mask = sources == source
        source_actual = actual[mask]
        source_predicted = predicted[mask]
        if int(source_actual[0]) == 1:
            metric = float(source_predicted.mean())
            result[source] = {"images": int(mask.sum()), "sensitivity": metric}
        else:
            metric = float((source_predicted == 0).mean())
            result[source] = {"images": int(mask.sum()), "specificity": metric}
    return result


def select_threshold(actual: np.ndarray, probabilities: np.ndarray, sources: np.ndarray) -> tuple[float, bool]:
    candidates = np.unique(np.r_[np.linspace(0.05, 0.99, 189), probabilities])
    positive_sources = sorted(set(sources[actual == 1].tolist()))
    eligible = []
    fallback = []
    for threshold in candidates:
        predicted = probabilities >= threshold
        specificity = float((~predicted[actual == 0]).mean())
        sensitivities = [float(predicted[(actual == 1) & (sources == source)].mean()) for source in positive_sources]
        minimum = min(sensitivities)
        average = float(np.mean(sensitivities))
        fallback.append((min(minimum, specificity), minimum + specificity, average, -float(threshold), float(threshold)))
        if specificity >= 0.98:
            eligible.append((minimum, average, specificity, -float(threshold), float(threshold)))
    if eligible:
        return max(eligible)[-1], True
    return max(fallback)[-1], False


class VisibleContentQuality(keras.callbacks.Callback):
    def __init__(self, validation: tf.data.Dataset, model_path: Path, previous_best: float = -1.0):
        super().__init__()
        self.validation = validation
        self.model_path = model_path
        self.best = previous_best
        self.wait = 0
        self.history: list[dict] = []

    def on_epoch_end(self, epoch, logs=None):
        actual, probabilities, sources = collect(self.model, self.validation)
        predicted = (probabilities >= 0.5).astype("int32")
        f1 = float(f1_score(actual, predicted))
        auc = float(roc_auc_score(actual, probabilities))
        metrics = source_metrics(actual, probabilities, sources, 0.5)
        positive = [values["sensitivity"] for values in metrics.values() if "sensitivity" in values]
        negative = [values["specificity"] for values in metrics.values() if "specificity" in values]
        score = 0.65 * min(positive) + 0.35 * float(np.mean(negative))
        record = {"epoch": int(epoch + 1), "f1": f1, "auc": auc, "score": score, "by_source": metrics}
        self.history.append(record)
        print(f"CALIDAD CONTENIDO | f1={f1:.4f} auc={auc:.4f} minimo_retinas={min(positive):.4f} especificidad={np.mean(negative):.4f}")
        for source, values in metrics.items():
            name, value = next((key, val) for key, val in values.items() if key in {"sensitivity", "specificity"})
            print(f"  {source}: {name}={value:.4f} n={values['images']}")
        if score > self.best + 0.001:
            self.best = score
            self.wait = 0
            self.model.save(self.model_path)
            print("  Mejor filtro guardado.")
        else:
            self.wait += 1
            if self.wait >= 3:
                self.model.stop_training = True


def main() -> None:
    args = arguments()
    args.output_dir.mkdir(parents=True, exist_ok=True)
    sys.path.insert(0, str(args.output_dir))
    import entrenar_clasificador_multifuente_real_v2 as sources

    tf.keras.utils.set_random_seed(SEED)
    if not tf.config.list_physical_devices("GPU"):
        raise RuntimeError("Selecciona GPU T4 en Entorno de ejecución > Cambiar tipo de entorno.")

    sources.ROOT.mkdir(parents=True, exist_ok=True)
    roots = sources.download_sources()
    rows = sources.prepare_kaggle_rows(args.output_dir, roots)
    rows.extend(sources.prepare_idrid())
    rows.extend(sources.prepare_rim_one())
    rows = list({row["sha256"]: row for row in rows}.values())
    train_rows = [row for row in rows if row["split"] == "train"]
    validation_rows = [row for row in rows if row["split"] == "val"]
    test_rows = [row for row in rows if row["split"] not in {"train", "val"}]
    print("Retinografías contenido:", len(train_rows), len(validation_rows), len(test_rows))
    print("Fuentes entrenamiento:", Counter(row["source"] for row in train_rows))

    negative_train_each = len(train_rows) // 2
    negative_validation_each = math.ceil(len(validation_rows) / 2)
    negative_test_each = math.ceil(len(test_rows) / 2)
    lfw = tfds.load("lfw", split="train", shuffle_files=False)
    lfw_partition = lambda example: tf.strings.to_hash_bucket_fast(example["label"], 100)
    lfw_train = lfw.filter(lambda x: lfw_partition(x) < 70).take(negative_train_each)
    lfw_validation = lfw.filter(lambda x: (lfw_partition(x) >= 70) & (lfw_partition(x) < 85)).take(negative_validation_each)
    lfw_test = lfw.filter(lambda x: lfw_partition(x) >= 85).take(negative_test_each)

    imagenette_train_all = tfds.load("imagenette/320px-v2", split="train", shuffle_files=False)
    imagenette_test_all = tfds.load("imagenette/320px-v2", split="validation", shuffle_files=False)
    imagenette_train = imagenette_train_all.take(negative_train_each)
    imagenette_validation = imagenette_train_all.skip(negative_train_each).take(negative_validation_each)
    imagenette_test = imagenette_test_all.take(negative_test_each)

    positive_train = retina_dataset(train_rows, True).repeat()
    negative_lfw_train = lfw_train.map(lambda x: prepare_lfw(x, True), num_parallel_calls=tf.data.AUTOTUNE).repeat()
    negative_imagenette_train = imagenette_train.map(lambda x: prepare_imagenette(x, True), num_parallel_calls=tf.data.AUTOTUNE).repeat()
    training = tf.data.Dataset.sample_from_datasets(
        [positive_train, negative_lfw_train, negative_imagenette_train],
        weights=[0.50, 0.25, 0.25], seed=SEED,
    )
    training = training.map(lambda image, label, source: (image, label), num_parallel_calls=tf.data.AUTOTUNE)
    training = training.batch(BATCH).prefetch(tf.data.AUTOTUNE)

    validation = retina_dataset(validation_rows).concatenate(
        lfw_validation.map(lambda x: prepare_lfw(x, False), num_parallel_calls=tf.data.AUTOTUNE)
    ).concatenate(
        imagenette_validation.map(lambda x: prepare_imagenette(x, False), num_parallel_calls=tf.data.AUTOTUNE)
    )
    testing = retina_dataset(test_rows).concatenate(
        lfw_test.map(lambda x: prepare_lfw(x, False), num_parallel_calls=tf.data.AUTOTUNE)
    ).concatenate(
        imagenette_test.map(lambda x: prepare_imagenette(x, False), num_parallel_calls=tf.data.AUTOTUNE)
    )
    validation_fit = validation.map(lambda image, label, source: (image, label)).batch(BATCH).prefetch(tf.data.AUTOTUNE)

    model_path = args.output_dir / "filtro_retinografia_multifuente_v2_candidato.keras"
    base = keras.applications.EfficientNetB0(
        include_top=False, weights="imagenet", input_shape=(SIZE, SIZE, 3), pooling="avg"
    )
    base.trainable = False
    inputs = keras.Input((SIZE, SIZE, 3), name="imagen")
    features = base(inputs, training=False)
    features = keras.layers.Dropout(0.30)(features)
    outputs = keras.layers.Dense(1, activation="sigmoid", name="probabilidad_retinografia")(features)
    model = keras.Model(inputs, outputs, name="retinai_filtro_contenido_multifuente_v2")
    model.compile(keras.optimizers.Adam(8e-4), "binary_crossentropy", metrics=[keras.metrics.AUC(name="auc")])

    visible_1 = VisibleContentQuality(validation, model_path)
    model.fit(training, validation_data=validation_fit, steps_per_epoch=STEPS, epochs=6, callbacks=[visible_1])

    model = keras.models.load_model(model_path)
    backbone = next(layer for layer in model.layers if isinstance(layer, keras.Model))
    backbone.trainable = True
    for layer in backbone.layers[:-35]:
        layer.trainable = False
    model.compile(keras.optimizers.Adam(1e-5), "binary_crossentropy", metrics=[keras.metrics.AUC(name="auc")])
    visible_2 = VisibleContentQuality(validation, model_path, previous_best=visible_1.best)
    model.fit(training, validation_data=validation_fit, steps_per_epoch=STEPS, epochs=6, callbacks=[visible_2])
    model = keras.models.load_model(model_path)

    y_validation, p_validation, source_validation = collect(model, validation)
    threshold, met_specificity_target = select_threshold(y_validation, p_validation, source_validation)
    y_test, p_test, source_test = collect(model, testing)
    test_predicted = (p_test >= threshold).astype("int32")
    matrix = confusion_matrix(y_test, test_predicted, labels=[0, 1])
    tn, fp, fn, tp = matrix.ravel()
    by_source = source_metrics(y_test, p_test, source_test, threshold)
    positive_metrics = [values["sensitivity"] for values in by_source.values() if "sensitivity" in values]
    negative_metrics = [values["specificity"] for values in by_source.values() if "specificity" in values]

    converter = tf.lite.TFLiteConverter.from_keras_model(model)
    tflite_path = args.output_dir / "filtro_retinografia_multifuente_v2_candidato.tflite"
    tflite_path.write_bytes(converter.convert())
    interpreter = tf.lite.Interpreter(model_path=str(tflite_path))
    interpreter.allocate_tensors()
    assert tuple(interpreter.get_input_details()[0]["shape"]) == (1, SIZE, SIZE, 3)
    assert tuple(interpreter.get_output_details()[0]["shape"]) == (1, 1)

    accepted = {
        "overall_retina_sensitivity_at_least_0_95": float(tp / (tp + fn)) >= 0.95,
        "every_retina_source_sensitivity_at_least_0_85": min(positive_metrics) >= 0.85,
        "negative_specificity_at_least_0_98": float(tn / (tn + fp)) >= 0.98,
    }
    report = {
        "type": "retinai_content_filter_multisource_v2",
        "decision": "APROBADO PARA PIPELINE ACADEMICO" if all(accepted.values()) else "CANDIDATO: REVISAR METRICAS",
        "image_size": SIZE,
        "threshold": float(threshold),
        "validation_met_specificity_target": met_specificity_target,
        "training_retinas": len(train_rows),
        "validation_retinas": len(validation_rows),
        "test_retinas": len(test_rows),
        "positive_sources": POSITIVE_SOURCES,
        "negative_sources": ["Imagenette (incluye animales y objetos)", "LFW periocular"],
        "test_confusion_matrix": matrix.tolist(),
        "test_auc": float(roc_auc_score(y_test, p_test)),
        "test_f1_retina": float(f1_score(y_test, test_predicted)),
        "test_retina_sensitivity": float(tp / (tp + fn)),
        "test_negative_specificity": float(tn / (tn + fp)),
        "test_by_source": by_source,
        "criteria": accepted,
        "keras_candidate": str(model_path),
        "tflite_candidate": str(tflite_path),
        "quality_model_reused": str(args.output_dir / "filtro_calidad_mshf_candidato.tflite"),
        "clinical_model_reused": str(args.output_dir / "clasificador_multitarea_enmascarado_v2_candidato.keras"),
        "training_history": visible_1.history + visible_2.history,
        "note": "Pipeline académico; no sustituye evaluación médica ni constituye aprobación clínica.",
    }
    report_path = args.output_dir / "informe_filtro_contenido_multifuente_pipeline_v2.json"
    report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print("\n", "=" * 80)
    print("DECISIÓN FILTRO CONTENIDO:", report["decision"])
    print("UMBRAL:", threshold)
    print("SENSIBILIDAD RETINAS:", report["test_retina_sensitivity"])
    print("ESPECIFICIDAD NO RETINAS:", report["test_negative_specificity"])
    print("POR FUENTE:", json.dumps(by_source, ensure_ascii=False, indent=2))
    print("INFORME:", report_path)
    print("TFLITE:", tflite_path)


if __name__ == "__main__":
    main()
