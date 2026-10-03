<?php

declare(strict_types=1);

class ServicioBorradorClinicoLocal
{
    private const LIMITE_CONTROLES = 3;

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

        $controles = array_slice($controlesPrevios, 0, self::LIMITE_CONTROLES);
        if ($controles === []) {
            $lineas[] = 'No se encontraron controles previos comparables del mismo ojo.';
        } else {
            $lineas[] = 'Controles previos comparables:';
            foreach ($controles as $control) {
                $probabilidadControl = number_format((float) $control['probabilidad_principal'], 1, ',', '.');
                $lineas[] = '- ' . $control['fecha_analisis'] . ': ' . $control['resultado_principal']
                    . ' (' . $probabilidadControl . '%), modelo ' . ($control['version_modelo'] ?: 'no registrado') . '.';
            }
            $lineas[] = 'Las diferencias entre controles requieren interpretación médica y no implican progresión automática.';
        }

        $lineas[] = '';
        $lineas[] = 'Conclusión médica editable:';
        $lineas[] = 'Correlacionar los hallazgos referenciales con la evaluación clínica y los antecedentes del paciente.';

        return implode("\n", $lineas);
    }
}
