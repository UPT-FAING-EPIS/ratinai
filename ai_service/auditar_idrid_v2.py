"""Audita IDRiD original por partición y detecta copias exactas conocidas."""

import csv
import hashlib
import io
import zipfile
from collections import Counter, defaultdict
from pathlib import Path


BASE = Path(__file__).parent
ARCHIVO = BASE / "data" / "idrid" / "B. Disease Grading.zip"
SALIDA = BASE / "data" / "idrid" / "manifest_idrid.csv"
FUENTES = Path("C:/Users/Equipo/Downloads/retinai_fuentes_v2.csv")


def main():
    hashes_anteriores = set()
    with FUENTES.open(encoding="utf-8-sig", newline="") as archivo:
        for fila in csv.DictReader(archivo):
            hashes_anteriores.add(fila["sha256"])

    with zipfile.ZipFile(ARCHIVO) as z:
        nombres = z.namelist()
        registros = []
        for particion, clave in [("entrenamiento", "Training"), ("prueba", "Testing")]:
            csv_archivo = next(n for n in nombres if n.endswith(f"{clave} Labels.csv"))
            filas = list(csv.DictReader(io.StringIO(z.read(csv_archivo).decode("utf-8-sig"))))
            for fila in filas:
                imagen = fila["Image name"] + ".jpg"
                candidatos = [
                    n for n in nombres
                    if n.endswith("/" + imagen) and f"{clave} Set/" in n and "Original Images" in n
                ]
                if len(candidatos) != 1:
                    raise ValueError(f"No se encontró una única imagen para {particion}: {imagen}")
                contenido = z.read(candidatos[0])
                sha = hashlib.sha256(contenido).hexdigest()
                registros.append({
                    "fuente": "IDRiD",
                    "particion_oficial": particion,
                    "imagen": imagen,
                    "ruta_zip": candidatos[0],
                    "grado_retinopatia": fila["Retinopathy grade"],
                    "riesgo_edema_macular": fila["Risk of macular edema "],
                    "sha256": sha,
                    "bytes": len(contenido),
                    "coincide_sha_fuentes_previas": int(sha in hashes_anteriores),
                })
    if len(registros) != 516:
        raise ValueError(f"Se esperaban 516 imágenes, hay {len(registros)}")
    grupos = defaultdict(list)
    for registro in registros:
        grupos[registro["sha256"]].append(registro)
    for grupo in grupos.values():
        grados = {r["grado_retinopatia"] for r in grupo}
        particiones = {r["particion_oficial"] for r in grupo}
        for registro in grupo:
            registro["duplicado_exacto_idrid"] = int(len(grupo) > 1)
            registro["conflicto_grado_idrid"] = int(len(grados) > 1)
            registro["cruza_particiones_idrid"] = int(len(particiones) > 1)
    with SALIDA.open("w", encoding="utf-8-sig", newline="") as archivo:
        escritor = csv.DictWriter(archivo, fieldnames=registros[0].keys())
        escritor.writeheader()
        escritor.writerows(registros)
    print("Imágenes:", len(registros))
    print("Por partición:", Counter(r["particion_oficial"] for r in registros))
    print("Por grado:", Counter((r["particion_oficial"], r["grado_retinopatia"]) for r in registros))
    print("SHA coincidentes con fuentes previas:", sum(r["coincide_sha_fuentes_previas"] for r in registros))
    print("Grupos SHA duplicados internos:", sum(len(g) > 1 for g in grupos.values()))
    print("Filas con grados contradictorios:", sum(r["conflicto_grado_idrid"] for r in registros))
    print("Filas duplicadas entre entrenamiento y prueba:", sum(r["cruza_particiones_idrid"] for r in registros))
    print("Manifiesto:", SALIDA)


if __name__ == "__main__":
    main()
