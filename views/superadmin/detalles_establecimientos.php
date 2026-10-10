<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/EstablecimientoModel.php';
require_role('SAD');

$user         = current_user();
$initials     = get_initials($user['nombre']);
$base         = get_base_path();
$logout_url   = $base . 'controllers/AuthController.php?action=logout';
$role_label   = 'Super Administrador';
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
    $consultaModelo = $conexion->prepare(
        "SELECT SUM(CASE WHEN a.alerta_anomalia=1 THEN 1 ELSE 0 END) AS alertas,
                AVG(a.probabilidad_principal) AS confianza_media, AVG(a.tiempo_analisis) AS tiempo_medio
         FROM analisis_retinales a INNER JOIN usuarios u ON u.id=a.id_medico
         WHERE u.establecimiento_id=?"
    );
    $consultaModelo->execute([$id_establecimiento]);
    $modeloUso = $consultaModelo->fetch(PDO::FETCH_ASSOC) ?: ['alertas'=>0,'confianza_media'=>null,'tiempo_medio'=>null];
    $consultaActividad = $conexion->prepare(
        "SELECT DATE(a.fecha_analisis) AS fecha, COUNT(*) AS total
         FROM analisis_retinales a INNER JOIN usuarios u ON u.id=a.id_medico
         WHERE u.establecimiento_id=? AND a.fecha_analisis >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(a.fecha_analisis) ORDER BY fecha"
    );
    $consultaActividad->execute([$id_establecimiento]);
    $actividadPorFecha = [];
    foreach ($consultaActividad->fetchAll(PDO::FETCH_ASSOC) as $fila) $actividadPorFecha[$fila['fecha']] = (int) $fila['total'];
    $actividadModelo = [];
    for ($dia = 6; $dia >= 0; $dia--) { $fecha = date('Y-m-d', strtotime("-{$dia} days")); $actividadModelo[] = ['fecha'=>date('d/m', strtotime($fecha)), 'total'=>$actividadPorFecha[$fecha] ?? 0]; }
    $consultaResultados = $conexion->prepare(
        "SELECT COALESCE(NULLIF(a.resultado_principal,''),'Sin resultado') AS etiqueta, COUNT(*) AS total
         FROM analisis_retinales a INNER JOIN usuarios u ON u.id=a.id_medico
         WHERE u.establecimiento_id=? GROUP BY etiqueta ORDER BY total DESC LIMIT 5"
    );
    $consultaResultados->execute([$id_establecimiento]);
    $resultadosModelo = $consultaResultados->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    $msg_error       = 'Error cargando detalles del establecimiento.';
    $establecimiento = [];
    $admins          = [];
    $medicos         = [];
    $resumen         = ['pacientes'=>0,'analisis'=>0,'informes'=>0,'ultima_actividad'=>null];
    $integracion     = ['proveedor'=>'local','ultima_prueba_estado'=>'sin_probar','ultima_prueba_fecha'=>null];
    $modeloUso       = ['alertas'=>0,'confianza_media'=>null,'tiempo_medio'=>null];
    $actividadModelo = [];
    $resultadosModelo = [];
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

        <section class="analytics-grid" aria-label="Indicadores del modelo">
            <article class="card analytics-card"><h2>Uso del modelo · últimos 7 días</h2><p>Análisis procesados diariamente por los médicos del establecimiento.</p><div class="chart-wrap"><canvas id="chart-actividad-modelo"></canvas></div></article>
            <article class="card analytics-card"><h2>Hallazgos principales</h2><p>Resultados más frecuentes detectados por el modelo.</p><div class="chart-wrap"><canvas id="chart-resultados-modelo"></canvas></div></article>
        </section>
        <div class="panel-kpis" style="grid-template-columns:repeat(3,minmax(160px,1fr));margin-bottom:24px">
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Casos con alerta</span><strong class="panel-kpi__valor"><?= (int)($modeloUso['alertas'] ?? 0) ?></strong></article>
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Confianza media</span><strong class="panel-kpi__valor"><?= $modeloUso['confianza_media'] !== null ? number_format((float)$modeloUso['confianza_media'],1).'%' : '—' ?></strong></article>
            <article class="panel-kpi"><span class="panel-kpi__etiqueta">Tiempo medio de análisis</span><strong class="panel-kpi__valor"><?= $modeloUso['tiempo_medio'] !== null ? number_format((float)$modeloUso['tiempo_medio'],2).' s' : '—' ?></strong></article>
        </div>

        <div class="detalles-grid">
            <!-- Formulario de Edición — POST al controlador -->
            <div class="card form-card">
                <h3 class="card-title">Datos institucionales</h3>
                <form method="POST"
                      action="<?= $base ?>controllers/EstablecimientoController.php?action=update"
                      id="form-est">
                    <input type="hidden" name="id_establecimiento" value="<?= $id_establecimiento ?>">
                    <div class="form-grid">
                    <div class="form-group form-group--full">
                        <label>Nombre del Centro</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($establecimiento['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="form-group form-group--full">
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
                    </div>
                    <div class="form-section">
                        <p class="form-section__title">Ubicación geográfica</p>
                        <p class="form-section__hint">Registre ambas coordenadas en formato decimal para ubicar el centro en el mapa.</p>
                        <div class="form-grid">
                            <div class="form-group"><label for="latitud">Latitud</label><input id="latitud" type="number" name="latitud" step="any" min="-90" max="90" placeholder="Ej. -18.014650" value="<?= htmlspecialchars($establecimiento['latitud'] ?? '') ?>"></div>
                            <div class="form-group"><label for="longitud">Longitud</label><input id="longitud" type="number" name="longitud" step="any" min="-180" max="180" placeholder="Ej. -70.253620" value="<?= htmlspecialchars($establecimiento['longitud'] ?? '') ?>"></div>
                        </div>
                    </div>
                <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
                <div class="location-summary"><h3 class="card-title" style="border:0;padding:0;margin:0">Vista de ubicación</h3><?php if ($establecimiento['latitud'] !== null && $establecimiento['longitud'] !== null): ?><span class="coordinates-value"><?= htmlspecialchars($establecimiento['latitud']) ?>, <?= htmlspecialchars($establecimiento['longitud']) ?></span><?php endif; ?></div><?php if ($establecimiento['latitud'] !== null && $establecimiento['longitud'] !== null): ?><div class="mapa-detalle" id="mapa-establecimiento"></div><?php else: ?><div class="estado-vacio">Aún no se registraron coordenadas para este establecimiento.</div><?php endif; ?>
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
                <div class="card status-card">
                    <span class="status-card__label">Notificación al responsable</span>
                    <span class="status-card__value">Al guardar se intentará enviar un correo al titular o administrador asignado.</span>
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
    const flash = document.getElementById('flash-msg');
    if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 3500);
</script>
<script src="<?= $base ?>assets/js/dashboard/detalles_establecimientos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const actividadModelo = <?= json_encode($actividadModelo, JSON_UNESCAPED_UNICODE) ?>;
const resultadosModelo = <?= json_encode($resultadosModelo, JSON_UNESCAPED_UNICODE) ?>;
const chartBaseOptions = {responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}};
new Chart(document.getElementById('chart-actividad-modelo'), {type:'line', data:{labels:actividadModelo.map(f=>f.fecha), datasets:[{data:actividadModelo.map(f=>f.total), borderColor:'#1A56DB', backgroundColor:'rgba(26,86,219,.12)', fill:true, tension:.35, pointRadius:3, pointBackgroundColor:'#1A56DB'}]}, options:{...chartBaseOptions, scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(148,163,184,.18)'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('chart-resultados-modelo'), {type:'bar', data:{labels:resultadosModelo.map(f=>f.etiqueta), datasets:[{data:resultadosModelo.map(f=>f.total), backgroundColor:['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6'], borderRadius:5, maxBarThickness:34}]}, options:{...chartBaseOptions, scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(148,163,184,.18)'}},x:{grid:{display:false}}}}});
</script>
<?php if ($establecimiento['latitud'] !== null && $establecimiento['longitud'] !== null): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>const latitud=<?= json_encode((float)$establecimiento['latitud']) ?>,longitud=<?= json_encode((float)$establecimiento['longitud']) ?>,mapa=L.map('mapa-establecimiento').setView([latitud,longitud],16);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(mapa);L.marker([latitud,longitud]).addTo(mapa).bindPopup(<?= json_encode($establecimiento['nombre'],JSON_UNESCAPED_UNICODE) ?>).openPopup();</script><?php endif; ?>
</body>
</html>
