<?php
use PHPUnit\Framework\TestCase;

class RF08_TestUnitaria_InformacionModeloTest extends TestCase {
    private $vista;

    protected function setUp(): void {
        $this->vista = file_get_contents(__DIR__ . '/../../views/medico/modeloinfo.php');
    }

    public function testExponeLasMetricasOficialesDelModelo(): void {
        foreach (['95.4%', '93.8%', '96.1%', 'ODIR-5K', 'CNN · TF Lite', '224×224 px'] as $metrica) {
            $this->assertStringContainsString($metrica, $this->vista);
        }
    }

    public function testMuestraLaAdvertenciaDeUsoReferencial(): void {
        $this->assertStringContainsString('apoyo diagnóstico referencial', $this->vista);
        $this->assertStringContainsString('criterio clínico del médico especialista', $this->vista);
    }
}
