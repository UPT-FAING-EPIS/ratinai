<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../controllers/AnalisisController.php';

class RF10_PruebaUnitaria_ContratoCnnV1Test extends TestCase
{
    public function testAceptaSalidaRealV1SinInventarValidaciones(): void
    {
        $controlador = (new ReflectionClass(AnalisisController::class))->newInstanceWithoutConstructor();
        $normalizar = new ReflectionMethod(AnalisisController::class, 'normalizarSalidaModelo');
        $normalizar->setAccessible(true);
        $salida = $normalizar->invoke($controlador, [
            'resultado_principal' => 'normal',
            'probabilidad_principal' => 82.0,
            'probabilidades' => ['normal' => 82.0, 'diabetes' => 11.0, 'glaucoma' => 7.0, 'catarata' => 4.0],
            'alerta_anomalia' => false,
            'modelo_version' => '1.0',
            'tiempo_analisis' => 0.15,
        ]);

        $this->assertNull($salida['es_retinografia']);
        $this->assertNull($salida['es_evaluable']);
        $this->assertSame('normal', $salida['resultado_principal']);
        $this->assertSame(11.0, $salida['probabilidad_diabetes']);
    }

    public function testRechazaSalidaV1ConProbabilidadAusente(): void
    {
        $controlador = (new ReflectionClass(AnalisisController::class))->newInstanceWithoutConstructor();
        $normalizar = new ReflectionMethod(AnalisisController::class, 'normalizarSalidaModelo');
        $normalizar->setAccessible(true);
        $this->expectException(RuntimeException::class);
        $normalizar->invoke($controlador, [
            'resultado_principal' => 'normal',
            'probabilidad_principal' => 82.0,
            'probabilidades' => ['normal' => 82.0, 'diabetes' => 11.0, 'glaucoma' => 7.0],
            'alerta_anomalia' => false,
            'modelo_version' => '1.0',
            'tiempo_analisis' => 0.15,
        ]);
    }
}
