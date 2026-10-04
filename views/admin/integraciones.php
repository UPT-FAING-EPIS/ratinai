<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../models/EstablecimientoModel.php';
require_once __DIR__ . '/../../models/IntegracionModel.php';
require_auth();
require_role('ADM');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';
$role_label = 'Administrador';
$role_class = 'role-adm';
$avatar_class = 'avatar-adm';
$header_sub = 'Administrador del establecimiento';
$_page = 'integraciones.php';
$modeloEstablecimiento = new EstablecimientoModel();
$establecimientos = $modeloEstablecimiento->getByOwnerId((int)$user['id']);
if ($establecimientos === [] && !empty($user['establecimiento_id'])) {
    $asignado = $modeloEstablecimiento->getById((int)$user['establecimiento_id']);
    if ($asignado) $establecimientos[] = $asignado;
}
$idSeleccionado = (int)($_GET['establecimiento'] ?? ($establecimientos[0]['id'] ?? 0));
$idsPermitidos = array_map('intval', array_column($establecimientos, 'id'));
if (!in_array($idSeleccionado, $idsPermitidos, true)) $idSeleccionado = (int)($idsPermitidos[0] ?? 0);
$modeloIntegracion = new IntegracionModel();
$configuracion = $idSeleccionado ? $modeloIntegracion->obtenerConfiguracion($idSeleccionado) : null;
$sincronizaciones = $idSeleccionado ? $modeloIntegracion->listarSincronizaciones($idSeleccionado) : [];
$oauthDisponible = str_starts_with(APP_URL, 'https://');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RetinAI — Almacenamiento de informes</title><link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css"><style>
.estado-integracion{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0}.estado-integracion>div{padding:14px;border:1px solid var(--border);border-radius:9px}.estado-integracion span{display:block;font-size:10px;color:var(--text3);text-transform:uppercase}.estado-integracion strong{font-size:14px}.estado-completada{color:var(--success)}.estado-fallida{color:var(--danger)}.estado-pendiente{color:var(--warning)}.acciones{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0}@media(max-width:700px){.estado-integracion{grid-template-columns:1fr}.table-responsive{overflow:auto}}
</style><script>const BASE_URL='<?= $base ?>';</script></head><body>
<?php require_once __DIR__ . '/../shared/header.php'; ?><div class="app-shell"><?php require_once __DIR__ . '/../shared/sidebar.php'; ?><main class="main-content">
<div class="page-header"><h1 class="page-title">Almacenamiento de informes</h1><p class="page-sub">Conecta la cuenta de Google Drive o OneDrive de cada establecimiento.</p></div>
<?php if ($establecimientos === []): ?><div class="card"><p>No hay establecimientos asignados.</p></div><?php else: ?>
<section class="card mb-16"><label class="form-label-d" for="establecimiento">Establecimiento</label><select class="form-input-d" id="establecimiento"><?php foreach($establecimientos as $establecimiento): ?><option value="<?= (int)$establecimiento['id'] ?>" <?= (int)$establecimiento['id'] === $idSeleccionado ? 'selected' : '' ?>><?= htmlspecialchars($establecimiento['nombre']) ?></option><?php endforeach; ?></select></section>
<section class="card mb-16">
    <div class="card-title">Cuenta documental del establecimiento</div>
    <?php if (($_GET['oauth'] ?? '') === 'error'): ?><div class="alert-box alert-warning"><p>No se pudo conectar la cuenta. Comprueba el consentimiento y vuelve a intentarlo.</p></div><?php endif; ?>
    <?php if (($_GET['oauth'] ?? '') === 'conectado'): ?><div class="alert-box"><p>Cuenta documental conectada correctamente.</p></div><?php endif; ?>
    <p>Elige una sola cuenta. Puede ser personal o corporativa. Al conectar otro proveedor, el anterior deja de estar activo.</p>
    <?php if (!$oauthDisponible): ?><p class="text-muted">La autorización de cuentas se inicia desde la aplicación HTTPS de Azure, donde están registradas las URLs de retorno.</p><?php endif; ?>
    <div class="acciones">
        <?php if ($oauthDisponible): ?>
        <a class="btn btn-primary" href="<?= htmlspecialchars($base . 'controllers/IntegracionController.php?action=iniciar&proveedor=google_drive&establecimiento=' . $idSeleccionado) ?>">Conectar Google Drive</a>
        <a class="btn btn-primary" href="<?= htmlspecialchars($base . 'controllers/IntegracionController.php?action=iniciar&proveedor=onedrive&establecimiento=' . $idSeleccionado) ?>">Conectar OneDrive</a>
        <?php endif; ?>
    </div>
    <div class="estado-integracion"><div><span>Destino actual</span><strong><?= htmlspecialchars(match ($configuracion['proveedor'] ?? 'local') { 'google_drive' => 'Google Drive', 'onedrive' => 'OneDrive', default => 'Sin cuenta conectada' }) ?></strong></div><div><span>Última verificación</span><strong><?= htmlspecialchars($configuracion['ultima_prueba_estado'] ?? 'sin_probar') ?></strong></div><div><span>Fecha</span><strong><?= htmlspecialchars($configuracion['ultima_prueba_fecha'] ?? 'Aún no verificada') ?></strong></div></div>
    <p class="text-muted" style="font-size:12px"><?= htmlspecialchars($configuracion['ultima_prueba_mensaje'] ?? 'Conecta una cuenta para sincronizar los PDF aprobados.') ?></p>
    <?php if (in_array($configuracion['proveedor'] ?? '', ['google_drive', 'onedrive'], true)): ?><button class="btn btn-ghost" id="probar-conexion">Probar conexión de la cuenta</button><?php endif; ?>
</section>
<section class="card"><div class="card-title">Sincronizaciones de informes</div><div class="table-responsive"><table class="data-table"><thead><tr><th>Informe</th><th>Paciente</th><th>Destino</th><th>Estado</th><th>Intentos</th><th>Actualización</th><th>Acción</th></tr></thead><tbody><?php if($sincronizaciones === []): ?><tr><td colspan="7">Aún no hay sincronizaciones.</td></tr><?php endif; ?><?php foreach($sincronizaciones as $sincronizacion): ?><tr><td>#<?= (int)$sincronizacion['id_informe'] ?> · v<?= (int)$sincronizacion['version'] ?></td><td><?= htmlspecialchars($sincronizacion['codigo_paciente'] ?: 'Sin código') ?></td><td><?= htmlspecialchars($sincronizacion['proveedor']) ?></td><td class="estado-<?= htmlspecialchars($sincronizacion['estado']) ?>"><?= htmlspecialchars($sincronizacion['estado']) ?></td><td><?= (int)$sincronizacion['intentos'] ?></td><td><?= htmlspecialchars($sincronizacion['fecha_actualizacion']) ?></td><td><?php if($sincronizacion['estado'] !== 'completada'): ?><button class="btn btn-ghost btn-sm reintentar" data-id="<?= (int)$sincronizacion['id'] ?>">Reintentar</button><?php elseif(in_array($sincronizacion['proveedor'], ['google_drive', 'onedrive'], true) && str_starts_with((string)$sincronizacion['ruta_remota'], 'https://')): ?><a href="<?= htmlspecialchars($sincronizacion['ruta_remota'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Abrir archivo</a><?php else: ?>—<?php endif; ?></td></tr><?php if(!empty($sincronizacion['ultimo_error'])): ?><tr><td colspan="7" class="text-muted">Último error: <?= htmlspecialchars($sincronizacion['ultimo_error']) ?></td></tr><?php endif; ?><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?></main></div><div id="toast" class="toast" style="display:none"></div><script>
(() => { const idEstablecimiento=<?= $idSeleccionado ?>; const selector=document.getElementById('establecimiento'); selector?.addEventListener('change',()=>window.location.search='establecimiento='+selector.value); async function enviar(accion,valores){const formulario=new FormData();Object.entries(valores).forEach(([clave,valor])=>formulario.append(clave,String(valor)));return fetch(BASE_URL+'controllers/IntegracionController.php?action='+accion,{method:'POST',body:formulario}).then(respuesta=>respuesta.json());} function mensaje(texto,tipo){const elemento=document.getElementById('toast');elemento.textContent=texto;elemento.className='toast '+tipo;elemento.style.display='flex';setTimeout(()=>elemento.style.display='none',3500);} document.getElementById('probar-conexion')?.addEventListener('click',async()=>{const respuesta=await enviar('probar',{id_establecimiento:idEstablecimiento});mensaje(respuesta.mensaje||respuesta.error,respuesta.success?'success':'danger');if(respuesta.success)setTimeout(()=>window.location.reload(),600);});document.querySelectorAll('.reintentar').forEach(boton=>boton.addEventListener('click',async()=>{boton.disabled=true;const respuesta=await enviar('reintentar',{id_establecimiento:idEstablecimiento,id_sincronizacion:boton.dataset.id});mensaje(respuesta.mensaje||respuesta.error,respuesta.success?'success':'danger');if(respuesta.success)setTimeout(()=>window.location.reload(),600);else boton.disabled=false;})); })();
</script></body></html>
