<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/EstablecimientoModel.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../config/session_guard.php';

class EstablecimientoController {
    private EstablecimientoModel $model;

    public function __construct() {
        $this->model = new EstablecimientoModel();
    }

    private function baseUrl(): string {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $parts = explode('/', rtrim($scriptDir, '/'));
        array_pop($parts);
        $root = implode('/', $parts);
        return ($root === '' ? '/' : $root . '/');
    }

    private function redirect(string $path): void {
        header("Location: " . $this->baseUrl() . ltrim($path, '/'));
        exit();
    }

    /**
     * RF-04: Actualiza los datos de un establecimiento y notifica al titular.
     */
    public function updateEstablecimiento(): void {
        require_role('SAD');

        $id  = (int)($_POST['id_establecimiento'] ?? 0);
        if ($id === 0) {
            $this->redirect('views/superadmin/Establecimientos.php');
        }

        $nombre    = trim($_POST['nombre']    ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $tipo      = trim($_POST['tipo']      ?? '');
        $ruc       = trim($_POST['ruc']       ?? '');
        $latitudRaw = trim($_POST['latitud'] ?? '');
        $longitudRaw = trim($_POST['longitud'] ?? '');

        if (empty($nombre)) {
            $_SESSION['est_error']   = 'El nombre del establecimiento es obligatorio.';
            $_SESSION['est_id_back'] = $id;
            $this->redirect("views/superadmin/detalles_establecimientos.php?id={$id}");
        }
        if (($latitudRaw === '') !== ($longitudRaw === '')) {
            $_SESSION['est_error'] = 'Registre ambas coordenadas o deje ambos campos vacíos.';
            $this->redirect("views/superadmin/detalles_establecimientos.php?id={$id}");
        }
        $latitud = $longitud = null;
        if ($latitudRaw !== '') {
            if (!is_numeric($latitudRaw) || !is_numeric($longitudRaw)
                || (float)$latitudRaw < -90 || (float)$latitudRaw > 90
                || (float)$longitudRaw < -180 || (float)$longitudRaw > 180) {
                $_SESSION['est_error'] = 'Ingrese coordenadas válidas: latitud entre -90 y 90 y longitud entre -180 y 180.';
                $this->redirect("views/superadmin/detalles_establecimientos.php?id={$id}");
            }
            $latitud = (float) $latitudRaw;
            $longitud = (float) $longitudRaw;
        }

        try {
            $this->model->update($id, $nombre, $direccion, $tipo, $ruc, $latitud, $longitud, true);

            // Notificar al titular del establecimiento
            $admins = $this->model->getAdminByEstablecimiento($id);
            $notificados = 0;
            foreach ($admins as $adm) {
                if (MailService::sendEstablecimientoActualizado(
                    $adm['correo'],
                    $adm['nombre'],
                    $nombre
                )) $notificados++;
            }

            $_SESSION['est_success'] = $notificados > 0
                ? "Establecimiento actualizado y notificación enviada a {$notificados} responsable(s)."
                : 'Establecimiento actualizado. No se pudo enviar la notificación: revise la configuración SMTP y el correo del responsable.';
        } catch (Exception $e) {
            $_SESSION['est_error'] = 'Error al actualizar: ' . $e->getMessage();
        }

        $this->redirect("views/superadmin/detalles_establecimientos.php?id={$id}");
    }
}

// ── Router ────────────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new EstablecimientoController();
    switch ($_GET['action']) {
        case 'update': $controller->updateEstablecimiento(); break;
    }
}
