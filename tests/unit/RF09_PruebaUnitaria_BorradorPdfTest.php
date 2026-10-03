<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../services/ServicioBorradorClinicoLocal.php';
require_once __DIR__ . '/../../services/ServicioPdfClinico.php';

class RF09_PruebaUnitaria_BorradorPdfTest extends TestCase
{
    public function testElBorradorDistingueSalidaReferencialYControlPrevio(): void
    {
        $analisis = [
            'ojo' => 'derecho',
            'fecha_captura' => '2026-09-29 10:00:00',
            'fecha_analisis' => '2026-09-29 10:01:00',
            'version_modelo' => '2.0',
            'resultado_principal' => 'normal',
            'probabilidad_principal' => 82.0,
        ];
        $controles = [[
            'fecha_analisis' => '2026-08-01 09:00:00',
            'resultado_principal' => 'normal',
            'probabilidad_principal' => 78.0,
            'version_modelo' => '1.9',
        ]];

        $texto = (new ServicioBorradorClinicoLocal())->generar($analisis, $controles);

        $this->assertStringContainsString('Salida referencial de RetinAI', $texto);
        $this->assertStringContainsString('Controles previos comparables', $texto);
        $this->assertStringContainsString('requieren interpretación médica', $texto);
    }

    public function testGeneraUnPdfAprobadoValido(): void
    {
        $analisis = [
            'id' => 1,
            'ojo' => 'derecho',
            'fecha_captura' => '2026-09-29 10:00:00',
            'fecha_analisis' => '2026-09-29 10:01:00',
            'codigo_paciente' => 'PAC-00001',
            'nombre_medico' => 'Médico de prueba',
            'resultado_principal' => 'normal',
            'probabilidad_principal' => 82.0,
            'version_modelo' => 'demostracion-local-no-clinica',
        ];
        $informe = [
            'id' => 2,
            'version' => 2,
            'fecha_aprobacion' => '2026-09-29 10:05:00',
            'texto_editado' => 'Conclusión médica revisada.',
        ];

        $contenido = (new ServicioPdfClinico())->generar($analisis, $informe);

        $this->assertStringStartsWith('%PDF-', $contenido);
        $this->assertGreaterThan(1000, strlen($contenido));
    }
}
