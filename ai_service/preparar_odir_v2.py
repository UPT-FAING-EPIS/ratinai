"""Prepara un manifiesto auditable de imágenes ODIR para RetinAI CNN v2.

No interpreta imágenes. Solo agrupa palabras diagnósticas exactas por ojo;
las etiquetas resultantes son provisionales hasta la revisión del conjunto.
"""

import argparse
import csv
import re
from collections import Counter
from pathlib import Path


ETIQUETAS = {
    "normal fundus": "normal",
    "cataract": "catarata",
    "glaucoma": "glaucoma",
    "diabetic retinopathy": "retinopatia_diabetica",
    "mild nonproliferative retinopathy": "retinopatia_diabetica",
    "moderate non proliferative retinopathy": "retinopatia_diabetica",
    "severe nonproliferative retinopathy": "retinopatia_diabetica",
    "proliferative diabetic retinopathy": "retinopatia_diabetica",
    "severe proliferative diabetic retinopathy": "retinopatia_diabetica",
}


def leer_csv(ruta):
    with ruta.open(encoding="utf-8-sig", newline="") as archivo:
        return list(csv.DictReader(archivo))


def preparar(por_ojo, inventario):
    imagenes = [
        fila for fila in inventario
        if fila["fuente"] == "odir_original"
        and fila["etiqueta_carpeta"] == "Training Images"
    ]
    por_nombre = {fila["archivo"]: fila for fila in imagenes}
    if len(por_nombre) != 7000 or len(imagenes) != 7000:
        raise ValueError("El inventario ODIR no contiene 7.000 nombres únicos de entrenamiento")
    if len(por_ojo) != 7000 or len({fila["archivo"] for fila in por_ojo}) != 7000:
        raise ValueError("Las anotaciones por ojo no contienen 7.000 nombres únicos")

    salida = []
    for fila in por_ojo:
        nombre = fila["archivo"]
        imagen = por_nombre.get(nombre)
        if imagen is None or fila["_merge"] != "both":
            raise ValueError(f"Imagen sin vínculo válido: {nombre}")
        if str(fila["id_paciente"]) != imagen["id_paciente_sugerido"]:
            raise ValueError(f"ID de paciente inconsistente: {nombre}")

        palabras = [
            parte.strip().lower()
            for parte in re.split(r"[，,;]", fila["diagnostico_texto_ojo"])
            if parte.strip()
        ]
        clases = {ETIQUETAS[palabra] for palabra in palabras if palabra in ETIQUETAS}
        otros = [palabra for palabra in palabras if palabra not in ETIQUETAS]
        if len(clases) == 1 and not otros:
            estado = "candidato_clase_unica"
            etiqueta = next(iter(clases))
        elif not clases:
            estado = "fuera_de_cuatro_clases_o_sin_confirmar"
            etiqueta = ""
        else:
            estado = "diagnostico_mixto_o_artefacto"
            etiqueta = ""

        salida.append({
            "archivo": nombre,
            "id_paciente": fila["id_paciente"],
            "ojo": fila["ojo"],
            "diagnostico_texto_ojo": fila["diagnostico_texto_ojo"],
            "etiqueta_provisional": etiqueta,
            "estado": estado,
            "otros_terminos": " | ".join(otros),
            "etiquetas_paciente_N_D_G_C": "".join(fila[c] for c in "NDGC"),
            "sha256": imagen["sha256"],
            "ancho": imagen["ancho"],
            "alto": imagen["alto"],
            "brillo_medio": imagen["brillo_medio"],
            "ruta_colab": imagen["ruta"],
        })
    return salida


def main():
    analizador = argparse.ArgumentParser()
    analizador.add_argument("por_ojo", type=Path)
    analizador.add_argument("inventario", type=Path)
    analizador.add_argument("salida", type=Path)
    args = analizador.parse_args()
    filas = preparar(leer_csv(args.por_ojo), leer_csv(args.inventario))
    args.salida.parent.mkdir(parents=True, exist_ok=True)
    with args.salida.open("w", encoding="utf-8-sig", newline="") as archivo:
        escritor = csv.DictWriter(archivo, fieldnames=filas[0].keys())
        escritor.writeheader()
        escritor.writerows(filas)
    print("Imágenes:", len(filas))
    for (estado, etiqueta), cantidad in sorted(Counter((f["estado"], f["etiqueta_provisional"]) for f in filas).items()):
        print(estado, etiqueta or "-", cantidad)
    print("Manifiesto:", args.salida)


if __name__ == "__main__":
    main()
