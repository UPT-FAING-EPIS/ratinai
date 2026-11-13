<?php
require_once __DIR__ . "/../../config/session_guard.php";
require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../controllers/EstablecimientoController.php";
require_role("SAD");
$usuario = current_user();
$initials = get_initials($usuario["nombre"]);
$base = get_base_path();
$logout_url = $base . "controllers/AuthController.php?action=logout";
$role_label = "Super Administrador";
$role_class = "role-sad";
$avatar_class = "avatar-sad";
$header_sub = "Control global";
$_page = "index.php";
$indicadores = [
    "establecimientos" => 0,
    "medicos" => 0,
    "administradores" => 0,
    "solicitudes" => 0,
    "analisis" => 0,
    "informes" => 0,
];
$usuariosPorRol = $solicitudesPorEstado = $actividadMensual = $establecimientosActivos = $actividadReciente = [];
$integraciones = ["completadas" => 0, "fallidas" => 0, "pendientes" => 0];
// El panel prepara valores vacíos para mantener la presentación si falla la lectura.
try {
    // El controlador reúne los indicadores y series que consume esta plantilla.
    extract(EstablecimientoController::datosPanelGlobal(), EXTR_OVERWRITE);
} catch (Throwable $error) {
    error_log(
        "No se pudo construir el dashboard global: " . $error->getMessage(),
    );
}
$datosUsuarios = array_map(
    static fn($fila) => [$fila["etiqueta"], (int) $fila["total"]],
    $usuariosPorRol,
);
$datosSolicitudes = array_map(
    static fn($fila) => [$fila["etiqueta"], (int) $fila["total"]],
    $solicitudesPorEstado,
);
$datosActividad = array_map(
    static fn($fila) => [$fila["periodo"], (int) $fila["total"]],
    $actividadMensual,
);
$datosCentros = array_map(
    static fn($fila) => [
        $fila["nombre"],
        (int) $fila["medicos"],
        (int) $fila["analisis"],
    ],
    $establecimientosActivos,
);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>RetinAI — Dashboard global</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/paneles.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/views/superadmin/index.css">
</head>

<body>
    <?php require_once __DIR__ . "/../shared/header.php"; ?>
    <div class="app-shell">
        <?php require_once __DIR__ . "/../shared/sidebar.php"; ?>
        <main class="main-content">
            <header class="panel-cabecera">
                <div>
                    <h1 class="page-title">Dashboard global</h1>
                    <p class="page-sub">Estado operativo de RetinAI con información consolidada.</p>
                </div>
                <div class="panel-acciones">
                    <button class="boton-informacion" data-ayuda="ayuda-dashboard"
                        aria-label="Información del dashboard">i</button>
                    <button class="boton-exportar" onclick='PanelesRetinAI.descargarCsv("resumen_global_retinai.csv",["Indicador","Total"],<?= json_encode(
    array_map(
        static fn($clave, $valor) => [$clave, $valor],
        array_keys($indicadores),
        $indicadores,
    ),
    JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>)'>Descargar resumen</button>
                </div>
            </header>
            <div class="panel-kpis"><?php foreach (
    [
        ["Establecimientos", $indicadores["establecimientos"]],
        ["Médicos activos", $indicadores["medicos"]],
        ["Administradores", $indicadores["administradores"]],
        ["Solicitudes pendientes", $indicadores["solicitudes"]],
        ["Análisis", $indicadores["analisis"]],
        ["Informes aprobados", $indicadores["informes"]],
    ]
    as $indicador
): ?><article class="panel-kpi">
                    <span class="panel-kpi__etiqueta"><?= htmlspecialchars(
    $indicador[0],
) ?></span>
                    <strong class="panel-kpi__valor"><?= (int) $indicador[1] ?></strong>
                </article><?php endforeach; ?></div>
            <div class="panel-rejilla">
                <section class="panel-tarjeta panel-ancho-8">
                    <div class="panel-titulo">
                        <h2>Actividad de análisis por mes</h2>
                        <button class="boton-exportar" onclick='PanelesRetinAI.descargarCsv("analisis_por_mes.csv",["Mes","Análisis"],<?= json_encode(
    $datosActividad,
    JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>)'>CSV</button>
                    </div>
                    <div class="panel-grafico">
                        <canvas id="grafico-actividad"></canvas>
                    </div>
                </section>
                <section class="panel-tarjeta panel-ancho-4">
                    <div class="panel-titulo">
                        <h2>Solicitudes por estado</h2>
                        <button class="boton-exportar" onclick='PanelesRetinAI.descargarCsv("solicitudes_por_estado.csv",["Estado","Total"],<?= json_encode(
    $datosSolicitudes,
    JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>)'>CSV</button>
                    </div>
                    <div class="panel-grafico">
                        <canvas id="grafico-solicitudes"></canvas>
                    </div>
                </section>
                <section class="panel-tarjeta panel-ancho-7">
                    <div class="panel-titulo">
                        <h2>Actividad por establecimiento</h2>
                        <button class="boton-exportar" onclick='PanelesRetinAI.descargarCsv("actividad_establecimientos.csv",["Establecimiento","Médicos","Análisis"],<?= json_encode(
    $datosCentros,
    JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>)'>CSV</button>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Establecimiento</th>
                                    <th>Médicos</th>
                                    <th>Análisis</th>
                                </tr>
                            </thead>
                            <tbody><?php
if (
    $establecimientosActivos === []
): ?><tr>
                                    <td colspan="3" class="estado-vacio">Sin información disponible.</td>
                                </tr><?php endif;
foreach (
    $establecimientosActivos
    as $fila
): ?><tr>
                                    <td>
                                        <strong><?= htmlspecialchars(
    $fila["nombre"],
) ?></strong>
                                    </td>
                                    <td><?= (int) $fila["medicos"] ?></td>
                                    <td><?= (int) $fila[
    "analisis"
] ?></td>
                                </tr><?php endforeach;
?></tbody>
                        </table>
                    </div>
                </section>
                <section class="panel-tarjeta panel-ancho-5">
                    <div class="panel-titulo">
                        <h2>Usuarios activos por rol</h2>
                        <button class="boton-exportar" onclick='PanelesRetinAI.descargarCsv("usuarios_por_rol.csv",["Rol","Total"],<?= json_encode(
    $datosUsuarios,
    JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>)'>CSV</button>
                    </div>
                    <div class="panel-grafico panel-grafico--compacto">
                        <canvas id="grafico-usuarios"></canvas>
                    </div>
                </section>
                <section class="panel-tarjeta panel-ancho-8">
                    <div class="panel-titulo">
                        <h2>Actividad clínica reciente</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Establecimiento</th>
                                    <th>Resultado</th>
                                    <th>Modelo</th>
                                </tr>
                            </thead>
                            <tbody><?php
if (
    $actividadReciente === []
): ?><tr>
                                    <td colspan="4" class="estado-vacio">Sin análisis registrados.</td>
                                </tr><?php endif;
foreach ($actividadReciente as $fila): ?><tr>
                                    <td><?= htmlspecialchars(
    date("d/m/Y H:i", strtotime($fila["fecha_analisis"])),
) ?></td>
                                    <td><?= htmlspecialchars(
    $fila["establecimiento"] ?? "Sin establecimiento",
) ?></td>
                                    <td><?= htmlspecialchars(
    $fila["resultado_principal"] ?? "Sin resultado",
) ?></td>
                                    <td><?= htmlspecialchars(
    $fila["version_modelo"] ?? "Sin versión",
) ?></td>
                                </tr><?php endforeach;
?></tbody>
                        </table>
                    </div>
                </section>
                <section class="panel-tarjeta panel-ancho-4">
                    <div class="panel-titulo">
                        <h2>Sincronización documental</h2>
                    </div>
                    <div class="panel-lista">
                        <div class="panel-lista__fila">
                            <span>Completadas</span>
                            <strong class="panel-lista__valor"><?= (int) $integraciones[
    "completadas"
] ?></strong>
                        </div>
                        <div class="panel-lista__fila">
                            <span>Fallidas</span>
                            <strong class="panel-lista__valor"><?= (int) $integraciones[
    "fallidas"
] ?></strong>
                        </div>
                        <div class="panel-lista__fila">
                            <span>Pendientes</span>
                            <strong class="panel-lista__valor"><?= (int) $integraciones[
    "pendientes"
] ?></strong>
                        </div>
                    </div>
                    <div class="panel-acciones ajuste-margen-superior-18px">
                        <a class="btn btn-ghost"
                            href="calidad_modelo.php">Abrir seguimiento</a>
                    </div>
                </section>
            </div>
            <aside class="ayuda-flotante" id="ayuda-dashboard">Los indicadores consolidan los registros actuales del
                sistema. Cada botón CSV descarga el listado asociado al bloque visible.</aside>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= $base ?>assets/js/paneles.js"></script>
    <script src="<?= $base ?>assets/js/session.service.js"></script>
    <script>
        const actividad = <?= json_encode(
    $actividadMensual,
    JSON_UNESCAPED_UNICODE,
) ?>,
            solicitudes = <?= json_encode(
    $solicitudesPorEstado,
    JSON_UNESCAPED_UNICODE,
) ?>,
            usuarios = <?= json_encode(
    $usuariosPorRol,
    JSON_UNESCAPED_UNICODE,
) ?>;
        const opciones = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        };
        new Chart(document.getElementById('grafico-actividad'), {
            type: 'line',
            data: {
                labels: actividad.map(f => f.periodo),
                datasets: [{
                    label: 'Análisis',
                    data: actividad.map(f => Number(f.total)),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.12)',
                    fill: true,
                    tension: .32
                }]
            },
            options: opciones
        });
        new Chart(document.getElementById('grafico-solicitudes'), {
            type: 'doughnut',
            data: {
                labels: solicitudes.map(f => f.etiqueta),
                datasets: [{
                    data: solicitudes.map(f => Number(f.total)),
                    backgroundColor: ['#f59e0b', '#10b981', '#ef4444']
                }]
            },
            options: opciones
        });
        new Chart(document.getElementById('grafico-usuarios'), {
            type: 'bar',
            data: {
                labels: usuarios.map(f => f.etiqueta),
                datasets: [{
                    label: 'Usuarios activos',
                    data: usuarios.map(f => Number(f.total)),
                    backgroundColor: ['#7c3aed', '#10b981', '#2563eb']
                }]
            },
            options: opciones
        });
        SessionService.init({
            timeout: 300000,
            loginUrl: '<?= htmlspecialchars(
    $base . "views/auth/login.php",
) ?>'
        });
    </script>
</body>

</html>