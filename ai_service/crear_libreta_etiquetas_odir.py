"""Crea una copia de la libreta de auditoría para revisar etiquetas ODIR en Colab."""

import json
from pathlib import Path


base = Path(__file__).parent
origen = base / "RetinAI_CNN_V2_Depuracion.ipynb"
destino = base / "RetinAI_CNN_V2_Etiquetas_ODIR.ipynb"
notebook = json.loads(origen.read_text(encoding="utf-8"))


def celda(tipo, contenido):
    return {
        "cell_type": tipo,
        "metadata": {},
        "source": contenido.splitlines(keepends=True),
        **({"execution_count": None, "outputs": []} if tipo == "code" else {}),
    }


notebook["cells"].append(
    celda(
        "markdown",
        """## Verificación de las etiquetas oficiales de ODIR

Esta etapa enlaza cada imagen de entrenamiento con `full_df.csv` por nombre exacto de archivo. Comprueba cobertura, diagnósticos múltiples y separación de pacientes. La etiqueta del paciente no se convierte automáticamente en un diagnóstico de cada ojo.
""",
    )
)
notebook["cells"].append(
    celda(
        "code",
        """from pathlib import Path
import pandas as pd

odir_raiz = rutas["odir_original"]
metadatos = sorted(odir_raiz.rglob("full_df.csv"))
print("Metadatos encontrados:", [str(p) for p in metadatos])
if len(metadatos) != 1:
    raise RuntimeError("Se esperaba exactamente un full_df.csv en ODIR. Revisar rutas antes de seguir.")

oficial = pd.read_csv(metadatos[0])
print("Filas oficiales:", len(oficial))
print("Columnas:", oficial.columns.tolist())
display(oficial.head(3))
""",
    )
)
notebook["cells"].append(
    celda(
        "code",
        """if "filename" not in oficial.columns:
    raise RuntimeError("No existe la columna filename. Revisar el esquema mostrado arriba.")

oficial = oficial.copy()
oficial["archivo_join"] = oficial["filename"].astype(str).map(lambda x: Path(x).name)
conteo_metadata = oficial.groupby("archivo_join").size()
print("Nombres repetidos en metadatos:", int((conteo_metadata > 1).sum()))
print("Filas con nombre repetido:", int(conteo_metadata[conteo_metadata > 1].sum()))

odir_entrenamiento = revision.loc[
    revision["fuente"].eq("odir_original") &
    revision["etiqueta_carpeta"].eq("Training Images")
].copy()
odir_entrenamiento["archivo_join"] = odir_entrenamiento["archivo"].astype(str).map(lambda x: Path(x).name)

# Validar unicidad antes de unir; el join no debe multiplicar filas sin aviso.
oficial_unico = oficial.loc[~oficial["archivo_join"].duplicated(keep=False)].copy()
unido = odir_entrenamiento.merge(
    oficial_unico, on="archivo_join", how="left", indicator=True,
    validate="one_to_one", suffixes=("_imagen", "_oficial")
)
print("Imágenes de entrenamiento:", len(odir_entrenamiento))
print("Coincidencias únicas:", int(unido["_merge"].eq("both").sum()))
print("Sin coincidencia:", int(unido["_merge"].eq("left_only").sum()))
print("IDs de paciente en entrenamiento:", odir_entrenamiento["id_paciente_sugerido"].nunique())

odir_prueba = revision.loc[
    revision["fuente"].eq("odir_original") &
    revision["etiqueta_carpeta"].eq("Testing Images")
]
solapados = set(odir_entrenamiento["id_paciente_sugerido"]) & set(odir_prueba["id_paciente_sugerido"])
print("Pacientes presentes en entrenamiento y prueba:", len(solapados))

clases = [c for c in ["N", "D", "G", "C", "A", "H", "M", "O"] if c in unido.columns]
print("Columnas diagnósticas encontradas:", clases)
if clases:
    matriz = unido[clases].apply(pd.to_numeric, errors="coerce").fillna(0)
    print("Distribución de cantidad de diagnósticos por fila:")
    display(matriz.sum(axis=1).value_counts(dropna=False).sort_index().rename_axis("n_diagnosticos").to_frame("filas"))
    print("Conteo por categoría del metadato:")
    display(matriz.sum().astype(int).to_frame("filas"))

reporte = unido[["ruta", "archivo_join", "id_paciente_sugerido", "_merge"] + clases].copy()
reporte.to_csv("/content/retinai_odir_union.csv", index=False)
display(reporte.loc[reporte["_merge"].ne("both")].head(20))
print("Reporte guardado en /content/retinai_odir_union.csv")
""",
    )
)
notebook["cells"].append(
    celda(
        "code",
        """from google.colab import files
files.download("/content/retinai_odir_union.csv")
""",
    )
)

destino.write_text(json.dumps(notebook, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
print(destino)
