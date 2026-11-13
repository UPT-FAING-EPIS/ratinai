<?php
require_once __DIR__ . "/../../config/session_guard.php";
require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../controllers/EstablecimientoController.php";
require_once __DIR__ . "/../../controllers/SolicitudController.php";
require_role("SAD");
$user = current_user();
$initials = get_initials($user["nombre"]);
$base = get_base_path();
$logout_url = $base . "controllers/AuthController.php?action=logout";

$role_label = "Super Administrador";
$role_class = "role-sad";
$avatar_class = "avatar-sad";
$header_sub = "Control Global";

$msg_success = "";
$msg_error = "";
if (isset($_SESSION["solicitud_msg"])) {
    $msg_success = $_SESSION["solicitud_msg"];
    unset($_SESSION["solicitud_msg"]);
}

try {
    // La lectura de centros y solicitudes se resuelve en sus componentes de dominio.
    $establecimientos = EstablecimientoController::listarConMedicos();

    // Solicitudes pendientes de registro
    $solicitudes = [];
    $cnt_solicitudes_pendientes = 0;
    try {
        ["solicitudes" => $solicitudes, "pendientes" => $cnt_solicitudes_pendientes] =
            datosSolicitudesAdministracion();
    } catch (Exception $e) {
        $solicitudes = [];
        $cnt_solicitudes_pendientes = 0;
    }
} catch (Exception $ex) {
    $establecimientos = [];
    $solicitudes = [];
    $cnt_solicitudes_pendientes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>RetinAI — Establecimientos</title>
    <meta name="description" content="Gestión de centros oftalmológicos registrados en RetinAI.">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/paneles.css">

    <link rel="stylesheet" href="<?= $base ?>assets/css/views/superadmin/Establecimientos.css">
</head>

<body>

    <?php require_once __DIR__ . "/../shared/header.php"; ?>

    <div class="app-shell">

        <?php require_once __DIR__ . "/../shared/sidebar.php"; ?>

        <main class="main-content">

            <?php if ($msg_success): ?>
            <div class="alert-flash"><?= htmlspecialchars($msg_success) ?></div>
            <?php endif; ?>

            <div class="section-header">
                <h1 class="page-title">Establecimientos</h1>
                <p class="page-sub">Gestión de centros oftalmológicos en la plataforma.</p>
            </div>

            <!-- ── Centros activos ── -->
            <div class="card">
                <div class="establishment-filters">
                    <label class="filter-search" aria-label="Buscar establecimientos">
                        <svg viewBox="0 0 24 24"
                            fill="none" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                            <path d="m16.5 16.5 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <input id="filtro-establecimiento" type="search"
                            placeholder="Buscar por nombre, RUC o dirección">
                    </label>
                    <select id="filtro-estado" aria-label="Filtrar por estado">
                        <option value="">Todos los estados</option>
                        <option value="activo">Activo</option>
                        <option value="sin_responsable">Sin responsable</option>
                    </select>
                </div>
                <?php if (empty($establecimientos)): ?>
                <p class="empty-msg">No hay establecimientos registrados.</p>
                <?php else: ?>
                <table class="data-table" id="tabla-establecimientos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Médicos</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($establecimientos as $e): ?>
                        <tr data-fila data-busqueda="<?= htmlspecialchars(
                    mb_strtolower(
                        ($e["nombre"] ?? "") .
                            " " .
                            ($e["direccion"] ?? "") .
                            " " .
                            ($e["ruc"] ?? ""),
                    ),
                ) ?>" data-estado="<?= htmlspecialchars($e["estado"]) ?>">
                            <td class="mono"><?= (int) $e["id"] ?></td>
                            <td>
                                <strong><?= htmlspecialchars(
                        $e["nombre"],
                    ) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($e["direccion"] ?? "—") ?></td>
                            <td>
                                <span class="badge badge-info"><?= (int) $e[
                        "medicos"
                    ] ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $e["estado"] === "activo"
                        ? "badge-active"
                        : "badge-warning" ?>"><?= $e["estado"] === "activo"
    ? "Activo"
    : "Sin responsable" ?></span>
                            </td>
                            <td>
                                <a href="detalles_establecimientos.php?id=<?= $e[
                            "id"
                        ] ?>" class="btn-approve enlace-editar-establecimiento">Ver/Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="filter-empty" id="establecimientos-vacio">No hay establecimientos que coincidan con los
                    filtros.</p>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script src="<?= $base ?>assets/js/session.service.js"></script>
    <script src="<?= $base ?>assets/js/paneles.js"></script>
    <script>
        if (typeof SessionService !== 'undefined') {
            SessionService.init({
                timeout: 300000,
                loginUrl: '<?= htmlspecialchars(
            $base . "views/auth/login.php",
        ) ?>'
            });
        }
    </script>
    <script>
        const applyEstablishmentFilters = () => {
            const query = (document.getElementById('filtro-establecimiento')?.value || '').trim().toLocaleLowerCase(
                'es');
            const state = document.getElementById('filtro-estado')?.value || '';
            const rows = document.querySelectorAll('#tabla-establecimientos tbody tr[data-fila]');
            let visible = 0;
            rows.forEach(row => {
                const match = (!query || row.dataset.busqueda.includes(query)) && (!state || row.dataset
                    .estado === state);
                row.hidden = !match;
                if (match) visible++;
            });
            const empty = document.getElementById('establecimientos-vacio');
            if (empty) empty.style.display = rows.length && !visible ? 'block' : 'none';
        };
        document.getElementById('filtro-establecimiento')?.addEventListener('input', applyEstablishmentFilters);
        document.getElementById('filtro-estado')?.addEventListener('change', applyEstablishmentFilters);
    </script>
    <script src="<?= $base ?>assets/js/dashboard/establecimientos.js"></script>
</body>

</html>