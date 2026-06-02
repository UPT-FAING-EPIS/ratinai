<?php
/**
 * controllers/SolicitudController.php
 * RF-08 — Maneja la solicitud de registro de centros oftalmológicos
 *         y la gestión (aprobar/rechazar) por el Super Administrador.
 */

require_once __DIR__ . '/../config/session_guard.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../services/MailService.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ── Enviar código de verificación ────────────────────────────────────────
    case 'send_code':
        handleSendCode();
        break;

    // ── RF-08: Envío del formulario público ──────────────────────────────────
    case 'solicitar':
        handleSolicitar();
        break;

    // ── ADM: Envío desde el admin ────────────────────────────────────────────
    case 'solicitar_admin':
        require_role('ADM');
        handleSolicitarAdmin();
        break;

    // ── SAD: Aprobar solicitud ───────────────────────────────────────────────
    case 'aprobar':
        require_role('SAD');
        handleAprobar();
        break;

    // ── SAD: Rechazar solicitud ──────────────────────────────────────────────
    case 'rechazar':
        require_role('SAD');
        handleRechazar();
        break;

    default:
        header('Location: ../index.php');
        exit;
}

// ────────────────────────────────────────────────────────────────────────────
// FUNCIONES
// ────────────────────────────────────────────────────────────────────────────

function handleSendCode(): void
{
    header('Content-Type: application/json');
    $correo = trim($_POST['correo'] ?? '');
    $nombres = trim($_POST['nombres'] ?? 'Solicitante');

    if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Correo inválido.']);
        exit;
    }

    $codigo = sprintf("%06d", mt_rand(1, 999999));
    $_SESSION['verification_code'] = $codigo;
    $_SESSION['verification_email'] = $correo;
    $_SESSION['verification_time'] = time();

    $sent = MailService::sendVerificationCode($correo, $nombres, $codigo);

    if ($sent) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo de verificación.']);
    }
    exit;
}

function handleSolicitar(): void
{
    $base = get_base_path();
    $redirect_form = $base . 'views/auth/solicitud_registro.php';

    // Recoger y sanear datos Centro
    $nombre_centro    = trim($_POST['nombre_centro']    ?? '');
    $direccion        = trim($_POST['direccion']        ?? '');
    $tipo             = trim($_POST['tipo']             ?? '');
    $ruc              = trim($_POST['ruc']              ?? '');
    
    // Datos Titular
    $dni_titular      = trim($_POST['dni_titular']      ?? '');
    $nombres_titular  = trim($_POST['nombres_titular']  ?? '');
    $apellidos_titular= trim($_POST['apellidos_titular']?? '');
    $telefono         = trim($_POST['telefono']         ?? '');
    $correo_contacto  = trim($_POST['correo_contacto']  ?? '');
    $codigo_verificacion = trim($_POST['codigo_verificacion'] ?? '');

    // Evidencias Base64
    $evidencia_1_b64    = $_POST['evidencia_1_b64'] ?? null;
    $evidencia_1_nombre = $_POST['evidencia_1_nombre'] ?? null;
    $evidencia_2_b64    = $_POST['evidencia_2_b64'] ?? null;
    $evidencia_2_nombre = $_POST['evidencia_2_nombre'] ?? null;

    // ── Validaciones de servidor ─────────────────────────────────────────────

    if (!$nombre_centro || !$direccion || !$tipo || !$ruc || !$dni_titular || !$nombres_titular || !$apellidos_titular || !$telefono || !$correo_contacto || !$codigo_verificacion) {
        $_SESSION['solicitud_error'] = 'Por favor, complete todos los campos requeridos antes de enviar.';
        header('Location: ' . $redirect_form);
        exit;
    }

    // Validar código OTP
    if (!isset($_SESSION['verification_code']) || !isset($_SESSION['verification_email'])) {
        $_SESSION['solicitud_error'] = 'Debe solicitar un código de verificación primero.';
        header('Location: ' . $redirect_form);
        exit;
    }
    if ($_SESSION['verification_email'] !== $correo_contacto) {
        $_SESSION['solicitud_error'] = 'El correo electrónico no coincide con el verificado.';
        header('Location: ' . $redirect_form);
        exit;
    }
    if (time() - $_SESSION['verification_time'] > 600) {
        $_SESSION['solicitud_error'] = 'El código de verificación ha expirado. Solicite uno nuevo.';
        header('Location: ' . $redirect_form);
        exit;
    }
    if ($_SESSION['verification_code'] !== $codigo_verificacion) {
        $_SESSION['solicitud_error'] = 'El código de verificación es incorrecto.';
        header('Location: ' . $redirect_form);
        exit;
    }

    if (!in_array($tipo, ['publico', 'privado'], true)) {
        $_SESSION['solicitud_error'] = 'Tipo de establecimiento no válido.';
        header('Location: ' . $redirect_form);
        exit;
    }

    if (!preg_match('/^\d{11}$/', $ruc)) {
        $_SESSION['solicitud_error'] = 'El RUC ingresado no es válido.';
        header('Location: ' . $redirect_form);
        exit;
    }

    if (!preg_match('/^\d{8}$/', $dni_titular)) {
        $_SESSION['solicitud_error'] = 'El DNI ingresado no es válido.';
        header('Location: ' . $redirect_form);
        exit;
    }

    if (empty($evidencia_1_b64)) {
        $_SESSION['solicitud_error'] = 'Debe adjuntar al menos una evidencia.';
        header('Location: ' . $redirect_form);
        exit;
    }

    try {
        $db = (new Database())->getConnection();

        // ── Verificar duplicados RUC ───────────────────────
        $stmtEst = $db->prepare("SELECT COUNT(*) FROM establecimientos WHERE ruc = ?");
        $stmtEst->execute([$ruc]);
        if ((int)$stmtEst->fetchColumn() > 0) {
            $_SESSION['solicitud_error'] = 'Ya existe un centro registrado con este RUC.';
            header('Location: ' . $redirect_form);
            exit;
        }

        $stmtSol = $db->prepare(
            "SELECT COUNT(*) FROM solicitudes_establecimiento WHERE ruc = ? AND estado IN ('pendiente','aprobado')"
        );
        $stmtSol->execute([$ruc]);
        if ((int)$stmtSol->fetchColumn() > 0) {
            $_SESSION['solicitud_error'] = 'Ya existe una solicitud en proceso con este RUC.';
            header('Location: ' . $redirect_form);
            exit;
        }

        // ── Insertar solicitud ────────────────────────
        $stmt = $db->prepare(
            "INSERT INTO solicitudes_establecimiento
                (nombre_centro, direccion, tipo, ruc, dni_titular, nombres_titular, apellidos_titular, telefono, correo_contacto, evidencia_1, evidencia_1_nombre, evidencia_2, evidencia_2_nombre, estado, fecha_solicitud)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())"
        );
        $stmt->execute([
            $nombre_centro, $direccion, $tipo, $ruc,
            $dni_titular, $nombres_titular, $apellidos_titular, $telefono, $correo_contacto,
            $evidencia_1_b64, $evidencia_1_nombre, 
            $evidencia_2_b64 ?: null, $evidencia_2_nombre ?: null
        ]);

        // Limpiar sesión de OTP
        unset($_SESSION['verification_code'], $_SESSION['verification_email'], $_SESSION['verification_time']);

        // ── Éxito ────────────────────────────────────────────────────────────
        $_SESSION['solicitud_success'] = 'Su solicitud ha sido enviada correctamente. El administrador revisará su información y le notificará el resultado.';
        header('Location: ' . $redirect_form);
        exit;

    } catch (Exception $ex) {
        $_SESSION['solicitud_error'] = 'Ocurrió un error al procesar su solicitud. Error: ' . $ex->getMessage();
        header('Location: ' . $redirect_form);
        exit;
    }
}

// ────────────────────────────────────────────────────────────────────────────

function handleAprobar(): void
{
    $base = get_base_path();
    $id   = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        header('Location: ' . $base . 'views/superadmin/SolicitudesRegistro.php');
        exit;
    }

    try {
        $db = (new Database())->getConnection();

        // Obtener solicitud
        $stmt = $db->prepare(
            "SELECT * FROM solicitudes_establecimiento WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$id]);
        $sol = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sol) {
            $_SESSION['solicitud_msg'] = 'La solicitud no existe o ya fue procesada.';
            header('Location: ' . $base . 'views/superadmin/SolicitudesRegistro.php');
            exit;
        }

        // Crear el establecimiento en la tabla principal
        $ins = $db->prepare(
            "INSERT INTO establecimientos (nombre, direccion, tipo, ruc)
             VALUES (?, ?, ?, ?)"
        );
        $ins->execute([$sol['nombre_centro'], $sol['direccion'], $sol['tipo'], $sol['ruc']]);
        $establecimiento_id = $db->lastInsertId();

        // Marcar solicitud como aprobada
        $upd = $db->prepare(
            "UPDATE solicitudes_establecimiento SET estado = 'aprobado' WHERE id = ?"
        );
        $upd->execute([$id]);

        // TODO: Crear cuenta de usuario Admin de Establecimiento
        $tempPass = bin2hex(random_bytes(4)); // 8 caracteres hex
        $hashPass = password_hash($tempPass, PASSWORD_DEFAULT);
        
        $insUser = $db->prepare("
            INSERT INTO usuarios (rol_codigo, establecimiento_id, nombre, correo, password, es_password_temporal, activo) 
            VALUES ('ADM', ?, ?, ?, ?, 1, 1)
        ");
        $nombreCompleto = trim($sol['nombres_titular'] . ' ' . $sol['apellidos_titular']);
        $insUser->execute([
            $establecimiento_id,
            $nombreCompleto,
            $sol['correo_contacto'],
            $hashPass
        ]);

        // Enviar correo de aprobación
        MailService::sendSolicitudAprobada($sol['correo_contacto'], $nombreCompleto, $sol['nombre_centro']);
        
        // Enviar contraseña temporal (reutilizando el método existente o similar)
        // Por simplicidad, enviaremos la pass temporal usando el mismo método que envía a los médicos
        MailService::sendTempPassword($sol['correo_contacto'], $nombreCompleto, $tempPass);

        $_SESSION['solicitud_msg'] = "Solicitud aprobada. El centro «{$sol['nombre_centro']}» ha sido registrado y se enviaron las credenciales al titular.";

    } catch (Exception $ex) {
        $_SESSION['solicitud_msg'] = 'Error al aprobar la solicitud. ' . $ex->getMessage();
    }

    header('Location: ' . $base . 'views/superadmin/SolicitudesRegistro.php');
    exit;
}

// ────────────────────────────────────────────────────────────────────────────

function handleRechazar(): void
{
    $base = get_base_path();
    $id   = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        header('Location: ' . $base . 'views/superadmin/SolicitudesRegistro.php');
        exit;
    }

    try {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare(
            "UPDATE solicitudes_establecimiento SET estado = 'rechazado' WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$id]);
        $_SESSION['solicitud_msg'] = 'La solicitud ha sido rechazada.';

    } catch (Exception $ex) {
        $_SESSION['solicitud_msg'] = 'Error al rechazar la solicitud.';
    }

    header('Location: ' . $base . 'views/superadmin/SolicitudesRegistro.php');
    exit;
}

// ────────────────────────────────────────────────────────────────────────────

function handleSolicitarAdmin(): void
{
    $base = get_base_path();
    $redirect_form = $base . 'views/admin/nuevoestablecimiento.php';

    // Recoger y sanear datos Centro
    $nombre_centro    = trim($_POST['nombre_centro']    ?? '');
    $direccion        = trim($_POST['direccion']        ?? '');
    $tipo             = trim($_POST['tipo']             ?? '');
    $ruc              = trim($_POST['ruc']              ?? '');
    
    // Datos Titular
    $dni_titular      = trim($_POST['dni_titular']      ?? '');
    $nombres_titular  = trim($_POST['nombres_titular']  ?? '');
    $apellidos_titular= trim($_POST['apellidos_titular']?? '');
    $telefono         = trim($_POST['telefono']         ?? '');
    
    // El correo es el del usuario admin actual
    $user = current_user();
    if (!$user || empty($user['id'])) {
        header('Location: ' . $base . 'views/auth/login.php');
        exit;
    }

    // Evidencias Base64
    $evidencia_1_b64    = $_POST['evidencia_1_b64'] ?? null;
    $evidencia_1_nombre = $_POST['evidencia_1_nombre'] ?? null;
    $evidencia_2_b64    = $_POST['evidencia_2_b64'] ?? null;
    $evidencia_2_nombre = $_POST['evidencia_2_nombre'] ?? null;

    if (!$nombre_centro || !$direccion || !$tipo || !$ruc || !$dni_titular || !$nombres_titular || !$apellidos_titular || !$telefono) {
        $_SESSION['solicitud_error'] = 'Por favor, complete todos los campos requeridos.';
        header('Location: ' . $redirect_form);
        exit;
    }

    if (!in_array($tipo, ['publico', 'privado'], true)) {
        $_SESSION['solicitud_error'] = 'Tipo de establecimiento no válido.';
        header('Location: ' . $redirect_form);
        exit;
    }
    if (!preg_match('/^\d{11}$/', $ruc)) {
        $_SESSION['solicitud_error'] = 'El RUC ingresado no es válido.';
        header('Location: ' . $redirect_form);
        exit;
    }
    if (!preg_match('/^\d{8}$/', $dni_titular)) {
        $_SESSION['solicitud_error'] = 'El DNI ingresado no es válido.';
        header('Location: ' . $redirect_form);
        exit;
    }
    if (empty($evidencia_1_b64)) {
        $_SESSION['solicitud_error'] = 'Debe adjuntar al menos una evidencia.';
        header('Location: ' . $redirect_form);
        exit;
    }

    try {
        $db = (new Database())->getConnection();

        // Obtener el correo del usuario
        $stmtCorreo = $db->prepare("SELECT correo FROM usuarios WHERE id = ?");
        $stmtCorreo->execute([$user['id']]);
        $correo_contacto = $stmtCorreo->fetchColumn();

        if (!$correo_contacto) {
            $_SESSION['solicitud_error'] = 'No se pudo obtener el correo de su usuario.';
            header('Location: ' . $redirect_form);
            exit;
        }

        $stmtEst = $db->prepare("SELECT COUNT(*) FROM establecimientos WHERE ruc = ?");
        $stmtEst->execute([$ruc]);
        if ((int)$stmtEst->fetchColumn() > 0) {
            $_SESSION['solicitud_error'] = 'Ya existe un centro registrado con este RUC.';
            header('Location: ' . $redirect_form);
            exit;
        }

        $stmtSol = $db->prepare("SELECT COUNT(*) FROM solicitudes_establecimiento WHERE ruc = ? AND estado IN ('pendiente','aprobado')");
        $stmtSol->execute([$ruc]);
        if ((int)$stmtSol->fetchColumn() > 0) {
            $_SESSION['solicitud_error'] = 'Ya existe una solicitud en proceso con este RUC.';
            header('Location: ' . $redirect_form);
            exit;
        }

        $stmt = $db->prepare(
            "INSERT INTO solicitudes_establecimiento
                (nombre_centro, direccion, tipo, ruc, dni_titular, nombres_titular, apellidos_titular, telefono, correo_contacto, evidencia_1, evidencia_1_nombre, evidencia_2, evidencia_2_nombre, estado, fecha_solicitud)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())"
        );
        $stmt->execute([
            $nombre_centro, $direccion, $tipo, $ruc,
            $dni_titular, $nombres_titular, $apellidos_titular, $telefono, $correo_contacto,
            $evidencia_1_b64, $evidencia_1_nombre, 
            $evidencia_2_b64 ?: null, $evidencia_2_nombre ?: null
        ]);

        $_SESSION['solicitud_success'] = 'Su solicitud ha sido enviada correctamente.';
        header('Location: ' . $base . 'views/admin/misestablecimientos.php');
        exit;

    } catch (Exception $ex) {
        $_SESSION['solicitud_error'] = 'Ocurrió un error: ' . $ex->getMessage();
        header('Location: ' . $redirect_form);
        exit;
    }
}
