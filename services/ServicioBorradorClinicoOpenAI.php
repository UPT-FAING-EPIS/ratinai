<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/ServicioBorradorClinicoLocal.php';

/** Redacta un borrador a partir de hechos clínicos sin enviar identificadores ni imágenes. */
class ServicioBorradorClinicoOpenAI
{
    private const URL_RESPUESTAS = 'https://api.openai.com/v1/responses';
    private const TIEMPO_MAXIMO_SEGUNDOS = 35;
    private const LONGITUD_MAXIMA_NARRATIVA = 3000;

    public function generar(array $analisis, array $controlesPrevios): string
    {
        $claveApi = trim((string) env_value('OPENAI_API_KEY', ''));
        if (!filter_var(env_value('REPORT_AI_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN) || $claveApi === '') {
            throw new RuntimeException('La generación del informe con OpenAI no está configurada.');
        }

        $hechos = [
            'ojo' => (string) $analisis['ojo'],
            'resultado_cnn' => (string) $analisis['resultado_principal'],
            'probabilidades_cnn' => [
                'normal' => (float) $analisis['probabilidad_normal'],
                'diabetes' => (float) $analisis['probabilidad_diabetes'],
                'glaucoma' => (float) $analisis['probabilidad_glaucoma'],
                'catarata' => (float) $analisis['probabilidad_catarata'],
            ],
            'controles_previos' => array_map(static function (array $control): array {
                return [
                    'resultado_cnn' => (string) $control['resultado_principal'],
                    'probabilidad_principal' => (float) $control['probabilidad_principal'],
                    'misma_version' => (string) $control['version_modelo'],
                ];
            }, array_slice($controlesPrevios, 0, 3)),
        ];
        $contenidoSolicitud = [
            'model' => (string) env_value('REPORT_AI_MODEL', 'gpt-5.6-terra'),
            'store' => false,
            'reasoning' => ['effort' => 'none'],
            'max_output_tokens' => 450,
            'instructions' => 'Eres un asistente de redacción clínica. Escribe en español un único párrafo referencial para revisión por un médico oftalmólogo. No diagnostiques, no prescribas, no atribuyas validación de retinografía o calidad a la CNN v1. No incluyas números, porcentajes ni datos identificables: la aplicación añade los hechos exactos por separado. No infieras progresión entre controles.',
            'input' => json_encode($hechos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'borrador_referencial',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => ['narrativa' => ['type' => 'string']],
                        'required' => ['narrativa'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        $encabezados = ['Authorization: Bearer ' . $claveApi, 'Content-Type: application/json'];
        $proyecto = trim((string) env_value('OPENAI_PROJECT_ID', ''));
        if ($proyecto !== '') {
            $encabezados[] = 'OpenAI-Project: ' . $proyecto;
        }
        $conexion = curl_init(self::URL_RESPUESTAS);
        curl_setopt_array($conexion, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($contenidoSolicitud, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => $encabezados,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIEMPO_MAXIMO_SEGUNDOS,
        ]);
        $respuestaCruda = curl_exec($conexion);
        $codigoHttp = (int) curl_getinfo($conexion, CURLINFO_RESPONSE_CODE);
        curl_close($conexion);
        if (!is_string($respuestaCruda) || $codigoHttp !== 200) {
            throw new RuntimeException('OpenAI no pudo generar el borrador. Compruebe la clave, el saldo y el modelo habilitado.');
        }

        $respuesta = json_decode($respuestaCruda, true, 512, JSON_THROW_ON_ERROR);
        $textoEstructurado = null;
        foreach (($respuesta['output'] ?? []) as $salida) {
            foreach (($salida['content'] ?? []) as $contenido) {
                if (($contenido['type'] ?? '') === 'output_text') {
                    $textoEstructurado = $contenido['text'] ?? null;
                }
            }
        }
        $estructura = is_string($textoEstructurado) ? json_decode($textoEstructurado, true) : null;
        $narrativa = trim((string) ($estructura['narrativa'] ?? ''));
        if ($narrativa === '' || mb_strlen($narrativa) > self::LONGITUD_MAXIMA_NARRATIVA || preg_match('/\d/u', $narrativa)) {
            throw new RuntimeException('El borrador generado no pasó la validación clínica de formato.');
        }

        $hechosVerificables = (new ServicioBorradorClinicoLocal())->generar($analisis, $controlesPrevios);
        return $hechosVerificables . "\n\nRedacción sugerida por IA (requiere revisión médica):\n" . $narrativa;
    }
}
