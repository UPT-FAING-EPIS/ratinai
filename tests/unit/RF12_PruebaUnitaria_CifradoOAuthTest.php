<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../services/ServicioOAuthDocumental.php';

class RF12_PruebaUnitaria_CifradoOAuthTest extends TestCase
{
    public function testGeneraAutorizacionConRetornoYPermisoDeArchivos(): void
    {
        $variables = [
            'GOOGLE_DRIVE_CLIENT_ID' => 'cliente-de-prueba',
            'GOOGLE_DRIVE_CLIENT_SECRET' => 'secreto-de-prueba',
            'GOOGLE_DRIVE_REDIRECT_URI' => 'https://retinai.example.org/controllers/AutorizacionGoogleDriveController.php',
        ];
        $anteriores = [];
        foreach ($variables as $nombre => $valor) {
            $anteriores[$nombre] = getenv($nombre);
            putenv($nombre . '=' . $valor);
        }
        try {
            $url = (new ServicioOAuthDocumental())->construirUrlAutorizacion('google_drive', 'estado-seguro');
            $parametros = [];
            parse_str((string) parse_url($url, PHP_URL_QUERY), $parametros);
            $this->assertSame($variables['GOOGLE_DRIVE_REDIRECT_URI'], $parametros['redirect_uri']);
            $this->assertSame('estado-seguro', $parametros['state']);
            $this->assertStringContainsString('drive.file', $parametros['scope']);
            $this->assertSame('offline', $parametros['access_type']);
        } finally {
            foreach ($anteriores as $nombre => $valor) {
                putenv($valor === false ? $nombre : $nombre . '=' . $valor);
            }
        }
    }

    public function testCifraTokensYDetectaAlteraciones(): void
    {
        $anterior = getenv('DOCUMENT_TOKEN_ENCRYPTION_KEY');
        putenv('DOCUMENT_TOKEN_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
        try {
            $servicio = new ServicioOAuthDocumental();
            $original = ['correo' => 'centro@example.org', 'tokens' => ['refresh_token' => 'secreto']];
            $cifrado = $servicio->cifrar($original);
            $this->assertStringNotContainsString('secreto', $cifrado);
            $this->assertSame($original, $servicio->descifrar($cifrado));
            $alterado = base64_decode($cifrado, true);
            $alterado[strlen($alterado) - 1] = chr(ord($alterado[strlen($alterado) - 1]) ^ 1);
            $this->expectException(RuntimeException::class);
            $servicio->descifrar(base64_encode($alterado));
        } finally {
            if ($anterior === false) {
                putenv('DOCUMENT_TOKEN_ENCRYPTION_KEY');
            } else {
                putenv('DOCUMENT_TOKEN_ENCRYPTION_KEY=' . $anterior);
            }
        }
    }
}
