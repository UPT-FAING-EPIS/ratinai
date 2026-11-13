<?php

declare(strict_types=1);

class ServicioBorradorClinicoLocal
{
    private const LIMITE_CONTROLES = 5;
    private const PROBABILIDADES = [
        'probabilidad_normal' => 'normal',
        'probabilidad_diabetes' => 'retinopatía diabética',
        'probabilidad_glaucoma' => 'glaucoma',
        'probabilidad_catarata' => 'catarata',
    ];

    /**
     * Genera un borrador reproducible sin enviar información clínica a terceros.
     */
    public function generar(array $analisis, array $controlesPrevios): string
    {
        $ojo = $analisis['ojo'] === 'derecho' ? 'derecho' : 'izquierdo';
        $fecha = $analisis['fecha_captura'] ?: $analisis['fecha_analisis'];
        $versionModelo = $analisis['version_modelo'] ?: 'no registrada';
        $resultado = $analisis['resultado_principal'];
        $probabilidad = number_format((float) $analisis['probabilidad_principal'], 1, ',', '.');

        $lineas = [
            'Retinografía del ojo ' . $ojo . ' capturada el ' . $fecha . '.',
            'Salida referencial de RetinAI: ' . $resultado . ' (' . $probabilidad . '%).',
            'Versión del modelo: ' . $versionModelo . '.',
        ];
        $distribucionActual = $this->describirProbabilidades($analisis);
        if ($distribucionActual !== '') {
            $lineas[] = 'Distribución actual de la CNN: ' . $distribucionActual . '.';
        }

        $controles = array_slice($controlesPrevios, 0, self::LIMITE_CONTROLES);
        if ($controles === []) {
            $lineas[] = 'No se encontraron controles previos comparables del mismo paciente, carpeta y ojo.';
        } else {
            $lineas[] = 'Controles previos comparables del mismo paciente, carpeta y ojo:';
            foreach ($controles as $control) {
                $probabilidadControl = number_format((float) $control['probabilidad_principal'], 1, ',', '.');
                $fechaControl = $control['fecha_captura'] ?? $control['fecha_analisis'];
                $detalleControl = $this->describirProbabilidades($control);
                $lineas[] = '- ' . $fechaControl . ': ' . $control['resultado_principal']
                    . ' (' . $probabilidadControl . '%), modelo ' . ($control['version_modelo'] ?: 'no registrado')
                    . ($detalleControl === '' ? '.' : '; distribución: ' . $detalleControl . '.');
            }
            $variaciones = $this->calcularVariaciones($analisis, $controles[0]);
            if ($variaciones !== []) {
                $lineas[] = 'Diferencia frente al control previo más reciente (puntos porcentuales): '
                    . implode('; ', $variaciones) . '.';
            }
            $lineas[] = 'Las diferencias entre controles requieren interpretación médica y no implican progresión automática.';
        }

        $lineas[] = '';
        $lineas[] = 'Conclusión médica editable:';
        $lineas[] = 'Correlacionar los hallazgos referenciales con la evaluación clínica y los antecedentes del paciente.';

        return implode("\n", $lineas);
    }

    private function calcularVariaciones(array $actual, array $previo): array
    {
        $variaciones = [];
        foreach (self::PROBABILIDADES as $campo => $etiqueta) {
            if (!array_key_exists($campo, $actual) || !array_key_exists($campo, $previo)
                || !is_numeric($actual[$campo]) || !is_numeric($previo[$campo])) {
                continue;
            }
            $diferencia = (float) $actual[$campo] - (float) $previo[$campo];
            $signo = $diferencia > 0 ? '+' : '';
            $variaciones[] = $etiqueta . ' ' . $signo . number_format($diferencia, 1, ',', '.');
        }

        return $variaciones;
    }

    private function describirProbabilidades(array $datos): string
    {
        $valores = [];
        foreach (self::PROBABILIDADES as $campo => $etiqueta) {
            if (array_key_exists($campo, $datos) && is_numeric($datos[$campo])) {
                $valores[] = $etiqueta . ' ' . number_format((float) $datos[$campo], 1, ',', '.') . '%';
            }
        }

        return implode(', ', $valores);
    }
}
