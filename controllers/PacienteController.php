<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/PacienteModel.php';
require_once __DIR__ . '/../models/CarpetaModel.php';

class PacienteController {
    private $model;

    public function __construct() {
        $this->model = new PacienteModel();
    }

    public function buscar_registrar() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }

        $dni = trim($_POST['dni'] ?? '');
        if (strlen($dni) !== 8 || !ctype_digit($dni)) {
            echo json_encode(['success' => false, 'error' => 'DNI inválido']);
            return;
        }

        $paciente = $this->model->buscarPorDNI($dni);
        if ($paciente) {
            echo json_encode(['success' => true, 'paciente' => $paciente, 'nuevo' => false]);
            return;
        }

        // Registrar
        $id = $this->model->registrarPaciente($dni);
        if ($id) {
            $paciente = $this->model->buscarPorDNI($dni);
            echo json_encode(['success' => true, 'paciente' => $paciente, 'nuevo' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al registrar paciente']);
        }
    }

    public function detalle_html() {
        if (!isset($_SESSION['user_id']) || $_SESSION['rol_codigo'] !== 'MED') {
            echo '<p class="text-danger">Sesión inválida</p>';
            return;
        }
        $id_paciente = (int)($_GET['id_paciente'] ?? 0);
        $id_medico   = (int)$_SESSION['user_id'];

        if ($id_paciente <= 0) {
            echo '<p>ID de paciente inválido</p>';
            return;
        }

        $detalle = $this->model->obtenerDetallePaciente($id_paciente, $id_medico);
        $carpetas = $detalle['carpetas'];
        $sin_carpeta = $detalle['sin_carpeta'];

        ob_start();
        ?>
        <div class="paciente-detalle-container" style="padding: 16px; background: var(--surface2); border-radius: var(--radius); margin-top: 10px; border: 1px solid var(--border);">
            
            <?php if (empty($carpetas) && empty($sin_carpeta)): ?>
                <p class="text-muted text-sm">No hay análisis para este paciente.</p>
            <?php else: ?>
                <input type="text" class="form-input-d folder-search-input" style="width: 100%; margin-bottom: 12px;" placeholder="Buscar carpeta..." onkeyup="filterFolders(this)">
                
                <!-- Carpetas -->
                <?php foreach ($carpetas as $c): ?>
                    <div class="carpeta-box" style="margin-bottom: 12px;">
                        <div class="carpeta-header flex items-center gap-8" style="margin-bottom: 8px; cursor: pointer;" onclick="toggleAnalisis('folder-<?= $c['id'] ?>')">
                            <span style="font-size: 16px;">📂</span>
                            <strong style="font-size: 13px; color: var(--text);"><?= htmlspecialchars($c['nombre']) ?></strong>
                            <span class="badge badge-info"><?= $c['total_analisis'] ?> análisis</span>
                        </div>
                        <div id="folder-<?= $c['id'] ?>" class="carpeta-analisis" style="display: none; padding-left: 24px; border-left: 2px solid var(--border); margin-left: 8px;">
                            <?php $analisisCarpeta = (new CarpetaModel())->obtenerAnalisisDeCarpeta($c['id'], $id_medico); ?>
                            <?php if(empty($analisisCarpeta)): ?>
                                <p class="text-muted text-sm">Carpeta vacía.</p>
                            <?php else: ?>
                                <?php foreach($analisisCarpeta as $a): 
                                    $this->renderAnalisisRow($a);
                                endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Sin Carpeta -->
                <?php if (!empty($sin_carpeta)): ?>
                    <div class="carpeta-box">
                        <div class="carpeta-header flex items-center gap-8" style="margin-bottom: 8px; cursor: pointer;" onclick="toggleAnalisis('folder-none')">
                            <span style="font-size: 16px;">📁</span>
                            <strong style="font-size: 13px; color: var(--text);">Análisis sin carpeta</strong>
                            <span class="badge badge-info"><?= count($sin_carpeta) ?> análisis</span>
                        </div>
                        <div id="folder-none" class="carpeta-analisis" style="display: none; padding-left: 24px; border-left: 2px solid var(--border); margin-left: 8px;">
                            <?php foreach($sin_carpeta as $a): 
                                $this->renderAnalisisRow($a);
                            endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
        echo ob_get_clean();
    }

    public function recuperar_codigo_historial() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }

        if (!isset($_SESSION['user_id']) || $_SESSION['rol_codigo'] !== 'MED') {
            echo json_encode([
                'success' => false,
                'error'   => 'Su sesión expiró por inactividad. Por favor ingrese nuevamente.',
                'expired' => true
            ]);
            return;
        }

        $dni = trim($_POST['dni'] ?? '');
        if (strlen($dni) !== 8 || !ctype_digit($dni)) {
            echo json_encode([
                'success' => false,
                'error'   => 'El DNI debe contener exactamente 8 dígitos numéricos.'
            ]);
            return;
        }

        try {
            $paciente = $this->model->recuperarCodigoHistorialPorDNI($dni, (int)$_SESSION['user_id']);
            if (!$paciente) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'No se encontró un paciente registrado con ese DNI en su historial.'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'paciente' => [
                    'id'              => (int)$paciente['id'],
                    'dni'             => $paciente['dni'],
                    'codigo_paciente' => $paciente['codigo_paciente']
                ],
                'message' => 'El código de historial de este paciente es: ' . $paciente['codigo_paciente']
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'No se pudo realizar la búsqueda en este momento. Intente nuevamente.'
            ]);
        }
    }

    private function renderAnalisisRow($a) {
        $color = 'var(--success)';
        $badge = 'badge-active';
        $badgeText = 'Normal';
        if ($a['alerta_anomalia']) {
            $color = 'var(--danger)';
            $badge = 'badge-pending';
            $badgeText = 'Alerta';
        }
        $fecha = date('d M Y H:i', strtotime($a['fecha_analisis']));
        $prob = number_format($a['probabilidad_principal'], 1) . '%';
        $resultado = htmlspecialchars(ucfirst($a['resultado_principal']));
        ?>
        <div class="flex items-center gap-12" style="padding: 8px 0; border-bottom: 1px dashed var(--border);">
            <div style="width:8px; height:8px; border-radius:50%; background:<?= $color ?>;"></div>
            <div style="flex:1;">
                <p style="font-size:12px; font-weight:600; color:var(--text);"><?= $resultado ?> · <?= $prob ?></p>
                <p style="font-size:11px; color:var(--text3);"><?= $fecha ?></p>
            </div>
            <span class="badge <?= $badge ?>"><?= $badgeText ?></span>
            <button class="btn btn-ghost btn-sm" onclick="descargarPDF(<?= $a['id'] ?>)">PDF</button>
        </div>
        <?php
    }
}

if (isset($_GET['action'])) {
    $controller = new PacienteController();
    switch ($_GET['action']) {
        case 'buscar_registrar': $controller->buscar_registrar(); break;
        case 'detalle_html':     $controller->detalle_html(); break;
        case 'recuperar_codigo': $controller->recuperar_codigo_historial(); break;
    }
}
