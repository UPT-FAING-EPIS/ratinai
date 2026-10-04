<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/** Gestiona OAuth, cifrado y documentos en la cuenta autorizada de un establecimiento. */
class ServicioOAuthDocumental
{
    private const TIEMPO_MAXIMO_SEGUNDOS = 30;
    private const CARPETA_INFORMES = 'RetinAI';
    private const MARGEN_VENCIMIENTO_SEGUNDOS = 60;
    private const CIFRADO = 'aes-256-gcm';
    private const TAMANO_NONCE = 12;
    private const TAMANO_ETIQUETA = 16;

    public function construirUrlAutorizacion(string $proveedor, string $estado): string
    {
        $cliente = $this->configuracionCliente($proveedor);
        $parametros = [
            'client_id' => $cliente['id'],
            'redirect_uri' => $cliente['retorno'],
            'response_type' => 'code',
            'scope' => $cliente['alcances'],
            'state' => $estado,
        ];
        if ($proveedor === 'google_drive') {
            $parametros['access_type'] = 'offline';
            $parametros['prompt'] = 'consent';
        }
        return $cliente['autorizacion'] . '?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
    }

    public function intercambiarCodigo(string $proveedor, string $codigo): array
    {
        $cliente = $this->configuracionCliente($proveedor);
        $parametros = [
            'client_id' => $cliente['id'],
            'client_secret' => $cliente['secreto'],
            'code' => $codigo,
            'redirect_uri' => $cliente['retorno'],
            'grant_type' => 'authorization_code',
        ];
        if ($proveedor === 'onedrive') {
            $parametros['scope'] = $cliente['alcances'];
        }
        $respuesta = $this->solicitar('POST', $cliente['token'], http_build_query($parametros), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        if (empty($respuesta['access_token']) || empty($respuesta['refresh_token'])) {
            throw new RuntimeException('El proveedor no otorgó acceso permanente. Vuelva a autorizar la cuenta.');
        }
        return $this->normalizarTokens($respuesta);
    }

    public function cifrar(array $configuracion): string
    {
        $nonce = random_bytes(self::TAMANO_NONCE);
        $etiqueta = '';
        $cifrado = openssl_encrypt(
            json_encode($configuracion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            self::CIFRADO,
            $this->obtenerClaveCifrado(),
            OPENSSL_RAW_DATA,
            $nonce,
            $etiqueta
        );
        if ($cifrado === false) {
            throw new RuntimeException('No se pudo proteger la autorización documental.');
        }
        return base64_encode($nonce . $etiqueta . $cifrado);
    }

    public function descifrar(string $contenido): array
    {
        $binario = base64_decode($contenido, true);
        if ($binario === false || strlen($binario) <= self::TAMANO_NONCE + self::TAMANO_ETIQUETA) {
            throw new RuntimeException('La autorización documental guardada es inválida.');
        }
        $nonce = substr($binario, 0, self::TAMANO_NONCE);
        $etiqueta = substr($binario, self::TAMANO_NONCE, self::TAMANO_ETIQUETA);
        $datos = substr($binario, self::TAMANO_NONCE + self::TAMANO_ETIQUETA);
        $texto = openssl_decrypt($datos, self::CIFRADO, $this->obtenerClaveCifrado(), OPENSSL_RAW_DATA, $nonce, $etiqueta);
        if ($texto === false) {
            throw new RuntimeException('No se pudo abrir la autorización documental.');
        }
        $configuracion = json_decode($texto, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($configuracion)) {
            throw new RuntimeException('La autorización documental guardada es inválida.');
        }
        return $configuracion;
    }

    public function obtenerCorreo(string $proveedor, array $tokens): string
    {
        $url = $proveedor === 'google_drive'
            ? 'https://www.googleapis.com/oauth2/v3/userinfo'
            : 'https://graph.microsoft.com/v1.0/me?$select=mail,userPrincipalName';
        $respuesta = $this->solicitar('GET', $url, null, [
            'Authorization: Bearer ' . $tokens['access_token'],
        ]);
        $correo = trim((string) ($respuesta['email'] ?? $respuesta['mail'] ?? $respuesta['userPrincipalName'] ?? ''));
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('No se pudo comprobar el correo de la cuenta documental.');
        }
        return $correo;
    }

    public function renovarSiNecesario(string $proveedor, array $tokens): array
    {
        if ((int) ($tokens['vence_en'] ?? 0) > time() + self::MARGEN_VENCIMIENTO_SEGUNDOS) {
            return $tokens;
        }
        $cliente = $this->configuracionCliente($proveedor);
        $parametros = [
            'client_id' => $cliente['id'],
            'client_secret' => $cliente['secreto'],
            'refresh_token' => (string) ($tokens['refresh_token'] ?? ''),
            'grant_type' => 'refresh_token',
        ];
        if ($proveedor === 'onedrive') {
            $parametros['scope'] = $cliente['alcances'];
        }
        $respuesta = $this->solicitar('POST', $cliente['token'], http_build_query($parametros), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        return $this->normalizarTokens($respuesta, (string) ($tokens['refresh_token'] ?? ''));
    }

    public function guardarPdf(
        string $proveedor,
        array $tokens,
        int $idEstablecimiento,
        int $idInforme,
        string $contenidoPdf,
        string $codigoPaciente = 'SIN_CODIGO',
        array $rutaDocumental = []
    ): string
    {
        $codigoSeguro = preg_replace('/[^A-Za-z0-9_-]/', '_', $codigoPaciente) ?: 'SIN_CODIGO';
        $nombreArchivo = 'informe_' . $codigoSeguro . '_' . $idInforme . '.pdf';
        $segmentos = [
            self::CARPETA_INFORMES,
            'Medicos',
            $this->normalizarSegmento((string) ($rutaDocumental['medico'] ?? 'Medico')),
            $this->normalizarSegmento((string) ($rutaDocumental['carpeta'] ?? 'Sin carpeta')),
            mb_strtolower(trim((string) ($rutaDocumental['ojo'] ?? ''))) === 'izquierdo' ? 'Ojo Izquierdo' : 'Ojo Derecho',
        ];
        $autorizacion = ['Authorization: Bearer ' . $tokens['access_token']];
        if ($proveedor === 'onedrive') {
            $this->asegurarRutaOneDrive($autorizacion, $segmentos);
            $ruta = '/' . implode('/', array_map('rawurlencode', array_merge($segmentos, [$nombreArchivo])));
            $url = 'https://graph.microsoft.com/v1.0/me/drive/root:' . $ruta . ':/content';
            $respuesta = $this->solicitar('PUT', $url, $contenidoPdf, array_merge($autorizacion, [
                'Content-Type: application/pdf',
            ]));
            return (string) ($respuesta['webUrl'] ?? throw new RuntimeException('OneDrive no confirmó el archivo.'));
        }

        $idCarpeta = $this->obtenerOCrearRutaGoogle($autorizacion, $segmentos);
        $nombreConsulta = $this->escaparConsultaGoogle($nombreArchivo);
        $consulta = rawurlencode("name = '{$nombreConsulta}' and '{$idCarpeta}' in parents and trashed = false");
        $existente = $this->solicitar('GET', 'https://www.googleapis.com/drive/v3/files?q=' . $consulta . '&fields=files(id,webViewLink)', null, $autorizacion);
        $idArchivo = $existente['files'][0]['id'] ?? null;
        if (is_string($idArchivo) && $idArchivo !== '') {
            $this->solicitar('PATCH', 'https://www.googleapis.com/upload/drive/v3/files/' . rawurlencode($idArchivo) . '?uploadType=media', $contenidoPdf, array_merge($autorizacion, [
                'Content-Type: application/pdf',
            ]));
        } else {
            $separador = 'retinai_' . bin2hex(random_bytes(8));
            $metadatos = json_encode(['name' => $nombreArchivo, 'parents' => [$idCarpeta], 'mimeType' => 'application/pdf'], JSON_THROW_ON_ERROR);
            $cuerpo = '--' . $separador . "\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n" . $metadatos
                . "\r\n--" . $separador . "\r\nContent-Type: application/pdf\r\n\r\n" . $contenidoPdf . "\r\n--" . $separador . "--\r\n";
            $creado = $this->solicitar('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink', $cuerpo, array_merge($autorizacion, [
                'Content-Type: multipart/related; boundary=' . $separador,
            ]));
            $idArchivo = $creado['id'] ?? null;
        }
        if (!is_string($idArchivo) || $idArchivo === '') {
            throw new RuntimeException('Google Drive no confirmó el archivo.');
        }
        return 'https://drive.google.com/file/d/' . rawurlencode($idArchivo) . '/view';
    }

    public function comprobarYPreparar(string $proveedor, array $tokens): void
    {
        $autorizacion = ['Authorization: Bearer ' . $tokens['access_token']];
        if ($proveedor === 'google_drive') {
            $this->obtenerOCrearRutaGoogle($autorizacion, [self::CARPETA_INFORMES, 'Medicos']);
            return;
        }
        $this->solicitar('GET', 'https://graph.microsoft.com/v1.0/me/drive', null, [
            'Authorization: Bearer ' . $tokens['access_token'],
        ]);
        $this->asegurarRutaOneDrive($autorizacion, [self::CARPETA_INFORMES, 'Medicos']);
    }

    private function obtenerOCrearRutaGoogle(array $autorizacion, array $segmentos): string
    {
        $idPadre = null;
        foreach ($segmentos as $segmento) {
            $nombre = $this->normalizarSegmento((string) $segmento);
            $nombreConsulta = $this->escaparConsultaGoogle($nombre);
            $filtroPadre = $idPadre === null ? " and 'root' in parents" : " and '{$idPadre}' in parents";
            $consulta = rawurlencode("name = '{$nombreConsulta}' and mimeType = 'application/vnd.google-apps.folder'{$filtroPadre} and trashed = false");
            $existente = $this->solicitar('GET', 'https://www.googleapis.com/drive/v3/files?q=' . $consulta . '&fields=files(id)', null, $autorizacion);
            $idCarpeta = $existente['files'][0]['id'] ?? null;
            if (!is_string($idCarpeta) || $idCarpeta === '') {
                $metadatos = ['name' => $nombre, 'mimeType' => 'application/vnd.google-apps.folder'];
                if ($idPadre !== null) {
                    $metadatos['parents'] = [$idPadre];
                }
                $carpeta = $this->solicitar('POST', 'https://www.googleapis.com/drive/v3/files?fields=id', json_encode(
                    $metadatos,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ), array_merge($autorizacion, ['Content-Type: application/json']));
                $idCarpeta = $carpeta['id'] ?? null;
            }
            if (!is_string($idCarpeta) || $idCarpeta === '') {
                throw new RuntimeException('Google Drive no confirmó la carpeta documental.');
            }
            $idPadre = $idCarpeta;
        }
        if ($idPadre === null) {
            throw new RuntimeException('La ruta documental de Google Drive está vacía.');
        }
        return $idPadre;
    }

    private function asegurarRutaOneDrive(array $autorizacion, array $segmentos): void
    {
        $ruta = [];
        foreach ($segmentos as $segmento) {
            $nombre = $this->normalizarSegmento((string) $segmento);
            $urlHijos = $ruta === []
                ? 'https://graph.microsoft.com/v1.0/me/drive/root/children?$select=name,folder'
                : 'https://graph.microsoft.com/v1.0/me/drive/root:/' . implode('/', array_map('rawurlencode', $ruta)) . ':/children?$select=name,folder';
            $hijos = $this->solicitar('GET', $urlHijos, null, $autorizacion);
            $existe = false;
            foreach (($hijos['value'] ?? []) as $hijo) {
                if (isset($hijo['folder']) && mb_strtolower((string) ($hijo['name'] ?? '')) === mb_strtolower($nombre)) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $urlCrear = $ruta === []
                    ? 'https://graph.microsoft.com/v1.0/me/drive/root/children'
                    : 'https://graph.microsoft.com/v1.0/me/drive/root:/' . implode('/', array_map('rawurlencode', $ruta)) . ':/children';
                $this->solicitar('POST', $urlCrear, json_encode([
                    'name' => $nombre,
                    'folder' => new stdClass(),
                    '@microsoft.graph.conflictBehavior' => 'fail',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), array_merge($autorizacion, ['Content-Type: application/json']));
            }
            $ruta[] = $nombre;
        }
    }

    private function normalizarSegmento(string $segmento): string
    {
        $segmento = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '-', trim($segmento)) ?? '';
        $segmento = preg_replace('/\s+/u', ' ', $segmento) ?? '';
        return mb_substr(trim($segmento, " .-"), 0, 100) ?: 'Sin nombre';
    }

    private function escaparConsultaGoogle(string $valor): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $valor);
    }

    private function normalizarTokens(array $respuesta, string $renovacionAnterior = ''): array
    {
        if (empty($respuesta['access_token'])) {
            throw new RuntimeException('El proveedor no entregó un token de acceso válido.');
        }
        return [
            'access_token' => (string) $respuesta['access_token'],
            'refresh_token' => (string) ($respuesta['refresh_token'] ?? $renovacionAnterior),
            'vence_en' => time() + (int) ($respuesta['expires_in'] ?? 3600),
        ];
    }

    private function obtenerClaveCifrado(): string
    {
        $claveConfigurada = trim((string) env_value('DOCUMENT_TOKEN_ENCRYPTION_KEY', ''));
        if ($claveConfigurada !== '') {
            $binario = base64_decode($claveConfigurada, true);
            if ($binario === false || strlen($binario) !== 32) {
                throw new RuntimeException('DOCUMENT_TOKEN_ENCRYPTION_KEY debe contener 32 bytes en base64.');
            }
            return $binario;
        }
        $secretoExistente = trim((string) env_value('ANALYSIS_API_KEY', ''));
        if ($secretoExistente === '') {
            throw new RuntimeException('Falta la clave de cifrado de autorizaciones documentales.');
        }
        return hash_hkdf('sha256', $secretoExistente, 32, 'RetinAI OAuth documentos v1');
    }

    private function configuracionCliente(string $proveedor): array
    {
        if ($proveedor === 'google_drive') {
            $prefijo = 'GOOGLE_DRIVE';
            $autorizacion = 'https://accounts.google.com/o/oauth2/v2/auth';
            $token = 'https://oauth2.googleapis.com/token';
            $alcances = 'openid email https://www.googleapis.com/auth/drive.file';
            $retornoPredeterminado = APP_URL . '/controllers/AutorizacionGoogleDriveController.php';
        } elseif ($proveedor === 'onedrive') {
            $prefijo = 'MICROSOFT_ONEDRIVE';
            $inquilino = (string) env_value('MICROSOFT_ONEDRIVE_TENANT', 'common');
            $autorizacion = 'https://login.microsoftonline.com/' . rawurlencode($inquilino) . '/oauth2/v2.0/authorize';
            $token = 'https://login.microsoftonline.com/' . rawurlencode($inquilino) . '/oauth2/v2.0/token';
            $alcances = 'openid profile email offline_access User.Read Files.ReadWrite';
            $retornoPredeterminado = APP_URL . '/controllers/AutorizacionOneDriveController.php';
        } else {
            throw new InvalidArgumentException('Proveedor documental no admitido.');
        }
        $id = trim((string) env_value($prefijo . '_CLIENT_ID', ''));
        $secreto = trim((string) env_value($prefijo . '_CLIENT_SECRET', ''));
        $retorno = trim((string) env_value($prefijo . '_REDIRECT_URI', $retornoPredeterminado));
        if ($id === '' || $secreto === '' || $retorno === '') {
            throw new RuntimeException('Faltan credenciales OAuth de ' . $proveedor . '.');
        }
        return compact('id', 'secreto', 'retorno', 'autorizacion', 'token', 'alcances');
    }

    private function solicitar(string $metodo, string $url, ?string $cuerpo, array $encabezados): array
    {
        $conexion = curl_init($url);
        curl_setopt_array($conexion, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $encabezados,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIEMPO_MAXIMO_SEGUNDOS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($cuerpo !== null) {
            curl_setopt($conexion, CURLOPT_POSTFIELDS, $cuerpo);
        }
        $respuestaCruda = curl_exec($conexion);
        $codigoHttp = (int) curl_getinfo($conexion, CURLINFO_RESPONSE_CODE);
        curl_close($conexion);
        if (!is_string($respuestaCruda) || $codigoHttp < 200 || $codigoHttp >= 300) {
            throw new RuntimeException('La cuenta documental rechazó la operación (HTTP ' . $codigoHttp . '). Revise la autorización y los permisos.');
        }
        $respuesta = json_decode($respuestaCruda, true);
        return is_array($respuesta) ? $respuesta : [];
    }
}
