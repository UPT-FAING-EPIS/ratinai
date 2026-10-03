<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../models/IntegracionModel.php';
require_auth();
require_role('SAD');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';
$role_label = '⚙️ Super Admin';
$role_class = 'role-sad';
$avatar_class = 'avatar-sad';
$header_sub = 'Super Administrador';
$_page = 'calidad_modelo.php';
$modelo = new IntegracionModel();
$calidad = $modelo->obtenerCalidadModelo();
$integraciones = $modelo->obtenerResumenGlobal();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RetinAI — Seguimiento del modelo</title><link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css"><style>.numero{font-weight:700;font-size:16px}.porcentaje{font-weight:700;color:var(--accent)}.nota{padding:11px;background:var(--surface2);border-radius:8px;font-size:12px;color:var(--text2);margin-bottom:16px}.table-responsive{overflow:auto}</style></head><body>
<?php require_once __DIR__ . '/../shared/header.php'; ?><div class="app-shell"><?php require_once __DIR__ . '/../shared/sidebar.php'; ?><main class="main-content">
<div class="page-header"><h1 class="page-title">Seguimiento del modelo</h1><p class="page-sub">Valoraciones médicas agregadas de versiones clínicas reales.</p></div>
<p class="nota"><strong>Qué significa este panel:</strong> resume cuántos médicos indicaron que coinciden o discrepan con la salida referencial de cada versión. No mide por sí solo precisión, sensibilidad, especificidad ni desempeño clínico. Los registros de demostración se excluyen.</p>
<section class="card mb-16"><div class="card-title">Valoración médica agregada</div><div class="table-responsive"><table class="data-table"><thead><tr><th>Versión</th><th>Establecimiento</th><th>Análisis</th><th>Evaluados</th><th>Coincidencias</th><th>Discrepancias</th><th>Sin evaluar</th></tr></thead><tbody><?php if($calidad===[]): ?><tr><td colspan="7">No existen valoraciones de una versión clínica real. El panel se poblará después de conectar la CNN remota y recibir revisiones médicas.</td></tr><?php endif; ?><?php foreach($calidad as $fila): $evaluados=(int)$fila['evaluados'];$porcentaje=$evaluados>0?((int)$fila['coincidencias']/$evaluados*100):0; ?><tr><td><?= htmlspecialchars($fila['version_modelo']) ?></td><td><?= htmlspecialchars($fila['establecimiento']) ?></td><td class="numero"><?= (int)$fila['total_analisis'] ?></td><td><?= $evaluados ?></td><td><span class="porcentaje"><?= number_format($porcentaje,1) ?>%</span> (<?= (int)$fila['coincidencias'] ?>)</td><td><?= (int)$fila['discrepancias'] ?></td><td><?= (int)$fila['sin_evaluar'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><div class="card-title">Estado del almacenamiento de informes</div><p class="nota">El administrador de cada establecimiento conecta Google Drive o OneDrive. «local» indica que solo existe la copia recuperable en el servidor.</p><div class="table-responsive"><table class="data-table"><thead><tr><th>Establecimiento</th><th>Proveedor</th><th>Verificación</th><th>Completadas</th><th>Fallidas</th><th>Pendientes</th></tr></thead><tbody><?php foreach($integraciones as $fila): ?><tr><td><?= htmlspecialchars($fila['nombre']) ?></td><td><?= htmlspecialchars($fila['proveedor']) ?></td><td><?= htmlspecialchars($fila['estado_conexion']) ?></td><td><?= (int)$fila['completadas'] ?></td><td><?= (int)$fila['fallidas'] ?></td><td><?= (int)$fila['pendientes'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</main></div></body></html>
