<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/EstablecimientoModel.php';
require_role('SAD');

$user         = current_user();
$initials     = get_initials($user['nombre']);
$base         = get_base_path();
$logout_url   = $base . 'controllers/AuthController.php?action=logout';
$role_label   = '⚡ Super Administrador';
$role_class   = 'role-sad';
$avatar_class = 'avatar-sad';
$header_sub   = 'Detalles del Establecimiento';

// Validar ID
$id_establecimiento = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_establecimiento === 0) {
    header('Location: Establecimientos.php');
    exit;
}

// Flash messages desde el controlador
$msg_success = '';
$msg_error   = '';
if (isset($_SESSION['est_success'])) {
    $msg_success = $_SESSION['est_success'];
    unset($_SESSION['est_success']);
}
if (isset($_SESSION['est_error'])) {
    $msg_error = $_SESSION['est_error'];
    unset($_SESSION['est_error']);
}

// Cargar datos a través del modelo
$model = new EstablecimientoModel();
try {
    $establecimiento = $model->getById($id_establecimiento);
    if (!$establecimiento) {
        header('Location: Establecimientos.php');
        exit;
    }
    $admins  = $model->getAdminByEstablecimiento($id_establecimiento);
    $medicos = $model->getMedicosByEstablecimiento($id_establecimiento);
    $conexion = (new Database())->getConnection();
    $consultaResumen = $conexion->prepare(
        "SELECT COUNT(DISTINCT a.id_paciente) AS pacientes, COUNT(DISTINCT a.id) AS analisis,
                COUNT(DISTINCT CASE WHEN i.estado='aprobado' THEN i.id END) AS informes,
                MAX(a.fecha_analisis) AS ultima_actividad
         FROM usuarios u
         LEFT JOIN analisis_retinales a ON a.id_medico=u.id
         LEFT JOIN informes_clinicos i ON i.id_analisis=a.id
         WHERE u.establecimiento_id=?"
    );
    $consultaResumen->execute([$id_establecimiento]);
    $resumen = $consultaResumen->fetch(PDO::FETCH_ASSOC) ?: ['pacientes'=>0,'analisis'=>0,'informes'=>0,'ultima_actividad'=>null];
    $consultaIntegracion = $conexion->prepare("SELECT proveedor,ultima_prueba_estado,ultima_prueba_fecha FROM configuraciones_almacenamiento WHERE id_establecimiento=? LIMIT 1");
    $consultaIntegracion->execute([$id_establecimiento]);
    $integracion = $consultaIntegracion->fetch(PDO::FETCH_ASSOC) ?: ['proveedor'=>'local','ultima_prueba_estado'=>'sin_probar','ultima_prueba_fecha'=>null];
} catch (Exception $ex) {
    $msg_error       = 'Error cargando detalles del establecimiento.';
    $establecimiento = [];
    $admins          = [];
    $medicos         = [];
    $resumen         = ['pacientes'=>0,'analisis'=>0,'informes'=>0,'ultima_actividad'=>null];
    $integracion     = ['proveedor'=>'local','ultima_prueba_estado'=>'sin_probar','ultima_prueba_fecha'=>null];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Detalles Establecimiento</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/detalles_establecimientos.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/paneles.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($msg_success): ?>
        <div class="alert-flash alert-ok" id="flash-msg"><?= htmlspecialchars($msg_success) ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
        <div class="alert-flash alert-err" id="flash-msg-err"><?= htmlspecialchars($msg_error) ?></div>
        <?php endif; ?>

        <div class="section-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="page-title"><?= htmlspecialchars($establecimiento['nombre'] ?? 'Establecimiento') ?></h1>
                    <p class="page-sub">Información institucional, actividad y usuarios asociados.</p>
                </div>
                <a href="Establecimientos.php" class="btn btn-outline" style="text-decoration:none; display:inline-flex; align-items:center; gap: 8px; padding:8px 16px;">
                    <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Volver
                </a>
            </div>
        </div>

        <div class="panel-kpis" style="grid-template-columns:repeat(5,minmax(140px,1fr))">
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Médicos</span><strong class="panel-kpi__valor"><?= count($medicos) ?></strong></article>
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Pacientes</span><strong class="panel-kpi__valor"><?= (int)$resumen['pacientes'] ?></strong></article>
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Análisis</span><strong class="panel-kpi__valor"><?= (int)$resumen['analisis'] ?></strong></article>
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Informes</span><strong class="panel-kpi__valor"><?= (int)$resumen['informes'] ?></strong></article>
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Última actividad</span><strong class="panel-kpi__detalle" style="font-size:13px;color:var(--text);margin-top:12px"><?= $resumen['ultima_actividad'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($resumen['ultima_actividad']))) : 'Sin actividad' ?></strong></article>
        </div>

        <div class="detalles-grid">
            <!-- Formulario de Edición — POST al controlador -->
            <div class="card form-card">
                <h3 class="card-title">Información del Establecimiento</h3>
                <form method="POST"
                      action="<?= $base ?>controllers/EstablecimientoController.php?action=update"
                      id="form-est">
                    <input type="hidden" name="id_establecimiento" value="<?= $id_establecimiento ?>">
                    <div class="form-group">
                        <label>Nombre del Centro</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($establecimiento['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="direccion" value="<?= htmlspecialchars($establecimiento['direccion'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">Seleccione...</option>
                            <option value="publico"  <?= (($establecimiento['tipo'] ?? '') === 'publico')  ? 'selected' : '' ?>>Público</option>
                            <option value="privado"  <?= (($establecimiento['tipo'] ?? '') === 'privado')  ? 'selected' : '' ?>>Privado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>RUC</label>
                        <input type="text" name="ruc" value="<?= htmlspecialchars($establecimiento['ruc'] ?? '') ?>" maxlength="11" pattern="\d{11}">
                    </div>
                <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
                <div style="margin-top:22px"><h3 class="card-title">Ubicación</h3><?php if ($establecimiento['latitud'] !== null && $establecimiento['longitud'] !== null): ?><div class="mapa-detalle" id="mapa-establecimiento"></div><?php else: ?><div class="estado-vacio">Sin coordenadas registradas.</div><?php endif; ?></div>
            </div>

            <!-- Información de Usuarios -->
            <div class="users-section">
                <!-- Titular/Admin -->
                <div class="card list-card">
                    <h3 class="card-title">Titular / Administrador</h3>
                    <?php if (empty($admins)): ?>
                        <p class="empty-msg">No hay administrador asignado a este establecimiento.</p>
                    <?php else: ?>
                        <div class="user-list">
                            <?php foreach ($admins as $adm): ?>
                                <div class="user-item">
                                    <div class="user-info">
                                        <strong><?= htmlspecialchars($adm['nombre']) ?></strong>
                                        <span class="text-muted small"><?= htmlspecialchars($adm['correo']) ?></span>
                                    </div>
                                    <span class="badge badge-active">Admin</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card list-card">
                    <h3 class="card-title">Almacenamiento documental</h3>
                    <div class="detalle-datos"><div class="detalle-dato"><span>Proveedor</span><strong><?= htmlspecialchars(match($integracion['proveedor']){'google_drive'=>'Google Drive','onedrive'=>'OneDrive',default=>'Copia local'}) ?></strong></div><div class="detalle-dato"><span>Estado</span><strong><?= htmlspecialchars(str_replace('_',' ',ucfirst($integracion['ultima_prueba_estado']))) ?></strong></div><div class="detalle-dato"><span>Última verificación</span><strong><?= $integracion['ultima_prueba_fecha']?htmlspecialchars(date('d/m/Y H:i',strtotime($integracion['ultima_prueba_fecha']))):'Sin verificar' ?></strong></div><div class="detalle-dato"><span>Tipo</span><strong><?= htmlspecialchars(ucfirst($establecimiento['tipo']??'Sin registrar')) ?></strong></div></div>
                </div>

                <!-- Médicos -->
                <div class="card list-card">
                    <h3 class="card-title">Médicos Oftalmólogos</h3>
                    <?php if (empty($medicos)): ?>
                        <p class="empty-msg">No hay médicos registrados en este establecimiento.</p>
                    <?php else: ?>
                        <div class="user-list">
                            <?php foreach ($medicos as $med): ?>
                                <div class="user-item">
                                    <div class="user-info">
                                        <strong><?= htmlspecialchars($med['nombre']) ?></strong>
                                        <span class="text-muted small"><?= htmlspecialchars($med['correo']) ?> | CMP: <?= htmlspecialchars($med['cmp'] ?? '—') ?></span>
                                    </div>
                                    <?php if ($med['activo']): ?>
                                        <span class="badge badge-active">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Inactivo</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script>
    if (typeof SessionService !== 'undefined') {
        SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base."views/auth/login.php") ?>' });
    }
    let remaining = 300;
    const cd = document.getElementById('session-countdown');
    if(cd) {
        setInterval(() => {
            const m = Math.floor(remaining / 60).toString().padStart(2, '0');
            const s = (remaining % 60).toString().padStart(2, '0');
            cd.textContent = m + ':' + s;
            if (remaining > 0) remaining--;
        }, 1000);
    }
    const flash = document.getElementById('flash-msg');
    if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 3500);
</script>
<script src="<?= $base ?>assets/js/dashboard/detalles_establecimientos.js"></script>
<?php if ($establecimiento['latitud'] !== null && $establecimiento['longitud'] !== null): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>const latitud=<?= json_encode((float)$establecimiento['latitud']) ?>,longitud=<?= json_encode((float)$establecimiento['longitud']) ?>,mapa=L.map('mapa-establecimiento').setView([latitud,longitud],16);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(mapa);L.marker([latitud,longitud]).addTo(mapa).bindPopup(<?= json_encode($establecimiento['nombre'],JSON_UNESCAPED_UNICODE) ?>).openPopup();</script><?php endif; ?>
</body>
</html>
