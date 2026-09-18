# Servicio de inferencia RetinAI

Este directorio contiene el código de la API TFLite. Los artefactos del modelo no se versionan: se instalan en `/opt/retinai/ai_service/model/` en EC2.

La API escucha únicamente en `127.0.0.1:8000`. Nginx debe publicar HTTPS y reenviar `/api/` hacia ese puerto. La clave se configura solo en `/etc/retinai-ai.env` y en la variable `ANALYSIS_API_KEY` del App Service.
