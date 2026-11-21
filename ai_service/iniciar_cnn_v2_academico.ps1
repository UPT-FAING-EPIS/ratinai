$ErrorActionPreference = 'Stop'
$env:RETINAI_CLAVE_API = 'retinai-academico-local-2026'
$env:RETINAI_USO_ACADEMICO = 'true'
$env:RETINAI_DIRECTORIO_MODELOS = Join-Path $PSScriptRoot 'modelo_v2'

Set-Location -LiteralPath $PSScriptRoot
py -m uvicorn servidor_v2:aplicacion --host 127.0.0.1 --port 8000 --workers 1
