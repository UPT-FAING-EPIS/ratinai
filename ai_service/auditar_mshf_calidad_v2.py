"""Audita las 1.302 imágenes MSHF y sus tres anotadores de calidad."""

import csv
import hashlib
import io
import json
import zipfile
from collections import Counter
from pathlib import Path, PurePosixPath

from openpyxl import load_workbook
from PIL import Image, UnidentifiedImageError


BASE = Path(__file__).resolve().parent
ZIP = BASE / "data" / "MSHF_dataset_2.zip"
SALIDA = BASE / "data" / "mshf_manifest_calidad.csv"
RESUMEN = BASE / "data" / "mshf_auditoria.json"

with zipfile.ZipFile(ZIP) as z:
    libro = load_workbook(io.BytesIO(z.read("MSHF dataset 2.0/Individual_scores.xlsx.xlsx")),
                           read_only=True, data_only=True)
    originales = {
        PurePosixPath(n).name: n for n in z.namelist()
        if n.startswith("MSHF dataset 2.0/Original/") and n.lower().endswith(".jpg")
    }
    particiones = {
        PurePosixPath(n).name: n.split("/")[2] for n in z.namelist()
        if n.startswith("MSHF dataset 2.0/AI-use/") and n.lower().endswith(".jpg")
    }
    if len(originales) != 1302 or len(particiones) != 1300:
        raise ValueError("La estructura de MSHF no coincide con la versión auditada")
    filas = []
    formatos = Counter()
    errores_decodificacion = []
    for fila in list(libro.active.values)[2:]:
        nombre = fila[0]
        if not nombre:
            continue
        if nombre not in originales:
            raise ValueError(f"No existe imagen original: {nombre}")
        votos = [fila[i] for i in (4, 9, 14)]
        if any(v not in (0, 1) for v in votos):
            raise ValueError(f"Votos de calidad inválidos: {nombre}: {votos}")
        etiqueta = int(sum(votos) >= 2)
        ruta = originales[nombre]
        contenido = z.read(ruta)
        try:
            with Image.open(io.BytesIO(contenido)) as imagen:
                formato = imagen.format
                imagen.load()
            formatos[formato] += 1
        except (UnidentifiedImageError, OSError) as error:
            formato = "invalido"
            errores_decodificacion.append({"archivo": nombre, "error": str(error)})
        filas.append({
            "archivo": nombre,
            "ruta_zip": ruta,
            "fuente": ruta.split("/")[2],
            "particion_publicada": particiones.get(nombre, "sin_particion_publicada"),
            "calidad_evaluable": etiqueta,
            "acuerdo_unanime": int(len(set(votos)) == 1),
            "votos": "".join(map(str, votos)),
            "sha256": hashlib.sha256(contenido).hexdigest(),
            "formato_real": formato,
        })

if len(filas) != 1302 or len({r["archivo"] for r in filas}) != 1302:
    raise ValueError("Etiquetas faltantes o duplicadas")
SALIDA.parent.mkdir(parents=True, exist_ok=True)
with SALIDA.open("w", encoding="utf-8", newline="") as archivo:
    escritor = csv.DictWriter(archivo, fieldnames=list(filas[0]))
    escritor.writeheader()
    escritor.writerows(filas)
resumen = {
    "imagenes": len(filas),
    "calidad_evaluable": dict(Counter(r["calidad_evaluable"] for r in filas)),
    "fuente": dict(Counter(r["fuente"] for r in filas)),
    "particion_publicada": dict(Counter(r["particion_publicada"] for r in filas)),
    "acuerdo_unanime": sum(r["acuerdo_unanime"] for r in filas),
    "sha_unicos": len({r["sha256"] for r in filas}),
    "formatos_reales": dict(formatos),
    "errores_decodificacion": errores_decodificacion,
    "sin_particion_publicada": [r["archivo"] for r in filas if r["particion_publicada"] == "sin_particion_publicada"],
}
RESUMEN.write_text(json.dumps(resumen, ensure_ascii=False, indent=2), encoding="utf-8")
print(json.dumps(resumen, ensure_ascii=False, indent=2))
