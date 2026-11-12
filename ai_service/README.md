# Servicio CNN de RetinAI

El servicio requiere una CNN TFLite con seis salidas ordenadas: probabilidad de retinografía, probabilidad de calidad evaluable y las clases `normal`, `diabetes`, `glaucoma` y `catarata`.

Archivos privados requeridos en `ai_service/modelo/`:

- `modelo_retinai.tflite`
- `informacion_modelo.json`

El JSON debe declarar `version`, `clases_clinicas`, `tamano_entrada`, `umbral_retinografia`, `umbral_evaluable` y `umbral_clinico`. El servicio se inicia con `uvicorn servidor:aplicacion` y exige `RETINAI_CLAVE_API`.

Una imagen rechazada por contenido o calidad no incluye probabilidades clínicas en la respuesta.

## Contrato preparado para CNN v2

`servidor_v2.py` implementa la secuencia de tres modelos TFLite independientes: `retinografia`, `calidad` y `clinico`. Cada uno tiene su tamaño de entrada y su archivo propio. Los dos primeros devuelven una probabilidad sigmoide (`[1,1]`); el clínico devuelve cuatro probabilidades (`[1,4]`) en el orden `normal`, `diabetes`, `glaucoma`, `catarata`. `POST /api/validar` ejecuta únicamente contenido y calidad; `POST /api/analizar` vuelve a comprobar ambos filtros y solo entonces ejecuta el clasificador. El campo de carga HTTP es `file`, igual que el que envía `AnalisisController.php`.

La configuración se lee de `modelo_v2/informacion_modelo.json` y los pesos de `modelo_v2/`, salvo que `RETINAI_DIRECTORIO_MODELOS` indique otra carpeta. Para uso médico exige `aprobado_para_uso: true`. Para una demostración académica permite `aprobado_para_uso: false` únicamente cuando el archivo declara `uso_academico: true` y el proceso define `RETINAI_USO_ACADEMICO=true`; la respuesta conserva una advertencia explícita. El servicio no arranca si faltan pesos o si las dimensiones no coinciden.

La configuración académica local añade `glaucoma_especialista`, un TFLite binario entrenado con RIM-ONE DL y evaluado en la partición separada por hospital. Se inicia con [iniciar_cnn_v2_academico.ps1](iniciar_cnn_v2_academico.ps1). La API queda limitada a `127.0.0.1:8000`; PHP usa `ANALYSIS_PROVIDER=local` y contrato `v2`. Los archivos siguen siendo experimentales y nunca se marcan como aprobados.

La prueba hospitalaria del especialista obtuvo AUC 0,7839, sensibilidad 0,6071 y especificidad 0,8390 sobre 174 imágenes (56 glaucoma, 118 normal). El entrenamiento reproducible está en [entrenar_glaucoma_rim_one_academico.py](entrenar_glaucoma_rim_one_academico.py). Estas cifras respaldan la exposición académica, no decisiones clínicas.
