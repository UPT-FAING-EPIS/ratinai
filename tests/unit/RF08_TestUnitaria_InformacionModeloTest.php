<?php
use PHPUnit\Framework\TestCase;

class RF08_TestUnitaria_InformacionModeloTest extends TestCase {
    private $contenidoVista;

    protected function setUp(): void {
        $this->contenidoVista = file_get_contents(__DIR__ . '/../../views/medico/modeloinfo.php');
    }

    public function testNoPublicaMetricasClinicasSinEvidenciaVerificada(): void {
        $this->assertStringContainsString('Métricas clínicas verificadas', $this->contenidoVista);
        $this->assertStringContainsString('Pendientes', $this->contenidoVista);
        $this->assertStringNotContainsString('95.4%', $this->contenidoVista);
        $this->assertStringNotContainsString('ODIR-5K', $this->contenidoVista);
    }

    public function testExplicaLasLimitacionesRealesDeLaCnnV1(): void {
        $this->assertStringContainsString('No evaluado por CNN v1', $this->contenidoVista);
        $this->assertStringContainsString('No evaluada por CNN v1', $this->contenidoVista);
        $this->assertStringContainsString('el análisis permanece bloqueado', $this->contenidoVista);
        $this->assertStringContainsString('no está configurado', $this->contenidoVista);
        $this->assertStringContainsString('requiere revisión y conclusión del médico especialista', $this->contenidoVista);
    }
}
