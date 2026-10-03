<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

const PATRON_MIGRACION = '/^\d{3}_.+\.sql$/';

/**
 * Aplica en orden las migraciones SQL pendientes y registra cada versión.
 */
function aplicarMigraciones(PDO $conexion): array
{
    $conexion->exec(
        'CREATE TABLE IF NOT EXISTS migraciones_esquema (
            nombre VARCHAR(255) NOT NULL PRIMARY KEY,
            hash_archivo CHAR(64) NOT NULL,
            fecha_aplicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $archivos = glob(__DIR__ . '/*.sql') ?: [];
    sort($archivos, SORT_STRING);
    $aplicadas = [];

    foreach ($archivos as $rutaArchivo) {
        $nombreArchivo = basename($rutaArchivo);
        if (!preg_match(PATRON_MIGRACION, $nombreArchivo)) {
            continue;
        }

        $consultaExistencia = $conexion->prepare(
            'SELECT hash_archivo FROM migraciones_esquema WHERE nombre = :nombre LIMIT 1'
        );
        $consultaExistencia->execute(['nombre' => $nombreArchivo]);
        $hashRegistrado = $consultaExistencia->fetchColumn();
        $hashActual = hash_file('sha256', $rutaArchivo);

        if ($hashRegistrado !== false) {
            if (!hash_equals((string) $hashRegistrado, $hashActual)) {
                throw new RuntimeException("La migración aplicada {$nombreArchivo} fue modificada.");
            }
            continue;
        }

        $contenidoSql = file_get_contents($rutaArchivo);
        if ($contenidoSql === false || trim($contenidoSql) === '') {
            throw new RuntimeException("No se pudo leer la migración {$nombreArchivo}.");
        }

        try {
            $conexion->exec($contenidoSql);
            $registro = $conexion->prepare(
                'INSERT INTO migraciones_esquema (nombre, hash_archivo) VALUES (:nombre, :hash_archivo)'
            );
            $registro->execute(['nombre' => $nombreArchivo, 'hash_archivo' => $hashActual]);
            $aplicadas[] = $nombreArchivo;
        } catch (Throwable $error) {
            throw $error;
        }
    }

    return $aplicadas;
}

if (PHP_SAPI === 'cli') {
    $conexion = (new Database())->getConnection();
    $migracionesAplicadas = aplicarMigraciones($conexion);
    echo $migracionesAplicadas === []
        ? "No hay migraciones pendientes.\n"
        : 'Migraciones aplicadas: ' . implode(', ', $migracionesAplicadas) . "\n";
}
