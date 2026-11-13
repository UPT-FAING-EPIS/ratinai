<?php
require_once __DIR__ . "/../../config/session_guard.php";
require_once __DIR__ . "/../../config/config.php";
require_role("ADM");
$user = current_user();
$initials = get_initials($user["nombre"]);
$base = get_base_path();
$est_id = (int) ($user["establecimiento_id"] ?? 0);
$logout_url = $base . "controllers/AuthController.php?action=logout";

require_once __DIR__ . "/../../controllers/DoctorController.php";

$role_label = "Administrador";
$role_class = "role-adm";
$avatar_class = "avatar-adm";

try {
    // El controlador limita las solicitudes a los centros administrados.
    ["mis_establecimientos" => $mis_establecimientos, "est_nombre" => $est_nombre,
        "pendientes" => $pendientes] = DoctorController::datosSolicitudes((int) $user["id"], $est_id);
    $header_sub = $est_nombre;
} catch (Exception $ex) {
    $est_nombre = "";
    $pendientes = [];
    $header_sub = "";
}

// Acciones POST: aprobar / rechazar
$msg_ok = $msg_err = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_type"])) {
    try {
        $msg_ok = DoctorController::procesarSolicitud((int) $user["id"], $est_id,
            (int) ($_POST["target_id"] ?? 0), (string) $_POST["action_type"]);
        header(
            "Location: " . $_SERVER["PHP_SELF"] . "?ok=" . urlencode($msg_ok),
        );
        exit();
    } catch (Exception $ex) {
        $msg_err = "Error al procesar la acción.";
    }
}
if (isset($_GET["ok"])) {
    $msg_ok = htmlspecialchars($_GET["ok"]);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>RetinAI — Solicitudes de Registro</title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/views/admin/solicitud_registro.css">
</head>

<body>

    <?php require_once __DIR__ . "/../shared/header.php"; ?>

    <div class="app-shell">

        <?php require_once __DIR__ . "/../shared/sidebar.php"; ?>

        <main class="main-content">

            <?php if (
            $msg_ok
        ): ?><div class="alert-flash alert-ok" id="flash-msg"><?= $msg_ok ?></div><?php endif; ?>
            <?php if (
            $msg_err
        ): ?><div class="alert-flash alert-err"><?= $msg_err ?></div><?php endif; ?>

            <!-- Solicitudes pendientes -->
            <section class="content-section">
                <div class="section-header">
                    <h1 class="page-title">Solicitudes de registro</h1>
                    <p class="page-sub">Revise y apruebe o rechace solicitudes de médicos oftalmólogos.</p>
                </div>
                <div class="card">
                    <?php if (empty($pendientes)): ?>
                    <p class="empty-msg">No hay solicitudes pendientes.</p>
                    <?php else: ?>
                    <table class="data-table" id="tabla-pendientes">
                        <thead>
                            <tr>
                                <th>Médico</th>
                                <th>CMP</th>
                                <th>Especialidad</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendientes as $m): ?>
                            <tr id="row-<?= $m["id"] ?>">
                                <td>
                                    <strong><?= htmlspecialchars(
                                $m["nombre"],
                            ) ?></strong>
                                    <br>
                                    <span class="text-muted small"><?= htmlspecialchars(
                                $m["correo"],
                            ) ?></span>
                                </td>
                                <td class="mono"><?= htmlspecialchars(
                            $m["cmp"] ?? "—",
                        ) ?></td>
                                <td><?= htmlspecialchars(
                            $m["especialidad"] ?? "—",
                        ) ?></td>
                                <td>
                                    <span class="badge badge-pending">Pendiente</span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <form method="POST" class="ajuste-visualizacion-inline">
                                            <input type="hidden" name="action_type" value="aprobar">
                                            <input type="hidden" name="target_id" value="<?= $m[
                                        "id"
                                    ] ?>">
                                            <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                                        </form>
                                        <form method="POST" class="ajuste-visualizacion-inline">
                                            <input type="hidden" name="action_type" value="rechazar">
                                            <input type="hidden" name="target_id" value="<?= $m[
                                        "id"
                                    ] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-danger-outline">Rechazar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>

        </main>
    </div>

    <script src="<?= $base ?>assets/js/session.service.js"></script>
    <script>
        SessionService.init({
            timeout: 300000,
            loginUrl: '<?= htmlspecialchars(
    $base . "views/auth/login.php",
) ?>'
        });

        const flash = document.getElementById('flash-msg');
        if (flash) setTimeout(() => {
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 3000);
    </script>
</body>

</html>