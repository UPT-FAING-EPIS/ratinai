<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class ServicioPdfClinico
{
    public function generar(array $analisis, array $informe): string
    {
        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('chroot', realpath(__DIR__ . '/..'));

        $generador = new Dompdf($opciones);
        $generador->setPaper('A4', 'portrait');
        $generador->loadHtml($this->crearHtml($analisis, $informe), 'UTF-8');
        $generador->render();

        return $generador->output();
    }

    private function crearHtml(array $analisis, array $informe): string
    {
        $escapar = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $ojo = $analisis['ojo'] === 'derecho' ? 'Derecho' : 'Izquierdo';
        $probabilidad = number_format((float) $analisis['probabilidad_principal'], 1, ',', '.');
        $fechaAprobacion = $informe['fecha_aprobacion'] ?: date('Y-m-d H:i:s');
        $textoInforme = nl2br($escapar($informe['texto_editado']));

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
            @page { margin: 38px; }
            body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 11px; line-height: 1.55; }
            header { border-bottom: 3px solid #1a56db; padding-bottom: 14px; margin-bottom: 20px; }
            h1 { color: #1a56db; font-size: 24px; margin: 0; }
            h2 { color: #1a56db; font-size: 14px; margin: 22px 0 8px; }
            .subtitulo { color: #60708a; margin: 2px 0 0; }
            .datos { width: 100%; border-collapse: collapse; }
            .datos td { width: 50%; padding: 7px; border: 1px solid #dbe3ef; vertical-align: top; }
            .etiqueta { color: #64748b; font-size: 9px; text-transform: uppercase; }
            .resultado { background: #eef4ff; border-left: 4px solid #1a56db; padding: 12px; }
            .informe { border: 1px solid #dbe3ef; padding: 14px; min-height: 140px; }
            .aviso { margin-top: 25px; padding: 10px; background: #fff8e6; color: #784c00; font-size: 9px; }
            footer { position: fixed; bottom: -22px; left: 0; right: 0; color: #64748b; font-size: 8px; }
        </style></head><body>
        <header><h1>RetinAI</h1><p class="subtitulo">Informe clínico aprobado</p></header>
        <table class="datos"><tr>
            <td><span class="etiqueta">Paciente</span><br>' . $escapar($analisis['codigo_paciente'] ?: 'Sin código') . '</td>
            <td><span class="etiqueta">Ojo y captura</span><br>' . $ojo . ' · ' . $escapar($analisis['fecha_captura'] ?: $analisis['fecha_analisis']) . '</td>
        </tr><tr>
            <td><span class="etiqueta">Médico aprobador</span><br>' . $escapar($analisis['nombre_medico']) . '</td>
            <td><span class="etiqueta">Aprobación y versión</span><br>' . $escapar($fechaAprobacion) . ' · v' . (int) $informe['version'] . '</td>
        </tr></table>
        <h2>Salida original del modelo</h2>
        <div class="resultado"><strong>' . $escapar($analisis['resultado_principal']) . '</strong> · ' . $probabilidad . '%<br>
        Modelo ' . $escapar($analisis['version_modelo'] ?: 'no registrado') . '</div>
        <h2>Conclusión médica</h2><div class="informe">' . $textoInforme . '</div>
        <div class="aviso">La salida de inteligencia artificial es referencial. La conclusión fue revisada y aprobada por el médico identificado en este documento.</div>
        <footer>Informe #' . (int) $informe['id'] . ' · Análisis fuente #' . (int) $analisis['id'] . ' · Hash registrado por RetinAI</footer>
        </body></html>';
    }
}
