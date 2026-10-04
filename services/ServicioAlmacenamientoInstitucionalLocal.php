<?php

declare(strict_types=1);

class ServicioAlmacenamientoInstitucionalLocal
{
    private string $directorioBase;

    public function __construct(?string $directorioBase = null)
    {
        $this->directorioBase = $directorioBase ?? dirname(__DIR__) . '/almacenamiento/informes';
    }

    public function guardar(string $codigoPaciente, int $idInforme, string $contenidoPdf, array $rutaDocumental = []): string
    {
        $codigoSeguro = preg_replace('/[^A-Za-z0-9_-]/', '_', $codigoPaciente) ?: 'SIN_CODIGO';
        $segmentos = [
            'Medicos',
            $this->segmentoSeguro((string) ($rutaDocumental['medico'] ?? 'Medico')),
            $this->segmentoSeguro((string) ($rutaDocumental['carpeta'] ?? 'Sin carpeta')),
            $this->nombreOjo((string) ($rutaDocumental['ojo'] ?? '')),
        ];
        $directorioInforme = $this->directorioBase . '/' . implode('/', $segmentos);
        if (!is_dir($directorioInforme) && !mkdir($directorioInforme, 0750, true) && !is_dir($directorioInforme)) {
            throw new RuntimeException('No se pudo crear el directorio institucional local.');
        }

        $nombreArchivo = 'informe_' . $codigoSeguro . '_' . $idInforme . '.pdf';
        $rutaCompleta = $directorioInforme . '/' . $nombreArchivo;
        if (file_put_contents($rutaCompleta, $contenidoPdf, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el informe en el almacenamiento local.');
        }

        return 'almacenamiento/informes/' . implode('/', $segmentos) . '/' . $nombreArchivo;
    }

    private function segmentoSeguro(string $valor): string
    {
        $valor = trim($valor);
        $valor = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '-', $valor) ?? '';
        $valor = preg_replace('/\s+/u', ' ', $valor) ?? '';
        return trim($valor, " .-") ?: 'Sin nombre';
    }

    private function nombreOjo(string $ojo): string
    {
        return mb_strtolower(trim($ojo)) === 'izquierdo' ? 'Ojo Izquierdo' : 'Ojo Derecho';
    }
}
