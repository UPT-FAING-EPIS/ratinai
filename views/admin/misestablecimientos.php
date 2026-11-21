<?php
require_once __DIR__ . "/../../config/session_guard.php";
require_once __DIR__ . "/../../config/config.php";
require_role("ADM");

$user = current_user();
$initials = get_initials($user["nombre"]);
$base = get_base_path();
$est_id = (int) ($user["establecimiento_id"] ?? 0);
$logout_url = $base . "controllers/AuthController.php?action=logout";

require_once __DIR__ . "/../../controllers/SolicitudController.php";

$role_label = "Administrador";
$role_class = "role-adm";
$avatar_class = "avatar-adm";

try {
    // El controlador agrupa centros, solicitudes y el contador del menú.
    ["mis_establecimientos" => $mis_establecimientos, "solicitudes" => $solicitudes,
        "cnt_pendientes" => $cnt_pendientes] = datosEstablecimientosAdministrador(
            (string) ($user["correo"] ?? ""), (int) $user["id"], $est_id);
} catch (Exception $ex) {
    $mis_establecimientos = [];
    $solicitudes = [];
    $cnt_pendientes = 0;
}

$msg_ok = "";
if (isset($_SESSION["solicitud_success"])) {
    $msg_ok = $_SESSION["solicitud_success"];
    unset($_SESSION["solicitud_success"]);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>RetinAI — Mis Establecimientos</title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">

    <link rel="stylesheet" href="<?= $base ?>assets/css/views/admin/misestablecimientos.css">
</head>

<body>

    <?php require_once __DIR__ . "/../shared/header.php"; ?>

    <div class="app-shell">
        <?php require_once __DIR__ . "/../shared/sidebar.php"; ?>

        <main class="main-content">
            <?php if (
            $msg_ok
        ): ?><div class="alert-flash alert-ok" id="flash-msg"><?= $msg_ok ?></div><?php endif; ?>

            <div class="section-header ajuste-visualizacion-flex-align-items-flex-end-justify-content-space-between">
                <div>
                    <h1 class="page-title">Mis Establecimientos</h1>
                    <p class="page-sub">Gestione sus centros oftalmológicos y solicite el registro de nuevos.</p>
                </div>
                <a href="<?= $base ?>views/admin/nuevoestablecimiento.php" class="btn-add">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M12 5v14M5 12h14" stroke="white" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    Añadir establecimiento
                </a>
            </div>

            <!-- ── Establecimientos activos ─────────────────────────────── -->
            <h3 class="ajuste-tamano-texto-15px-margen-inferior-14px-color-color">Establecimientos a su cargo (<?= count(
                $mis_establecimientos,
            ) ?>)</h3>

            <?php if (empty($mis_establecimientos)): ?>
            <div class="card full-width-card ajuste-margen-inferior-24px">
                <p class="empty-msg">Aún no tiene establecimientos registrados. Solicite uno usando el botón de arriba.
                </p>
            </div>
            <?php else: ?>
            <div class="est-grid">
                <?php foreach ($mis_establecimientos as $e): ?>
                <div class="est-card">
                    <p class="est-card-title"><?= htmlspecialchars(
                        $e["nombre"],
                    ) ?></p>
                    <p>
                        <strong>RUC:</strong> <?= htmlspecialchars(
                        $e["ruc"] ?? "—",
                    ) ?>
                    </p>
                    <p>
                        <strong>Dirección:</strong> <?= htmlspecialchars(
                        $e["direccion"] ?? "—",
                    ) ?>
                    </p>
                    <span class="badge-tipo <?= $e["tipo"] === "privado"
                        ? "privado"
                        : "" ?>">
                        <?= ucfirst(htmlspecialchars($e["tipo"] ?? "—")) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ── Historial de solicitudes ────────────────────────────── -->
            <div class="card full-width-card">
                <h3 class="ajuste-margen-superior-0-tamano-texto-16px-margen-inferior-16px">Historial de Solicitudes</h3>
                <?php if (empty($solicitudes)): ?>
                <p class="empty-msg">No ha registrado solicitudes adicionales.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Centro</th>
                            <th>Dirección</th>
                            <th>RUC</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $s): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars(
                                $s["nombre_centro"],
                            ) ?></strong>
                                <br>
                                <span class="text-muted small">Tipo: <?= ucfirst(
                                htmlspecialchars($s["tipo"]),
                            ) ?></span>
                            </td>
                            <td><?= htmlspecialchars($s["direccion"]) ?></td>
                            <td class="mono"><?= htmlspecialchars($s["ruc"]) ?></td>
                            <td class="text-muted small"><?= date(
                            "d/m/Y",
                            strtotime($s["fecha_solicitud"]),
                        ) ?></td>
                            <td>
                                <?php if ($s["estado"] === "pendiente"): ?>
                                <span class="badge-pendiente">Pendiente</span>
                                <?php elseif ($s["estado"] === "aprobado"): ?>
                                <span class="badge-aprobado">Aprobado</span>
                                <?php else: ?>
                                <span class="badge-rechazado">Rechazado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
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
