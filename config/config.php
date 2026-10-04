<?php

/** Carga variables locales sin requerir dependencias adicionales. */
function load_environment_file($path) {
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name !== '' && getenv($name) === false) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

function env_value($name, $default = null) {
    $value = getenv($name);
    return $value === false ? $default : $value;
}

load_environment_file(__DIR__ . '/../.env');

class Database {
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = env_value('DB_HOST', '127.0.0.1');
        $this->port = env_value('DB_PORT', '3306');
        $this->dbname = env_value('DB_DATABASE', 'railway');
        $this->username = env_value('DB_USERNAME', 'root');
        $this->password = env_value('DB_PASSWORD', '');
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('Error de conexión a MariaDB: ' . $exception->getMessage());
            throw new RuntimeException('No se pudo conectar a la base de datos. Revise las variables DB_* configuradas en el entorno.');
        }
        return $this->conn;
    }
}

define('ANALYSIS_AI_ENABLED', filter_var(env_value('ANALYSIS_AI_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN));
define('ANALYSIS_PROVIDER', (string) env_value('ANALYSIS_PROVIDER', 'remoto'));
define('APP_URL', rtrim((string) env_value('APP_URL', 'http://ratinai.local'), '/'));

/** Indica si el servicio CNN remoto tiene todos los secretos y certificados necesarios. */
function servicioAnalisisRemotoDisponible(): bool
{
    $rutaCertificado = dirname(__DIR__) . '/certs/retinai-ai-ca.crt';

    return ANALYSIS_AI_ENABLED
        && ANALYSIS_PROVIDER === 'remoto'
        && trim((string) env_value('ANALYSIS_API_URL', '')) !== ''
        && trim((string) env_value('ANALYSIS_API_KEY', '')) !== ''
        && is_readable($rutaCertificado);
}

define('SMTP_HOST', (string) env_value('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env_value('SMTP_PORT', '587'));
define('SMTP_USER', (string) env_value('SMTP_USER', ''));
define('SMTP_PASS', (string) env_value('SMTP_PASS', ''));
define('SMTP_FROM', (string) env_value('SMTP_FROM', ''));
define('SMTP_FROM_NAME', (string) env_value('SMTP_FROM_NAME', 'RetinAI'));
