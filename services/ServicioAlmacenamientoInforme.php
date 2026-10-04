<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/IntegracionModel.php';
require_once __DIR__ . '/../models/InformeClinicoModel.php';
require_once __DIR__ . '/ServicioAlmacenamientoInstitucionalLocal.php';
require_once __DIR__ . '/ServicioOAuthDocumental.php';

/** Guarda una copia recuperable y sincroniza el PDF con el proveedor autorizado. */
class ServicioAlmacenamientoInforme
{
    public function guardar(
        int $idEstablecimiento,
        int $idInforme,
        string $codigoPaciente,
        string $contenidoPdf,
        array $rutaDocumental = []
    ): array
    {
        $modeloInforme = new InformeClinicoModel();
        $modeloIntegracion = new IntegracionModel();
        $sincronizacionLocal = $modeloInforme->crearSincronizacionLocal($idInforme, $idEstablecimiento);
        try {
            $rutaLocal = (new ServicioAlmacenamientoInstitucionalLocal())->guardar(
                $codigoPaciente,
                $idInforme,
                $contenidoPdf,
                $rutaDocumental
            );
            $modeloInforme->registrarArchivo($idInforme, $rutaLocal, hash('sha256', $contenidoPdf));
            $modeloInforme->completarSincronizacion((int) $sincronizacionLocal['id'], $rutaLocal);
        } catch (Throwable $error) {
            $modeloInforme->fallarSincronizacion((int) $sincronizacionLocal['id'], $error->getMessage());
            throw $error;
        }

        $configuracion = $modeloIntegracion->obtenerConfiguracion($idEstablecimiento);
        $proveedor = (string) ($configuracion['proveedor'] ?? 'local');
        if (!in_array($proveedor, ['google_drive', 'onedrive'], true) || (int) ($configuracion['activo'] ?? 0) !== 1) {
            return ['estado' => 'local', 'ruta' => $rutaLocal];
        }

        $sincronizacion = $modeloInforme->crearSincronizacion($idInforme, $idEstablecimiento, $proveedor);
        try {
            $servicio = new ServicioOAuthDocumental();
            $autorizacion = $servicio->descifrar((string) $configuracion['configuracion_cifrada']);
            $tokensVigentes = $servicio->renovarSiNecesario($proveedor, $autorizacion['tokens']);
            if ($tokensVigentes !== $autorizacion['tokens']) {
                $autorizacion['tokens'] = $tokensVigentes;
                $modeloIntegracion->actualizarConfiguracionCifrada($idEstablecimiento, $proveedor, $servicio->cifrar($autorizacion));
            }
            $rutaRemota = $servicio->guardarPdf(
                $proveedor,
                $tokensVigentes,
                $idEstablecimiento,
                $idInforme,
                $contenidoPdf,
                $codigoPaciente,
                $rutaDocumental
            );
            $modeloInforme->completarSincronizacion((int) $sincronizacion['id'], $rutaRemota);
            $modeloIntegracion->registrarEstado($idEstablecimiento, true, 'Informe sincronizado con ' . $proveedor . '.');
            return ['estado' => 'completada', 'ruta' => $rutaRemota];
        } catch (Throwable $error) {
            $modeloInforme->fallarSincronizacion((int) $sincronizacion['id'], $error->getMessage());
            $modeloIntegracion->registrarEstado($idEstablecimiento, false, $error->getMessage());
            return ['estado' => 'fallida', 'ruta' => $rutaLocal];
        }
    }
}
