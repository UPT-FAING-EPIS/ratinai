<?php

declare(strict_types=1);

class ServicioAlmacenamientoInstitucionalLocal
{
    private string $directorioBase;

    public function __construct(?string $directorioBase = null)
    {
        $this->directorioBase = $directorioBase ?? dirname(__DIR__) . '/almacenamiento/informes';
    }

    public function guardar(string $codigoPaciente, int $idInforme, string $contenidoPdf): string
    {
        $codigoSeguro = preg_replace('/[^A-Za-z0-9_-]/', '_', $codigoPaciente) ?: 'SIN_CODIGO';
        $directorioPaciente = $this->directorioBase . '/' . $codigoSeguro;
        if (!is_dir($directorioPaciente) && !mkdir($directorioPaciente, 0750, true) && !is_dir($directorioPaciente)) {
            throw new RuntimeException('No se pudo crear el directorio institucional local.');
        }

        $nombreArchivo = 'informe_' . $idInforme . '.pdf';
        $rutaCompleta = $directorioPaciente . '/' . $nombreArchivo;
        if (file_put_contents($rutaCompleta, $contenidoPdf, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el informe en el almacenamiento local.');
        }

        return 'almacenamiento/informes/' . $codigoSeguro . '/' . $nombreArchivo;
    }
}
