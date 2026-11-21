"""Entrena y evalua un detector academico de glaucoma con RIM-ONE DL.

La particion por hospital del conjunto se respeta: solo ``training_set`` se
usa para ajustar el modelo y el umbral; ``test_set`` permanece externo hasta
la evaluacion final. El script no marca el modelo como apto para uso clinico.
"""

from __future__ import annotations

import argparse
import json
import random
import tempfile
import zipfile
from pathlib import Path

import numpy as np
import tensorflow as tf
from sklearn.metrics import confusion_matrix, roc_auc_score
from sklearn.model_selection import train_test_split
from tensorflow import keras


SEED = 42
SIZE = 224
BATCH = 16


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("zip_path", type=Path)
    parser.add_argument("output_dir", type=Path)
    return parser.parse_args()


def extract_partition(zip_path: Path, root: Path) -> tuple[list[dict], list[dict]]:
    rows: dict[str, list[dict]] = {"training_set": [], "test_set": []}
    with zipfile.ZipFile(zip_path) as archive:
        names = sorted(
            name for name in archive.namelist()
            if "/partitioned_by_hospital/" in name and name.lower().endswith(".png")
        )
        for name in names:
            parts = Path(name).parts
            marker = parts.index("partitioned_by_hospital")
            split, label = parts[marker + 1], parts[marker + 2]
            if split not in rows or label not in {"normal", "glaucoma"}:
                continue
            destination = root / split / label / Path(name).name
            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_bytes(archive.read(name))
            rows[split].append({"path": str(destination), "label": int(label == "glaucoma")})

    expected = {
        "training_set": {0: 195, 1: 116},
        "test_set": {0: 118, 1: 56},
    }
    for split, split_rows in rows.items():
        counts = {label: sum(row["label"] == label for row in split_rows) for label in (0, 1)}
        if counts != expected[split]:
            raise RuntimeError(f"Conteos inesperados en {split}: {counts}")
    return rows["training_set"], rows["test_set"]


def make_dataset(rows: list[dict], training: bool) -> tf.data.Dataset:
    paths = np.asarray([row["path"] for row in rows])
    labels = np.asarray([row["label"] for row in rows], dtype=np.float32)
    dataset = tf.data.Dataset.from_tensor_slices((paths, labels))
    if training:
        dataset = dataset.shuffle(len(rows), seed=SEED, reshuffle_each_iteration=True)

    def load(path: tf.Tensor, label: tf.Tensor) -> tuple[tf.Tensor, tf.Tensor]:
        image = tf.io.decode_png(tf.io.read_file(path), channels=3)
        image = tf.image.convert_image_dtype(image, tf.float32)
        image = tf.image.resize_with_pad(image, SIZE, SIZE) * 255.0
        if training:
            image = tf.image.random_flip_left_right(image, seed=SEED)
            image = tf.image.random_brightness(image, 18.0, seed=SEED)
            image = tf.image.random_contrast(image, 0.75, 1.25, seed=SEED)
            image = tf.image.random_saturation(image, 0.70, 1.30, seed=SEED)
            image = tf.clip_by_value(image, 0.0, 255.0)
        return image, label

    return (
        dataset.map(load, num_parallel_calls=tf.data.AUTOTUNE)
        .batch(BATCH)
        .prefetch(tf.data.AUTOTUNE)
    )


def metrics_at_threshold(y_true: np.ndarray, probabilities: np.ndarray, threshold: float) -> dict:
    predicted = (probabilities >= threshold).astype(np.int32)
    tn, fp, fn, tp = confusion_matrix(y_true, predicted, labels=[0, 1]).ravel()
    return {
        "threshold": float(threshold),
        "confusion_matrix": [[int(tn), int(fp)], [int(fn), int(tp)]],
        "sensitivity": float(tp / (tp + fn)),
        "specificity": float(tn / (tn + fp)),
        "balanced_accuracy": float(((tp / (tp + fn)) + (tn / (tn + fp))) / 2),
    }


def choose_threshold(y_true: np.ndarray, probabilities: np.ndarray) -> float:
    candidates = np.unique(np.concatenate(([0.0], probabilities, [1.0])))
    scored = [metrics_at_threshold(y_true, probabilities, float(value)) for value in candidates]
    # La eleccion usa solo validacion interna y equilibra sensibilidad/especificidad.
    best = max(scored, key=lambda row: (min(row["sensitivity"], row["specificity"]), row["balanced_accuracy"]))
    return float(best["threshold"])


def main() -> None:
    args = parse_args()
    random.seed(SEED)
    np.random.seed(SEED)
    tf.random.set_seed(SEED)
    args.output_dir.mkdir(parents=True, exist_ok=True)

    with tempfile.TemporaryDirectory(prefix="retinai_rim_one_") as temporary:
        train_all, test_rows = extract_partition(args.zip_path, Path(temporary))
        train_rows, validation_rows = train_test_split(
            train_all,
            test_size=0.20,
            random_state=SEED,
            stratify=[row["label"] for row in train_all],
        )
        train_ds = make_dataset(train_rows, True)
        validation_ds = make_dataset(validation_rows, False)
        test_ds = make_dataset(test_rows, False)

        base = keras.applications.EfficientNetB0(
            include_top=False,
            weights="imagenet",
            input_shape=(SIZE, SIZE, 3),
            pooling="avg",
        )
        base.trainable = False
        inputs = keras.Input(shape=(SIZE, SIZE, 3), name="imagen")
        x = keras.layers.RandomRotation(0.04, seed=SEED)(inputs)
        x = keras.layers.RandomZoom(0.08, seed=SEED)(x)
        x = base(x, training=False)
        x = keras.layers.Dropout(0.35, seed=SEED)(x)
        outputs = keras.layers.Dense(1, activation="sigmoid", name="probabilidad_glaucoma")(x)
        model = keras.Model(inputs, outputs)

        counts = np.bincount([row["label"] for row in train_rows], minlength=2)
        class_weight = {index: len(train_rows) / (2.0 * count) for index, count in enumerate(counts)}
        checkpoint = args.output_dir / "glaucoma_rim_one_academico.keras"
        callbacks = [
            keras.callbacks.ModelCheckpoint(str(checkpoint), monitor="val_auc", mode="max", save_best_only=True),
            keras.callbacks.EarlyStopping(monitor="val_auc", mode="max", patience=3, restore_best_weights=True),
            keras.callbacks.ReduceLROnPlateau(monitor="val_auc", mode="max", patience=2, factor=0.3),
        ]
        model.compile(
            optimizer=keras.optimizers.Adam(1e-3),
            loss="binary_crossentropy",
            metrics=[keras.metrics.AUC(name="auc")],
        )
        model.fit(train_ds, validation_data=validation_ds, epochs=10, class_weight=class_weight, callbacks=callbacks)

        model = keras.models.load_model(checkpoint)
        base = next(layer for layer in model.layers if isinstance(layer, keras.Model) and "efficientnet" in layer.name.lower())
        base.trainable = True
        for layer in base.layers[:-30]:
            layer.trainable = False
        for layer in base.layers:
            if isinstance(layer, keras.layers.BatchNormalization):
                layer.trainable = False
        model.compile(
            optimizer=keras.optimizers.Adam(1e-5),
            loss="binary_crossentropy",
            metrics=[keras.metrics.AUC(name="auc")],
        )
        model.fit(train_ds, validation_data=validation_ds, epochs=8, class_weight=class_weight, callbacks=callbacks)

        model = keras.models.load_model(checkpoint)
        validation_probabilities = model.predict(validation_ds, verbose=0).reshape(-1)
        validation_labels = np.asarray([row["label"] for row in validation_rows], dtype=np.int32)
        threshold = choose_threshold(validation_labels, validation_probabilities)

        test_probabilities = model.predict(test_ds, verbose=0).reshape(-1)
        test_labels = np.asarray([row["label"] for row in test_rows], dtype=np.int32)
        report = {
            "type": "academic_glaucoma_detector_rim_one",
            "clinical_approval": False,
            "split": "partitioned_by_hospital",
            "training_images": len(train_rows),
            "internal_validation_images": len(validation_rows),
            "external_hospital_test_images": len(test_rows),
            "validation_auc": float(roc_auc_score(validation_labels, validation_probabilities)),
            "test_auc": float(roc_auc_score(test_labels, test_probabilities)),
            "validation": metrics_at_threshold(validation_labels, validation_probabilities, threshold),
            "hospital_test": metrics_at_threshold(test_labels, test_probabilities, threshold),
            "note": "Modelo real para demostracion academica; no aprobado para decisiones clinicas.",
        }

        tflite_path = args.output_dir / "glaucoma_rim_one_academico.tflite"
        converter = tf.lite.TFLiteConverter.from_keras_model(model)
        tflite_path.write_bytes(converter.convert())
        report_path = args.output_dir / "informe_glaucoma_rim_one_academico.json"
        report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps(report, ensure_ascii=False, indent=2))
        print("TFLite:", tflite_path)
        print("Informe:", report_path)


if __name__ == "__main__":
    main()
