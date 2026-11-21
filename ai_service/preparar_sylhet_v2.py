"""Marca conflictos y posibles copias ODIR en el inventario Sylhet."""

import argparse
import csv
from collections import defaultdict, Counter
from pathlib import Path


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("inventario", type=Path)
    parser.add_argument("salida", type=Path)
    args = parser.parse_args()
    with args.inventario.open(encoding="utf-8-sig", newline="") as archivo:
        filas = list(csv.DictReader(archivo))

    etiquetas_por_hash = defaultdict(set)
    for fila in filas:
        if fila["fuente"] == "sylhet":
            etiquetas_por_hash[fila["sha256"]].add(fila["etiqueta_carpeta"])
    conflictos = {h for h, etiquetas in etiquetas_por_hash.items() if len(etiquetas) > 1}

    indice = defaultdict(list)
    for fila in filas:
        if fila["fuente"] == "odir_original" and fila["etiqueta_carpeta"] == "Training Images":
            huella = int(fila["phash"], 16)
            for posicion in range(8):
                indice[(posicion, (huella >> (8 * posicion)) & 255)].append((huella, fila["archivo"]))

    salida = []
    for fila in filas:
        if fila["fuente"] != "sylhet":
            continue
        fila = fila.copy()
        huella = int(fila["phash"], 16)
        posibles = {
            (otra_huella, nombre)
            for posicion in range(8)
            for otra_huella, nombre in indice[(posicion, (huella >> (8 * posicion)) & 255)]
        }
        mejor = min(posibles, key=lambda par: (huella ^ par[0]).bit_count()) if posibles else None
        distancia = (huella ^ mejor[0]).bit_count() if mejor else 64

        if fila["sha256"] in conflictos:
            estado = "cuarentena_etiquetas_contradictorias"
        elif fila["etiqueta_carpeta"] == "cataract":
            estado = "revision_visual_catarata"
        elif distancia <= 4:
            estado = "posible_copia_odir_revisar"
        else:
            estado = "candidato_adicional_no_validado"

        fila.update(
            estado=estado,
            phash_distancia_odir=distancia if distancia <= 4 else "",
            odir_cercano=mejor[1] if distancia <= 4 else "",
        )
        salida.append(fila)

    args.salida.parent.mkdir(parents=True, exist_ok=True)
    with args.salida.open("w", encoding="utf-8-sig", newline="") as archivo:
        escritor = csv.DictWriter(archivo, fieldnames=salida[0].keys())
        escritor.writeheader()
        escritor.writerows(salida)
    print("Estados:", Counter(f["estado"] for f in salida))
    print("Candidatos por carpeta:", Counter(f["etiqueta_carpeta"] for f in salida if f["estado"] == "candidato_adicional_no_validado"))
    print("Archivo:", args.salida)


if __name__ == "__main__":
    main()
