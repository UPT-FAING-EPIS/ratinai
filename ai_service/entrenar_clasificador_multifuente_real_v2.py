"""Entrenamiento multifuente reproducible del clasificador académico RetinAI v2.

Fuentes de entrenamiento independientes: ODIR original, Sylhet depurado,
remanente no duplicado del conjunto de cuatro clases, IDRiD y RIM-ONE DL.
IDRiD testing y RIM-ONE test por hospital se conservan como pruebas externas.
"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import random
import urllib.request
import zipfile
from collections import Counter
from pathlib import Path

import gdown
import kagglehub
import numpy as np
import pandas as pd
import tensorflow as tf
from sklearn.metrics import (
    accuracy_score,
    balanced_accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    roc_auc_score,
)
from sklearn.model_selection import train_test_split
from tensorflow import keras


SEED = 42
SIZE = 320
BATCH = 12
CLASSES = ["normal", "diabetes", "glaucoma", "catarata"]
LABEL_ID = {name: index for index, name in enumerate(CLASSES)}
ROOT = Path("/content/retinai_multifuente_real_v2")


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("output_dir", type=Path)
    return parser.parse_args()


def deterministic_split(token: str) -> str:
    value = int(hashlib.sha256((token + "|split-v2").encode()).hexdigest()[:8], 16) % 100
    return "train" if value < 80 else ("val" if value < 90 else "test")


def download_sources() -> dict[str, Path]:
    references = {
        "odir_original": "andrewmvd/ocular-disease-recognition-odir5k",
        "sylhet": "mahin661/eye-disease-classification-fundus-image-dataset",
        "cuatro_original": "gunavenkatdoddi/eye-diseases-classification",
    }
    roots = {}
    for name, reference in references.items():
        print("Descargando/verificando Kaggle:", name, flush=True)
        roots[name] = Path(kagglehub.dataset_download(reference))
    return roots


def prepare_kaggle_rows(output_dir: Path, roots: dict[str, Path]) -> list[dict]:
    inventory = pd.read_csv(output_dir / "retinai_fuentes_v2.csv", dtype=str).fillna("")
    odir_manifest = pd.read_csv(output_dir / "odir_manifest_v2_split.csv", dtype=str).fillna("")
    sylhet_manifest = pd.read_csv(output_dir / "sylhet_manifest_v2.csv", dtype=str).fillna("")
    rows: list[dict] = []

    inv_odir = inventory[
        inventory["fuente"].eq("odir_original")
        & inventory["etiqueta_carpeta"].eq("Training Images")
    ].drop_duplicates("sha256")
    odir_path = inv_odir.set_index("sha256")["relativo"].to_dict()
    label_map = {
        "normal": "normal",
        "retinopatia_diabetica": "diabetes",
        "glaucoma": "glaucoma",
        "catarata": "catarata",
    }
    split_map = {"entrenamiento": "train", "validacion": "val", "prueba_interna": "odir_test"}
    odir = odir_manifest[odir_manifest["estado"].eq("candidato_clase_unica")].copy()
    for row in odir.itertuples(index=False):
        relative = odir_path.get(row.sha256)
        if not relative or row.etiqueta_provisional not in label_map:
            continue
        rows.append({
            "path": str(roots["odir_original"] / relative),
            "label": LABEL_ID[label_map[row.etiqueta_provisional]],
            "source": "odir",
            "split": split_map[row.particion],
            "group": f"odir:{row.id_paciente}",
            "sha256": row.sha256,
        })

    sylhet = sylhet_manifest[sylhet_manifest["estado"].eq("candidato_adicional_no_validado")].copy()
    sylhet = sylhet.drop_duplicates("sha256")
    sylhet_labels = {"normal": "normal", "diabetic_retinopathy": "diabetes", "glaucoma": "glaucoma"}
    for row in sylhet.itertuples(index=False):
        if row.etiqueta_carpeta not in sylhet_labels:
            continue
        rows.append({
            "path": str(roots["sylhet"] / row.relativo),
            "label": LABEL_ID[sylhet_labels[row.etiqueta_carpeta]],
            "source": "sylhet",
            "split": {"train": "train", "val": "val", "test": "sylhet_test"}[deterministic_split(row.sha256)],
            "group": f"sylhet:{row.sha256}",
            "sha256": row.sha256,
        })

    odir_hashes = set(inventory.loc[inventory["fuente"].eq("odir_original"), "sha256"])
    four = inventory[inventory["fuente"].eq("cuatro_original")].copy()
    conflicts = set(
        four.groupby("sha256")["etiqueta_carpeta"].nunique().loc[lambda values: values > 1].index
    )
    four = four[~four["sha256"].isin(odir_hashes | conflicts)].drop_duplicates("sha256")
    four_labels = {
        "normal": "normal",
        "diabetic_retinopathy": "diabetes",
        "glaucoma": "glaucoma",
        "cataract": "catarata",
    }
    for row in four.itertuples(index=False):
        if row.etiqueta_carpeta not in four_labels:
            continue
        rows.append({
            "path": str(roots["cuatro_original"] / row.relativo),
            "label": LABEL_ID[four_labels[row.etiqueta_carpeta]],
            "source": "cuatro_clases_unico",
            "split": {"train": "train", "val": "val", "test": "cuatro_test"}[deterministic_split(row.sha256)],
            "group": f"cuatro:{row.sha256}",
            "sha256": row.sha256,
        })
    return rows


def prepare_idrid() -> list[dict]:
    zip_path = ROOT / "idrid_grading.zip"
    if not zip_path.is_file():
        print("Descargando IDRiD...", flush=True)
        urllib.request.urlretrieve(
            "https://zenodo.org/records/17219542/files/B.%20Disease%20Grading.zip?download=1",
            zip_path,
        )
    if hashlib.md5(zip_path.read_bytes()).hexdigest() != "b9239a4b956021a1cf0225522f11f58f":
        raise RuntimeError("La copia IDRiD cambió")
    extracted = ROOT / "idrid"
    records = []
    with zipfile.ZipFile(zip_path) as archive:
        names = archive.namelist()
        for official, key in (("training", "Training"), ("testing", "Testing")):
            csv_name = next(name for name in names if name.endswith(f"{key} Labels.csv"))
            labels = pd.read_csv(io.BytesIO(archive.read(csv_name)))
            for label_row in labels.itertuples(index=False):
                image_name = str(label_row[0]) + ".jpg"
                candidates = [
                    name for name in names
                    if name.endswith("/" + image_name) and f"{key} Set/" in name and "Original Images" in name
                ]
                if len(candidates) != 1:
                    raise RuntimeError(f"IDRiD no único: {image_name}")
                content = archive.read(candidates[0])
                sha = hashlib.sha256(content).hexdigest()
                destination = extracted / official / image_name
                destination.parent.mkdir(parents=True, exist_ok=True)
                destination.write_bytes(content)
                records.append({
                    "path": str(destination),
                    "grade": int(label_row[1]),
                    "official": official,
                    "sha256": sha,
                    "image": image_name,
                })
    table = pd.DataFrame(records)
    bad_hashes = set(
        table.groupby("sha256").filter(
            lambda group: group["grade"].nunique() > 1 or group["official"].nunique() > 1
        )["sha256"]
    )
    table = table[~table["sha256"].isin(bad_hashes)].drop_duplicates("sha256").copy()
    train_table = table[table["official"].eq("training")].copy()
    train_table["binary"] = train_table["grade"].gt(0).astype(int)
    train_indices, val_indices = train_test_split(
        train_table.index,
        test_size=0.15,
        random_state=SEED,
        stratify=train_table["binary"],
    )
    validation = set(val_indices)
    rows = []
    for index, row in table.iterrows():
        if row["official"] == "testing":
            split = "idrid_test"
        else:
            split = "val" if index in validation else "train"
        rows.append({
            "path": row["path"],
            "label": LABEL_ID["normal" if row["grade"] == 0 else "diabetes"],
            "source": "idrid",
            "split": split,
            "group": f"idrid:{row['sha256']}",
            "sha256": row["sha256"],
            "grade": int(row["grade"]),
        })
    return rows


def prepare_rim_one() -> list[dict]:
    zip_path = ROOT / "rim_one_dl_images.zip"
    if not zip_path.is_file():
        gdown.download(id="1teYi_smpLiNZNJcTWdxXgKKLW2fkUQr4", output=str(zip_path), quiet=False)
    sha = hashlib.file_digest(zip_path.open("rb"), "sha256").hexdigest()
    if sha != "85aed5f95c794f52d11b6ed953032108a66576ffcff4b587365df467491b603d":
        raise RuntimeError("La copia RIM-ONE DL cambió")
    extracted = ROOT / "rim_one"
    records = []
    with zipfile.ZipFile(zip_path) as archive:
        names = sorted(
            name for name in archive.namelist()
            if "/partitioned_by_hospital/" in name and name.lower().endswith(".png")
        )
        for name in names:
            parts = Path(name).parts
            marker = parts.index("partitioned_by_hospital")
            official, label = parts[marker + 1], parts[marker + 2]
            if official not in {"training_set", "test_set"} or label not in {"normal", "glaucoma"}:
                continue
            destination = extracted / official / label / Path(name).name
            destination.parent.mkdir(parents=True, exist_ok=True)
            content = archive.read(name)
            destination.write_bytes(content)
            records.append({
                "path": str(destination),
                "label_name": label,
                "official": official,
                "sha256": hashlib.sha256(content).hexdigest(),
            })
    table = pd.DataFrame(records).drop_duplicates("sha256")
    training = table[table["official"].eq("training_set")].copy()
    train_indices, val_indices = train_test_split(
        training.index,
        test_size=0.15,
        random_state=SEED,
        stratify=training["label_name"],
    )
    validation = set(val_indices)
    rows = []
    for index, row in table.iterrows():
        split = "rim_test" if row["official"] == "test_set" else ("val" if index in validation else "train")
        rows.append({
            "path": row["path"],
            "label": LABEL_ID[row["label_name"]],
            "source": "rim_one",
            "split": split,
            "group": f"rim:{row['sha256']}",
            "sha256": row["sha256"],
        })
    return rows


def decode(path: tf.Tensor, label: tf.Tensor, training: bool) -> tuple[tf.Tensor, tf.Tensor]:
    image = tf.io.decode_image(tf.io.read_file(path), channels=3, expand_animations=False)
    image.set_shape((None, None, 3))
    image = tf.image.convert_image_dtype(image, tf.float32)
    image = tf.image.resize_with_pad(image, SIZE, SIZE)
    if training:
        image = tf.image.random_flip_left_right(image, seed=SEED)
        image = tf.image.random_brightness(image, 0.12, seed=SEED)
        image = tf.image.random_contrast(image, 0.70, 1.30, seed=SEED)
        image = tf.image.random_saturation(image, 0.60, 1.40, seed=SEED)
        image = tf.image.random_hue(image, 0.05, seed=SEED)
        image = tf.clip_by_value(image, 0.0, 1.0)
    return image * 255.0, tf.cast(label, tf.int32)


def finite_dataset(rows: list[dict], training: bool = False) -> tf.data.Dataset:
    dataset = tf.data.Dataset.from_tensor_slices((
        np.asarray([row["path"] for row in rows]),
        np.asarray([row["label"] for row in rows], dtype=np.int32),
    ))
    if training:
        dataset = dataset.shuffle(len(rows), seed=SEED, reshuffle_each_iteration=True)
    return dataset.map(
        lambda path, label: decode(path, label, training),
        num_parallel_calls=tf.data.AUTOTUNE,
    ).batch(BATCH).prefetch(tf.data.AUTOTUNE)


def balanced_training_dataset(rows: list[dict]) -> tf.data.Dataset:
    class_streams = []
    for label in range(len(CLASSES)):
        source_streams = []
        sources = sorted({row["source"] for row in rows if row["label"] == label})
        if not sources:
            raise RuntimeError(f"La clase {CLASSES[label]} no tiene fuentes")
        for source in sources:
            group = [row for row in rows if row["label"] == label and row["source"] == source]
            dataset = tf.data.Dataset.from_tensor_slices((
                np.asarray([row["path"] for row in group]),
                np.full(len(group), label, dtype=np.int32),
            ))
            dataset = dataset.shuffle(len(group), seed=SEED, reshuffle_each_iteration=True).repeat()
            dataset = dataset.map(
                lambda path, value: decode(path, value, True),
                num_parallel_calls=tf.data.AUTOTUNE,
            )
            source_streams.append(dataset)
        class_streams.append(tf.data.Dataset.sample_from_datasets(
            source_streams,
            weights=[1.0 / len(source_streams)] * len(source_streams),
            seed=SEED,
        ))
    return tf.data.Dataset.sample_from_datasets(
        class_streams,
        weights=[0.25] * 4,
        seed=SEED,
    ).batch(BATCH).prefetch(tf.data.AUTOTUNE)


class BestMacroF1(keras.callbacks.Callback):
    def __init__(self, validation_dataset: tf.data.Dataset, labels: np.ndarray, path: Path):
        super().__init__()
        self.dataset = validation_dataset
        self.labels = labels
        self.path = path
        self.best = -1.0
        self.wait = 0
        self.history = []

    def on_epoch_end(self, epoch, logs=None):
        predicted = self.model.predict(self.dataset, verbose=0).argmax(axis=1)
        score = float(f1_score(self.labels, predicted, average="macro"))
        self.history.append({"epoch": int(epoch + 1), "validation_macro_f1": score})
        print("validation_macro_f1:", round(score, 4))
        if score > self.best + 0.002:
            self.best = score
            self.wait = 0
            self.model.save(self.path)
        else:
            self.wait += 1
            if self.wait >= 3:
                self.model.stop_training = True


def multiclass_metrics(rows: list[dict], probabilities: np.ndarray) -> dict:
    actual = np.asarray([row["label"] for row in rows], dtype=np.int32)
    predicted = probabilities.argmax(axis=1)
    present = sorted(set(actual.tolist()))
    return {
        "images": len(rows),
        "classes_present": [CLASSES[index] for index in present],
        "accuracy": float(accuracy_score(actual, predicted)),
        "macro_f1_present_classes": float(f1_score(actual, predicted, labels=present, average="macro")),
        "balanced_accuracy": float(balanced_accuracy_score(actual, predicted)),
        "confusion_matrix": confusion_matrix(actual, predicted, labels=list(range(4))).tolist(),
        "report": classification_report(
            actual,
            predicted,
            labels=list(range(4)),
            target_names=CLASSES,
            output_dict=True,
            zero_division=0,
        ),
    }


def binary_external_metrics(rows: list[dict], probabilities: np.ndarray, positive_index: int) -> dict:
    actual_class = np.asarray([row["label"] for row in rows], dtype=np.int32)
    actual = (actual_class == positive_index).astype(np.int32)
    predicted = (probabilities.argmax(axis=1) == positive_index).astype(np.int32)
    tn, fp, fn, tp = confusion_matrix(actual, predicted, labels=[0, 1]).ravel()
    return {
        "images": len(rows),
        "accuracy": float(accuracy_score(actual, predicted)),
        "auc": float(roc_auc_score(actual, probabilities[:, positive_index])),
        "confusion_matrix": [[int(tn), int(fp)], [int(fn), int(tp)]],
        "sensitivity": float(tp / (tp + fn)),
        "specificity": float(tn / (tn + fp)),
    }


def main() -> None:
    args = arguments()
    args.output_dir.mkdir(parents=True, exist_ok=True)
    ROOT.mkdir(parents=True, exist_ok=True)
    random.seed(SEED)
    np.random.seed(SEED)
    tf.random.set_seed(SEED)

    roots = download_sources()
    rows = prepare_kaggle_rows(args.output_dir, roots)
    rows.extend(prepare_idrid())
    rows.extend(prepare_rim_one())
    missing = [row["path"] for row in rows if not Path(row["path"]).is_file()]
    if missing:
        raise RuntimeError(f"Faltan {len(missing)} imágenes; primera: {missing[0]}")
    train_rows = [row for row in rows if row["split"] == "train"]
    validation_rows = [row for row in rows if row["split"] == "val"]
    print("Entrenamiento por fuente/clase:", Counter((row["source"], CLASSES[row["label"]]) for row in train_rows))
    print("Validación por fuente/clase:", Counter((row["source"], CLASSES[row["label"]]) for row in validation_rows))

    training_dataset = balanced_training_dataset(train_rows)
    validation_dataset = finite_dataset(validation_rows)
    validation_labels = np.asarray([row["label"] for row in validation_rows], dtype=np.int32)
    model_path = args.output_dir / "clasificador_multifuente_real_v2_candidato.keras"

    base = keras.applications.EfficientNetB0(
        include_top=False,
        weights="imagenet",
        input_shape=(SIZE, SIZE, 3),
        pooling="avg",
    )
    base.trainable = False
    inputs = keras.Input(shape=(SIZE, SIZE, 3), name="retinografia")
    x = keras.layers.RandomRotation(0.04, seed=SEED)(inputs)
    x = keras.layers.RandomZoom(0.08, seed=SEED)(x)
    x = base(x, training=False)
    x = keras.layers.Dropout(0.35, seed=SEED)(x)
    outputs = keras.layers.Dense(4, activation="softmax", name="clases")(x)
    model = keras.Model(inputs, outputs)
    model.compile(
        optimizer=keras.optimizers.Adam(8e-4),
        loss=keras.losses.SparseCategoricalCrossentropy(),
        metrics=["accuracy"],
    )
    selector = BestMacroF1(validation_dataset, validation_labels, model_path)
    model.fit(
        training_dataset,
        steps_per_epoch=350,
        validation_data=validation_dataset,
        epochs=8,
        callbacks=[selector],
        verbose=1,
    )

    model = keras.models.load_model(model_path)
    base = next(layer for layer in model.layers if isinstance(layer, keras.Model) and "efficientnet" in layer.name.lower())
    base.trainable = True
    for layer in base.layers[:-60]:
        layer.trainable = False
    for layer in base.layers:
        if isinstance(layer, keras.layers.BatchNormalization):
            layer.trainable = False
    model.compile(
        optimizer=keras.optimizers.Adam(1e-5),
        loss=keras.losses.SparseCategoricalCrossentropy(),
        metrics=["accuracy"],
    )
    model.fit(
        training_dataset,
        steps_per_epoch=350,
        validation_data=validation_dataset,
        epochs=8,
        callbacks=[selector],
        verbose=1,
    )
    model = keras.models.load_model(model_path)

    evaluations = {}
    for split in ("odir_test", "sylhet_test", "cuatro_test"):
        split_rows = [row for row in rows if row["split"] == split]
        if split_rows:
            probabilities = model.predict(finite_dataset(split_rows), verbose=1)
            evaluations[split] = multiclass_metrics(split_rows, probabilities)
    idrid_test = [row for row in rows if row["split"] == "idrid_test"]
    rim_test = [row for row in rows if row["split"] == "rim_test"]
    evaluations["idrid_external_dr"] = binary_external_metrics(
        idrid_test,
        model.predict(finite_dataset(idrid_test), verbose=1),
        LABEL_ID["diabetes"],
    )
    evaluations["rim_one_external_glaucoma"] = binary_external_metrics(
        rim_test,
        model.predict(finite_dataset(rim_test), verbose=1),
        LABEL_ID["glaucoma"],
    )

    odir_result = evaluations["odir_test"]
    idrid_result = evaluations["idrid_external_dr"]
    rim_result = evaluations["rim_one_external_glaucoma"]
    criteria = {
        "odir_macro_f1_at_least_0_60": odir_result["macro_f1_present_classes"] >= 0.60,
        "odir_cataract_recall_at_least_0_80": odir_result["report"]["catarata"]["recall"] >= 0.80,
        "idrid_dr_auc_at_least_0_80": idrid_result["auc"] >= 0.80,
        "idrid_dr_sensitivity_at_least_0_65": idrid_result["sensitivity"] >= 0.65,
        "idrid_specificity_at_least_0_80": idrid_result["specificity"] >= 0.80,
        "rim_glaucoma_auc_at_least_0_75": rim_result["auc"] >= 0.75,
        "rim_glaucoma_sensitivity_at_least_0_65": rim_result["sensitivity"] >= 0.65,
        "rim_specificity_at_least_0_75": rim_result["specificity"] >= 0.75,
    }
    approved_for_demo = all(criteria.values())
    tflite_path = args.output_dir / "clasificador_multifuente_real_v2_aprobado_demo.tflite"
    if approved_for_demo:
        converter = tf.lite.TFLiteConverter.from_keras_model(model)
        tflite_path.write_bytes(converter.convert())
    report = {
        "type": "retinai_cnn_v2_multisource_real_academic",
        "clinical_approval": False,
        "classes": CLASSES,
        "image_size": SIZE,
        "sources": ["ODIR original", "Sylhet depurado", "cuatro clases sin copias ODIR", "IDRiD", "RIM-ONE DL"],
        "training_images_unique": len(train_rows),
        "validation_images_unique": len(validation_rows),
        "training_counts": {
            f"{source}|{CLASSES[label]}": count
            for (source, label), count in Counter((row["source"], row["label"]) for row in train_rows).items()
        },
        "validation_history": selector.history,
        "evaluations": evaluations,
        "acceptance_criteria": criteria,
        "approved_for_academic_demo": approved_for_demo,
        "decision": (
            "APROBADO PARA DEMOSTRACION ACADEMICA; NO PARA USO CLINICO"
            if approved_for_demo
            else "RECHAZADO PARA DEMOSTRACION: NO SUPERA TODOS LOS CRITERIOS"
        ),
        "keras_candidate": str(model_path),
        "tflite_exported": str(tflite_path) if approved_for_demo else None,
        "note": "Inferencia real para demostración académica. No aprobado para decisiones clínicas.",
    }
    report_path = args.output_dir / "informe_clasificador_multifuente_real_v2.json"
    report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))
    print("Keras candidato:", model_path)
    print("Decisión:", report["decision"])
    print("TFLite:", tflite_path if approved_for_demo else "NO GENERADO")
    print("Informe:", report_path)


if __name__ == "__main__":
    main()
