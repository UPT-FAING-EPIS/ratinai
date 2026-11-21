"""Asigna pacientes ODIR a entrenamiento, validación y prueba interna.

Busca una partición reproducible que aproxime las proporciones de las cuatro
clases candidatas. No cambia las etiquetas ni decide calidad de imagen.
"""

import csv
import random
from collections import Counter, defaultdict
from pathlib import Path


ENTRADA = Path(__file__).parent / "odir_manifest_v2.csv"
SALIDA = Path(__file__).parent / "odir_manifest_v2_split.csv"
PROPORCIONES = {"entrenamiento": 0.70, "validacion": 0.15, "prueba_interna": 0.15}
CLASES = ("normal", "retinopatia_diabetica", "glaucoma", "catarata")


def main():
    with ENTRADA.open(encoding="utf-8-sig", newline="") as archivo:
        lector = csv.DictReader(archivo)
        filas = list(lector)
        campos = lector.fieldnames + ["particion"]
    pacientes = defaultdict(Counter)
    for fila in filas:
        pacientes[fila["id_paciente"]]["total"] += 1
        if fila["estado"] == "candidato_clase_unica":
            pacientes[fila["id_paciente"]][fila["etiqueta_provisional"]] += 1
    ids = sorted(pacientes, key=int)
    total = sum((contador for contador in pacientes.values()), Counter())
    cantidades = {
        "entrenamiento": round(len(ids) * 0.70),
        "validacion": round(len(ids) * 0.15),
    }
    cantidades["prueba_interna"] = len(ids) - sum(cantidades.values())

    mejor = None
    for semilla in range(1000):
        orden = ids.copy()
        random.Random(semilla).shuffle(orden)
        partes = {}
        cursor = 0
        for nombre, cantidad in cantidades.items():
            partes[nombre] = orden[cursor:cursor + cantidad]
            cursor += cantidad
        conteos = {
            nombre: sum((pacientes[id_] for id_ in seleccion), Counter())
            for nombre, seleccion in partes.items()
        }
        puntuacion = 0.0
        for nombre, proporciones in PROPORCIONES.items():
            for clase in CLASES:
                esperado = total[clase] * proporciones
                puntuacion += ((conteos[nombre][clase] - esperado) / max(esperado, 1)) ** 2
        if mejor is None or puntuacion < mejor[0]:
            mejor = (puntuacion, semilla, partes, conteos)

    _, semilla, partes, conteos = mejor
    asignacion = {id_: nombre for nombre, seleccion in partes.items() for id_ in seleccion}
    if len(asignacion) != 3500 or len(asignacion) != len(ids):
        raise ValueError("La asignación no cubre exactamente los 3.500 pacientes")
    for fila in filas:
        fila["particion"] = asignacion[fila["id_paciente"]]
    with SALIDA.open("w", encoding="utf-8-sig", newline="") as archivo:
        escritor = csv.DictWriter(archivo, fieldnames=campos)
        escritor.writeheader()
        escritor.writerows(filas)
    print("Semilla elegida:", semilla)
    for nombre in PROPORCIONES:
        print(nombre, "pacientes", len(partes[nombre]), "ojos candidatos",
              {clase: conteos[nombre][clase] for clase in CLASES})
    print("Archivo:", SALIDA)


if __name__ == "__main__":
    main()
