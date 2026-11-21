"""Clasificador RetinAI v2 con tres cabezas y etiquetas parciales enmascaradas."""

from __future__ import annotations

import argparse
import json
import random
from collections import Counter
from pathlib import Path

import numpy as np
import tensorflow as tf
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    roc_auc_score,
)
from tensorflow import keras

import entrenar_clasificador_multifuente_real_v2 as fuentes


SEED = 42
SIZE = 384
BATCH = 8
DISEASES = ["diabetes", "glaucoma", "catarata"]
CLASSES = ["normal", *DISEASES]
STEPS = 350


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("output_dir", type=Path)
    return parser.parse_args()


def add_partial_labels(rows: list[dict]) -> list[dict]:
    enriched = []
    for original in rows:
        row = original.copy()
        class_name = fuentes.CLASSES[row["label"]]
        labels = np.zeros(3, dtype=np.float32)
        masks = np.zeros(3, dtype=np.float32)
        if row["source"] in {"odir", "cuatro_clases_unico"}:
            masks[:] = 1.0
            if class_name != "normal":
                labels[DISEASES.index(class_name)] = 1.0
        elif row["source"] == "sylhet":
            if class_name == "normal":
                masks[:] = 1.0
            else:
                index = DISEASES.index(class_name)
                masks[index] = 1.0
                labels[index] = 1.0
        elif row["source"] == "idrid":
            masks[0] = 1.0
            labels[0] = float(class_name == "diabetes")
        elif row["source"] == "rim_one":
            masks[1] = 1.0
            labels[1] = float(class_name == "glaucoma")
        else:
            raise RuntimeError(f"Fuente sin política de etiquetas: {row['source']}")
        row["targets"] = np.concatenate([labels, masks]).astype(np.float32)
        enriched.append(row)
    return enriched


def decode(path: tf.Tensor, targets: tf.Tensor, training: bool) -> tuple[tf.Tensor, tf.Tensor]:
    image = tf.io.decode_image(tf.io.read_file(path), channels=3, expand_animations=False)
    image.set_shape((None, None, 3))
    image = tf.image.convert_image_dtype(image, tf.float32)
    image = tf.image.resize_with_pad(image, SIZE, SIZE)
    if training:
        image = tf.image.random_flip_left_right(image, seed=SEED)
        image = tf.image.random_brightness(image, 0.12, seed=SEED)
        image = tf.image.random_contrast(image, 0.68, 1.32, seed=SEED)
        image = tf.image.random_saturation(image, 0.55, 1.45, seed=SEED)
        image = tf.image.random_hue(image, 0.06, seed=SEED)
        image = tf.clip_by_value(image, 0.0, 1.0)
    return image * 255.0, targets


def finite_dataset(rows: list[dict]) -> tf.data.Dataset:
    dataset = tf.data.Dataset.from_tensor_slices((
        np.asarray([row["path"] for row in rows]),
        np.stack([row["targets"] for row in rows]),
    ))
    return dataset.map(
        lambda path, targets: decode(path, targets, False),
        num_parallel_calls=tf.data.AUTOTUNE,
    ).batch(BATCH).prefetch(tf.data.AUTOTUNE)


def balanced_training_dataset(rows: list[dict]) -> tf.data.Dataset:
    source_streams = []
    for source in sorted({row["source"] for row in rows}):
        source_rows = [row for row in rows if row["source"] == source]
        class_streams = []
        for label in sorted({row["label"] for row in source_rows}):
            group = [row for row in source_rows if row["label"] == label]
            dataset = tf.data.Dataset.from_tensor_slices((
                np.asarray([row["path"] for row in group]),
                np.stack([row["targets"] for row in group]),
            ))
            dataset = dataset.shuffle(len(group), seed=SEED, reshuffle_each_iteration=True).repeat()
            dataset = dataset.map(
                lambda path, targets: decode(path, targets, True),
                num_parallel_calls=tf.data.AUTOTUNE,
            )
            class_streams.append(dataset)
        source_streams.append(tf.data.Dataset.sample_from_datasets(
            class_streams,
            weights=[1.0 / len(class_streams)] * len(class_streams),
            seed=SEED,
        ))
    return tf.data.Dataset.sample_from_datasets(
        source_streams,
        weights=[1.0 / len(source_streams)] * len(source_streams),
        seed=SEED,
    ).batch(BATCH).prefetch(tf.data.AUTOTUNE)


@keras.utils.register_keras_serializable(package="RetinAI")
class MaskedBinaryCrossentropy(keras.losses.Loss):
    def call(self, y_true: tf.Tensor, y_pred: tf.Tensor) -> tf.Tensor:
        labels, masks = y_true[:, :3], y_true[:, 3:]
        probabilities = tf.clip_by_value(y_pred, keras.backend.epsilon(), 1.0 - keras.backend.epsilon())
        losses = -(labels * tf.math.log(probabilities) + (1.0 - labels) * tf.math.log(1.0 - probabilities))
        return tf.reduce_sum(losses * masks, axis=1) / tf.maximum(tf.reduce_sum(masks, axis=1), 1.0)


@keras.utils.register_keras_serializable(package="RetinAI")
class NormalProbability(keras.layers.Layer):
    def call(self, disease_probabilities: tf.Tensor) -> tf.Tensor:
        return 1.0 - tf.reduce_max(disease_probabilities, axis=1, keepdims=True)


def binary_metrics(actual: np.ndarray, scores: np.ndarray, threshold: float) -> dict:
    predicted = (scores >= threshold).astype(np.int32)
    tn, fp, fn, tp = confusion_matrix(actual, predicted, labels=[0, 1]).ravel()
    result = {
        "images": int(len(actual)),
        "threshold": float(threshold),
        "accuracy": float(accuracy_score(actual, predicted)),
        "f1": float(f1_score(actual, predicted, zero_division=0)),
        "sensitivity": float(tp / (tp + fn)) if tp + fn else 0.0,
        "specificity": float(tn / (tn + fp)) if tn + fp else 0.0,
        "confusion_matrix": [[int(tn), int(fp)], [int(fn), int(tp)]],
    }
    result["auc"] = float(roc_auc_score(actual, scores)) if len(np.unique(actual)) == 2 else None
    return result


def select_threshold(actual: np.ndarray, scores: np.ndarray) -> float:
    candidates = np.unique(np.concatenate(([0.05], scores, [0.95])))
    candidates = candidates[(candidates >= 0.05) & (candidates <= 0.95)]
    metrics = [binary_metrics(actual, scores, float(value)) for value in candidates]
    eligible = [row for row in metrics if row["sensitivity"] >= 0.65 and row["specificity"] >= 0.70]
    pool = eligible or metrics
    best = max(pool, key=lambda row: (min(row["sensitivity"], row["specificity"]), row["f1"], row["accuracy"]))
    return float(best["threshold"])


def probabilities_four(disease_probabilities: np.ndarray) -> np.ndarray:
    normal = 1.0 - disease_probabilities.max(axis=1, keepdims=True)
    return np.concatenate([normal, disease_probabilities], axis=1)


def predicted_class(disease_probabilities: np.ndarray, thresholds: list[float]) -> np.ndarray:
    ratios = disease_probabilities / np.asarray(thresholds, dtype=np.float32)[None, :]
    strongest = ratios.argmax(axis=1)
    any_positive = (disease_probabilities >= np.asarray(thresholds)[None, :]).any(axis=1)
    return np.where(any_positive, strongest + 1, 0).astype(np.int32)


def full_label_evaluation(rows: list[dict], probabilities: np.ndarray, thresholds: list[float]) -> dict:
    actual = np.asarray([row["label"] for row in rows], dtype=np.int32)
    predicted = predicted_class(probabilities, thresholds)
    return {
        "images": len(rows),
        "accuracy": float(accuracy_score(actual, predicted)),
        "macro_f1": float(f1_score(actual, predicted, labels=[0, 1, 2, 3], average="macro")),
        "confusion_matrix": confusion_matrix(actual, predicted, labels=[0, 1, 2, 3]).tolist(),
        "report": classification_report(
            actual,
            predicted,
            labels=[0, 1, 2, 3],
            target_names=CLASSES,
            output_dict=True,
            zero_division=0,
        ),
    }


def disease_evaluation(rows: list[dict], probabilities: np.ndarray, thresholds: list[float]) -> dict:
    targets = np.stack([row["targets"] for row in rows])
    result = {}
    for index, disease in enumerate(DISEASES):
        known = targets[:, 3 + index] == 1
        if known.sum() == 0:
            continue
        result[disease] = binary_metrics(
            targets[known, index].astype(np.int32),
            probabilities[known, index],
            thresholds[index],
        )
    return result


class VisibleQuality(keras.callbacks.Callback):
    def __init__(self, dataset, rows, model_path: Path, initial_best: float = -1.0):
        super().__init__()
        self.dataset = dataset
        self.rows = rows
        self.model_path = model_path
        self.best = initial_best
        self.wait = 0
        self.history = []

    def on_epoch_end(self, epoch, logs=None):
        probabilities = self.model.predict(self.dataset, verbose=0)
        targets = np.stack([row["targets"] for row in self.rows])
        f1_values = []
        visible = {}
        for index, disease in enumerate(DISEASES):
            known = targets[:, 3 + index] == 1
            metrics = binary_metrics(targets[known, index].astype(np.int32), probabilities[known, index], 0.5)
            f1_values.append(metrics["f1"])
            visible[disease] = {
                "accuracy": metrics["accuracy"],
                "auc": metrics["auc"],
                "f1": metrics["f1"],
                "sensitivity": metrics["sensitivity"],
                "specificity": metrics["specificity"],
            }
        macro_f1 = float(np.mean(f1_values))
        record = {"epoch": int(epoch + 1), "macro_f1": macro_f1, "diseases": visible}
        self.history.append(record)
        print("\nCALIDAD VALIDACION | macro_f1=", round(macro_f1, 4))
        for disease, metrics in visible.items():
            print(
                f"  {disease}: accuracy={metrics['accuracy']:.4f} auc={metrics['auc']:.4f} "
                f"f1={metrics['f1']:.4f} sens={metrics['sensitivity']:.4f} esp={metrics['specificity']:.4f}"
            )
        if macro_f1 > self.best + 0.002:
            self.best = macro_f1
            self.wait = 0
            self.model.save(self.model_path)
            print("  Mejor modelo guardado.")
        else:
            self.wait += 1
            if self.wait >= 3:
                self.model.stop_training = True


def build_export_model(disease_model: keras.Model) -> keras.Model:
    inputs = keras.Input(shape=(SIZE, SIZE, 3), name="retinografia")
    diseases = disease_model(inputs, training=False)
    normal = NormalProbability(name="normal")(diseases)
    outputs = keras.layers.Concatenate(name="probabilidades")([normal, diseases])
    return keras.Model(inputs, outputs, name="retinai_clinico_cuatro_porcentajes")


def main() -> None:
    args = arguments()
    args.output_dir.mkdir(parents=True, exist_ok=True)
    fuentes.ROOT.mkdir(parents=True, exist_ok=True)
    random.seed(SEED)
    np.random.seed(SEED)
    tf.random.set_seed(SEED)

    roots = fuentes.download_sources()
    rows = fuentes.prepare_kaggle_rows(args.output_dir, roots)
    rows.extend(fuentes.prepare_idrid())
    rows.extend(fuentes.prepare_rim_one())
    rows = add_partial_labels(rows)
    missing = [row["path"] for row in rows if not Path(row["path"]).is_file()]
    if missing:
        raise RuntimeError(f"Faltan {len(missing)} imágenes; primera: {missing[0]}")
    train_rows = [row for row in rows if row["split"] == "train"]
    validation_rows = [row for row in rows if row["split"] == "val"]
    print("Imágenes únicas entrenamiento:", len(train_rows))
    print("Imágenes únicas validación:", len(validation_rows))
    print("Conteos fuente/clase:", Counter((row["source"], fuentes.CLASSES[row["label"]]) for row in train_rows))

    train_dataset = balanced_training_dataset(train_rows)
    validation_dataset = finite_dataset(validation_rows)
    candidate_path = args.output_dir / "clasificador_multitarea_enmascarado_v2_candidato.keras"

    base = keras.applications.EfficientNetV2B0(
        include_top=False,
        weights="imagenet",
        input_shape=(SIZE, SIZE, 3),
        pooling="avg",
        include_preprocessing=True,
    )
    base.trainable = False
    inputs = keras.Input(shape=(SIZE, SIZE, 3), name="retinografia")
    x = keras.layers.RandomRotation(0.04, seed=SEED)(inputs)
    x = keras.layers.RandomZoom(0.08, seed=SEED)(x)
    x = base(x, training=False)
    x = keras.layers.Dropout(0.35, seed=SEED)(x)
    outputs = keras.layers.Dense(3, activation="sigmoid", name="enfermedades")(x)
    model = keras.Model(inputs, outputs)
    model.compile(optimizer=keras.optimizers.Adam(7e-4), loss=MaskedBinaryCrossentropy())

    phase_one = VisibleQuality(validation_dataset, validation_rows, candidate_path)
    model.fit(
        train_dataset,
        steps_per_epoch=STEPS,
        validation_data=validation_dataset,
        epochs=8,
        callbacks=[phase_one],
        verbose=1,
    )

    model = keras.models.load_model(candidate_path)
    base = next(layer for layer in model.layers if isinstance(layer, keras.Model) and "efficientnet" in layer.name.lower())
    base.trainable = True
    for layer in base.layers[:-80]:
        layer.trainable = False
    for layer in base.layers:
        if isinstance(layer, keras.layers.BatchNormalization):
            layer.trainable = False
    model.compile(optimizer=keras.optimizers.Adam(8e-6), loss=MaskedBinaryCrossentropy())
    phase_two = VisibleQuality(validation_dataset, validation_rows, candidate_path, initial_best=phase_one.best)
    model.fit(
        train_dataset,
        steps_per_epoch=STEPS,
        validation_data=validation_dataset,
        epochs=10,
        callbacks=[phase_two],
        verbose=1,
    )
    model = keras.models.load_model(candidate_path)

    validation_probabilities = model.predict(validation_dataset, verbose=1)
    validation_targets = np.stack([row["targets"] for row in validation_rows])
    thresholds = []
    for index in range(3):
        known = validation_targets[:, 3 + index] == 1
        thresholds.append(select_threshold(
            validation_targets[known, index].astype(np.int32),
            validation_probabilities[known, index],
        ))
    print("Umbrales calibrados:", dict(zip(DISEASES, thresholds)))

    evaluations = {}
    for split in ("odir_test", "sylhet_test", "cuatro_test"):
        split_rows = [row for row in rows if row["split"] == split]
        probabilities = model.predict(finite_dataset(split_rows), verbose=1)
        evaluations[split] = {
            "classification": full_label_evaluation(split_rows, probabilities, thresholds),
            "diseases": disease_evaluation(split_rows, probabilities, thresholds),
        }
    idrid_rows = [row for row in rows if row["split"] == "idrid_test"]
    rim_rows = [row for row in rows if row["split"] == "rim_test"]
    idrid_probabilities = model.predict(finite_dataset(idrid_rows), verbose=1)
    rim_probabilities = model.predict(finite_dataset(rim_rows), verbose=1)
    evaluations["idrid_external_dr"] = disease_evaluation(idrid_rows, idrid_probabilities, thresholds)["diabetes"]
    evaluations["rim_one_external_glaucoma"] = disease_evaluation(rim_rows, rim_probabilities, thresholds)["glaucoma"]

    odir = evaluations["odir_test"]["classification"]
    idrid = evaluations["idrid_external_dr"]
    rim = evaluations["rim_one_external_glaucoma"]
    criteria = {
        "odir_macro_f1_at_least_0_60": odir["macro_f1"] >= 0.60,
        "odir_normal_recall_at_least_0_65": odir["report"]["normal"]["recall"] >= 0.65,
        "odir_cataract_recall_at_least_0_80": odir["report"]["catarata"]["recall"] >= 0.80,
        "idrid_auc_at_least_0_80": (idrid["auc"] or 0.0) >= 0.80,
        "idrid_sensitivity_at_least_0_65": idrid["sensitivity"] >= 0.65,
        "idrid_specificity_at_least_0_80": idrid["specificity"] >= 0.80,
        "rim_auc_at_least_0_75": (rim["auc"] or 0.0) >= 0.75,
        "rim_sensitivity_at_least_0_65": rim["sensitivity"] >= 0.65,
        "rim_specificity_at_least_0_75": rim["specificity"] >= 0.75,
    }
    approved = all(criteria.values())
    tflite_path = args.output_dir / "clasificador_multitarea_enmascarado_v2_aprobado_demo.tflite"
    export_model_path = args.output_dir / "clasificador_multitarea_enmascarado_v2_cuatro_porcentajes.keras"
    if approved:
        export_model = build_export_model(model)
        export_model.save(export_model_path)
        converter = tf.lite.TFLiteConverter.from_keras_model(export_model)
        tflite_path.write_bytes(converter.convert())

    report = {
        "type": "retinai_v2_masked_multitask_academic",
        "clinical_approval": False,
        "image_size": SIZE,
        "sources": ["ODIR", "Sylhet depurado", "cuatro clases único", "IDRiD", "RIM-ONE DL"],
        "output_order": CLASSES,
        "normal_probability": "1 - max(diabetes, glaucoma, catarata)",
        "training_images_unique": len(train_rows),
        "validation_images_unique": len(validation_rows),
        "thresholds": dict(zip(DISEASES, thresholds)),
        "visible_quality_history": phase_one.history + phase_two.history,
        "evaluations": evaluations,
        "acceptance_criteria": criteria,
        "approved_for_academic_demo": approved,
        "decision": (
            "APROBADO PARA DEMOSTRACION ACADEMICA; NO PARA USO CLINICO"
            if approved else "RECHAZADO PARA DEMOSTRACION: NO SUPERA TODOS LOS CRITERIOS"
        ),
        "keras_candidate": str(candidate_path),
        "keras_four_percentages": str(export_model_path) if approved else None,
        "tflite_exported": str(tflite_path) if approved else None,
    }
    report_path = args.output_dir / "informe_clasificador_multitarea_enmascarado_v2.json"
    report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print("\n", "=" * 80)
    print("DECISION FINAL:", report["decision"])
    print("CRITERIOS:", json.dumps(criteria, ensure_ascii=False, indent=2))
    print("INFORME:", report_path)
    print("TFLITE:", tflite_path if approved else "NO GENERADO")


if __name__ == "__main__":
    main()
