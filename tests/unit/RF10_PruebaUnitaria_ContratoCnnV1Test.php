<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../controllers/AnalisisController.php';

class RF10_PruebaUnitaria_ContratoCnnV1Test extends TestCase
{
    public function testAceptaSalidaCompletaDeCnnV2(): void
    {
        putenv('ANALYSIS_CONTRACT_VERSION=v2');
        $controlador = (new ReflectionClass(AnalisisController::class))->newInstanceWithoutConstructor();
        $normalizar = new ReflectionMethod(AnalisisController::class, 'normalizarSalidaModelo');
        $normalizar->setAccessible(true);
        $salida = $normalizar->invoke($controlador, [
            'modelo_version' => 'cnn-v2-academico',
            'tiempo_analisis' => 0.15,
            'validacion' => [
                'es_retinografia' => true,
                'probabilidad_retinografia' => 99.4,
                'es_evaluable' => true,
                'probabilidad_calidad' => 96.2,
                'motivo_rechazo' => null,
            ],
            'resultado_principal' => 'glaucoma',
            'probabilidad_principal' => 72.0,
            'probabilidades' => ['normal' => 12.0, 'diabetes' => 9.0, 'glaucoma' => 72.0, 'catarata' => 7.0],
            'alerta_anomalia' => true,
        ]);

        $this->assertSame(1, $salida['es_retinografia']);
        $this->assertSame(1, $salida['es_evaluable']);
        $this->assertSame('glaucoma', $salida['resultado_principal']);
        $this->assertSame(72.0, $salida['probabilidad_glaucoma']);
    }

    public function testRechazaSalidaV2ConProbabilidadClinicaAusente(): void
    {
        putenv('ANALYSIS_CONTRACT_VERSION=v2');
        $controlador = (new ReflectionClass(AnalisisController::class))->newInstanceWithoutConstructor();
        $normalizar = new ReflectionMethod(AnalisisController::class, 'normalizarSalidaModelo');
        $normalizar->setAccessible(true);
        $this->expectException(RuntimeException::class);
        $normalizar->invoke($controlador, [
            'modelo_version' => 'cnn-v2-academico',
            'tiempo_analisis' => 0.15,
            'validacion' => [
                'es_retinografia' => true,
                'probabilidad_retinografia' => 99.4,
                'es_evaluable' => true,
                'probabilidad_calidad' => 96.2,
                'motivo_rechazo' => null,
            ],
            'resultado_principal' => 'normal',
            'probabilidad_principal' => 82.0,
            'probabilidades' => ['normal' => 82.0, 'diabetes' => 11.0, 'glaucoma' => 7.0],
            'alerta_anomalia' => false,
        ]);
    }

    public function testAceptaRechazoDeContenidoSinProbabilidadesClinicas(): void
    {
        putenv('ANALYSIS_CONTRACT_VERSION=v2');
        $controlador = (new ReflectionClass(AnalisisController::class))->newInstanceWithoutConstructor();
        $normalizar = new ReflectionMethod(AnalisisController::class, 'normalizarSalidaModelo');
        $normalizar->setAccessible(true);
        $salida = $normalizar->invoke($controlador, [
            'modelo_version' => 'cnn-v2-academico',
            'tiempo_analisis' => 0.04,
            'validacion' => [
                'es_retinografia' => false,
                'probabilidad_retinografia' => 14.0,
                'es_evaluable' => false,
                'probabilidad_calidad' => 0.0,
                'motivo_rechazo' => 'La imagen no fue identificada como una retinografía.',
            ],
        ]);

        $this->assertSame(0, $salida['es_retinografia']);
        $this->assertNull($salida['resultado_principal']);
        $this->assertNull($salida['probabilidad_normal']);
    }
}
