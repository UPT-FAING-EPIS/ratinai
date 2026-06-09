<?php
require_once __DIR__ . '/../config/config.php';

// Importar clases de PHPMailer
require_once __DIR__ . '/../libs/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService {
    /**
     * Envía un correo electrónico con la contraseña temporal usando PHPMailer (SMTP).
     * 
     * @param string $correo Correo destino
     * @param string $nombre Nombre del médico
     * @param string $tempPass Contraseña temporal
     * @return bool Retorna true si se envió, false si falló.
     */
    public static function sendTempPassword(string $correo, string $nombre, string $tempPass): bool {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Si no se han configurado los credenciales SMTP (aún están con los valores por defecto)
        // abortamos para activar el fallback visual que pide el diseño.
        if (SMTP_USER === 'tu_correo@gmail.com' || empty(SMTP_USER)) {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            // Remitente y destinatario
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($correo, $nombre);

            // Contenido
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Acceso a RetinAI - Su cuenta ha sido creada';
            
            $loginUrl = 'https://retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net/views/auth/login.php';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            <body style='margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='table-layout: fixed; background-color: #f8fafc; padding: 40px 20px;'>
                    <tr>
                        <td align='center'>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.04);'>
                                
                                <!-- Header -->
                                <tr>
                                    <td align='center' style='padding: 40px 0; background: linear-gradient(135deg, #1A56DB 0%, #1e40af 100%);'>
                                        <!-- Logo / Brand -->
                                        <h1 style='color: #ffffff; font-size: 28px; margin: 0; font-weight: 700; letter-spacing: -0.5px;'>
                                            <span style='color: #93c5fd;'>Retin</span>AI
                                        </h1>
                                        <p style='color: #bfdbfe; font-size: 14px; margin: 8px 0 0 0;'>Sistema de Análisis Oftalmológico</p>
                                    </td>
                                </tr>

                                <!-- Body -->
                                <tr>
                                    <td style='padding: 48px 40px;'>
                                        <h2 style='color: #0f172a; font-size: 22px; font-weight: 600; margin: 0 0 16px 0;'>¡Bienvenido, $nombre!</h2>
                                        
                                        <p style='color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 24px 0;'>
                                            Su cuenta como <strong>Médico Oftalmólogo</strong> ha sido creada exitosamente por su administrador de establecimiento. Ya puede acceder a nuestra plataforma para gestionar historiales y realizar análisis de retinopatía.
                                        </p>

                                        <p style='color: #475569; font-size: 15px; margin: 0 0 12px 0;'>Su contraseña temporal de acceso es:</p>

                                        <!-- Password Box -->
                                        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-bottom: 32px;'>
                                            <tr>
                                                <td align='center' style='background-color: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 20px;'>
                                                    <span style='font-family: \"Courier New\", Courier, monospace; font-size: 24px; font-weight: bold; color: #1e293b; letter-spacing: 3px;'>
                                                        $tempPass
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>

                                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                            <tr>
                                                <td align='center'>
                                                    <a href='$loginUrl' style='display: inline-block; background-color: #1A56DB; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 8px; transition: background-color 0.2s;'>
                                                        Iniciar Sesión Ahora
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style='color: #64748b; font-size: 13px; line-height: 1.5; margin: 32px 0 0 0; text-align: center;'>
                                            <span style='color: #ef4444; font-weight: 600;'>Importante:</span> Por razones de seguridad, el sistema le solicitará cambiar esta contraseña temporal por una nueva en su primer acceso.
                                        </p>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style='background-color: #f8fafc; padding: 24px 40px; border-top: 1px solid #e2e8f0; text-align: center;'>
                                        <p style='color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;'>
                                            © " . date('Y') . " RetinAI. Todos los derechos reservados.<br>
                                            Este es un mensaje automático, por favor no responda a este correo.
                                        </p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            $mail->AltBody = "Hola $nombre,\n\nSu cuenta ha sido creada. Su contraseña temporal es: $tempPass\n\nPor favor, inicie sesión y cambie su contraseña.";

            $mail->send();
            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Envía un correo electrónico de reseteo de contraseña usando PHPMailer.
     * 
     * @param string $correo Correo destino
     * @param string $nombre Nombre del médico
     * @param string $tempPass Contraseña temporal
     * @return bool Retorna true si se envió, false si falló.
     */
    public static function sendResetPassword(string $correo, string $nombre, string $tempPass): bool {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (SMTP_USER === 'tu_correo@gmail.com' || empty(SMTP_USER)) {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($correo, $nombre);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Reseteo de contraseña de RetinAI';
            
            $loginUrl = 'https://retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net/views/auth/login.php';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            <body style='margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='table-layout: fixed; background-color: #f8fafc; padding: 40px 20px;'>
                    <tr>
                        <td align='center'>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.04);'>
                                <tr>
                                    <td align='center' style='padding: 40px 0; background: linear-gradient(135deg, #1A56DB 0%, #1e40af 100%);'>
                                        <h1 style='color: #ffffff; font-size: 28px; margin: 0; font-weight: 700; letter-spacing: -0.5px;'>
                                            <span style='color: #93c5fd;'>Retin</span>AI
                                        </h1>
                                        <p style='color: #bfdbfe; font-size: 14px; margin: 8px 0 0 0;'>Sistema de Análisis Oftalmológico</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='padding: 48px 40px;'>
                                        <h2 style='color: #0f172a; font-size: 22px; font-weight: 600; margin: 0 0 16px 0;'>Hola, $nombre.</h2>
                                        
                                        <p style='color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 24px 0;'>
                                            Su administrador ha reseteado su contraseña de acceso a nuestra plataforma.
                                        </p>

                                        <p style='color: #475569; font-size: 15px; margin: 0 0 12px 0;'>Su nueva contraseña temporal de acceso es:</p>

                                        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-bottom: 32px;'>
                                            <tr>
                                                <td align='center' style='background-color: #0f172a; border-radius: 12px; padding: 24px;'>
                                                    <span style='font-family: \"Courier New\", Courier, monospace; font-size: 28px; font-weight: bold; color: #ffffff; letter-spacing: 4px;'>
                                                        $tempPass
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>

                                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                            <tr>
                                                <td align='center'>
                                                    <a href='$loginUrl' style='display: inline-block; background-color: #1A56DB; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 8px; transition: background-color 0.2s;'>
                                                        Iniciar Sesión Ahora
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style='color: #64748b; font-size: 13px; line-height: 1.5; margin: 32px 0 0 0; text-align: center;'>
                                            <span style='color: #ef4444; font-weight: 600;'>Importante:</span> Por razones de seguridad, deberá cambiar esta contraseña temporal por una nueva en su próximo ingreso.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='background-color: #f8fafc; padding: 24px 40px; border-top: 1px solid #e2e8f0; text-align: center;'>
                                        <p style='color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;'>
                                            © " . date('Y') . " RetinAI. Todos los derechos reservados.<br>
                                            Este es un mensaje automático, por favor no responda a este correo.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            $mail->AltBody = "Hola $nombre,\n\nSu contraseña ha sido reseteada. Su nueva contraseña temporal es: $tempPass\n\nPor favor, inicie sesión y cámbiela.";

            $mail->send();
            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Envía un código de verificación de 6 dígitos al correo del solicitante.
     *
     * @param string $correo Correo destino
     * @param string $nombre Nombre del titular
     * @param string $codigo Código de 6 dígitos
     * @return bool
     */
    public static function sendVerificationCode(string $correo, string $nombre, string $codigo): bool {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (SMTP_USER === 'tu_correo@gmail.com' || empty(SMTP_USER)) {
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($correo, $nombre);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'RetinAI — Código de verificación para registro';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head><meta charset='UTF-8'></head>
            <body style='margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='padding:40px 20px;background:#f8fafc;'>
                    <tr><td align='center'>
                        <table width='100%' cellpadding='0' cellspacing='0' style='max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.05);'>
                            <tr>
                                <td align='center' style='padding:36px 0;background:linear-gradient(135deg,#1A56DB 0%,#1e40af 100%);'>
                                    <h1 style='color:#fff;font-size:26px;margin:0;font-weight:700;letter-spacing:-.5px;'><span style='color:#93c5fd;'>Retin</span>AI</h1>
                                    <p style='color:#bfdbfe;font-size:13px;margin:6px 0 0;'>Sistema de Análisis Oftalmológico</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:44px 40px;'>
                                    <h2 style='color:#0f172a;font-size:20px;font-weight:600;margin:0 0 14px;'>Hola, $nombre</h2>
                                    <p style='color:#475569;font-size:15px;line-height:1.6;margin:0 0 28px;'>
                                        Ha solicitado verificar su correo electrónico para el registro de un centro oftalmológico en RetinAI. Use el código siguiente:
                                    </p>
                                    <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:28px;'>
                                        <tr>
                                            <td align='center' style='background:#0f172a;border-radius:12px;padding:22px;'>
                                                <span style='font-family:\"Courier New\",monospace;font-size:34px;font-weight:700;color:#fff;letter-spacing:10px;'>$codigo</span>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style='color:#64748b;font-size:13px;text-align:center;margin:0;'>
                                        <span style='color:#ef4444;font-weight:600;'>⏱ Este código expira en 10 minutos.</span><br>
                                        Si no solicitó este código, puede ignorar este correo.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;'>
                                    <p style='color:#94a3b8;font-size:12px;margin:0;'>
                                        &copy; " . date('Y') . " RetinAI. Todos los derechos reservados.<br>
                                        Mensaje automático, no responda este correo.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td></tr>
                </table>
            </body>
            </html>";

            $mail->AltBody = "Hola $nombre,\n\nSu código de verificación es: $codigo\n\nExpira en 10 minutos. Si no solicitó este código, ignore este mensaje.";

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Notifica al titular que su solicitud de registro fue aprobada.
     *
     * @param string $correo         Correo del titular
     * @param string $nombre         Nombre del titular
     * @param string $nombreCentro   Nombre del centro aprobado
     * @return bool
     */
    public static function sendSolicitudAprobada(string $correo, string $nombre, string $nombreCentro): bool {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (SMTP_USER === 'tu_correo@gmail.com' || empty(SMTP_USER)) {
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($correo, $nombre);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'RetinAI — ¡Su solicitud fue aprobada!';

            $loginUrl = 'https://retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net/views/auth/login.php';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head><meta charset='UTF-8'></head>
            <body style='margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='padding:40px 20px;background:#f8fafc;'>
                    <tr><td align='center'>
                        <table width='100%' cellpadding='0' cellspacing='0' style='max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.05);'>
                            <tr>
                                <td align='center' style='padding:36px 0;background:linear-gradient(135deg,#1A56DB 0%,#1e40af 100%);'>
                                    <h1 style='color:#fff;font-size:26px;margin:0;font-weight:700;'><span style='color:#93c5fd;'>Retin</span>AI</h1>
                                    <p style='color:#bfdbfe;font-size:13px;margin:6px 0 0;'>Sistema de Análisis Oftalmológico</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:44px 40px;'>
                                    <div style='text-align:center;margin-bottom:28px;'>
                                        <div style='width:60px;height:60px;border-radius:50%;background:#d1fae5;border:2px solid #6ee7b7;display:inline-flex;align-items:center;justify-content:center;font-size:28px;'>✅</div>
                                    </div>
                                    <h2 style='color:#0f172a;font-size:20px;font-weight:700;margin:0 0 14px;text-align:center;'>¡Solicitud Aprobada!</h2>
                                    <p style='color:#475569;font-size:15px;line-height:1.6;margin:0 0 18px;'>
                                        Estimado/a <strong>$nombre</strong>, nos complace informarle que su solicitud de registro para el centro oftalmológico <strong>«$nombreCentro»</strong> ha sido <strong style='color:#059669;'>aprobada</strong> por el administrador del sistema RetinAI.
                                    </p>
                                    <p style='color:#475569;font-size:15px;line-height:1.6;margin:0 0 28px;'>
                                        En breve recibirá sus credenciales de acceso (usuario y contraseña temporal) para ingresar a la plataforma y comenzar a gestionar su centro.
                                    </p>
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td align='center'>
                                                <a href='$loginUrl' style='display:inline-block;background:#1A56DB;color:#fff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;'>
                                                    Ir a RetinAI
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style='background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;'>
                                    <p style='color:#94a3b8;font-size:12px;margin:0;'>
                                        &copy; " . date('Y') . " RetinAI. Todos los derechos reservados.<br>
                                        Mensaje automático, no responda este correo.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td></tr>
                </table>
            </body>
            </html>";

            $mail->AltBody = "Estimado/a {$nombre},\n\nSu solicitud para el centro «{$nombreCentro}» ha sido APROBADA.\nEn breve recibirá sus credenciales de acceso.\n\nRetinAI";

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Notifica al titular que los datos de su establecimiento fueron actualizados.
     *
     * @param string $correo         Correo del titular
     * @param string $nombre         Nombre del titular
     * @param string $nombreCentro   Nombre del centro actualizado
     * @return bool
     */
    public static function sendEstablecimientoActualizado(string $correo, string $nombre, string $nombreCentro): bool {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (SMTP_USER === 'tu_correo@gmail.com' || empty(SMTP_USER)) {
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($correo, $nombre);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'RetinAI — Datos de su establecimiento actualizados';

            $loginUrl = 'https://retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net/views/auth/login.php';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head><meta charset='UTF-8'></head>
            <body style='margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='padding:40px 20px;background:#f8fafc;'>
                    <tr><td align='center'>
                        <table width='100%' cellpadding='0' cellspacing='0' style='max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.05);'>
                            <tr>
                                <td align='center' style='padding:36px 0;background:linear-gradient(135deg,#1A56DB 0%,#1e40af 100%);'>
                                    <h1 style='color:#fff;font-size:26px;margin:0;font-weight:700;'><span style='color:#93c5fd;'>Retin</span>AI</h1>
                                    <p style='color:#bfdbfe;font-size:13px;margin:6px 0 0;'>Sistema de Análisis Oftalmológico</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:44px 40px;'>
                                    <div style='text-align:center;margin-bottom:28px;'>
                                        <div style='width:60px;height:60px;border-radius:50%;background:#e0f2fe;border:2px solid #7dd3fc;display:inline-flex;align-items:center;justify-content:center;font-size:28px;'>✏️</div>
                                    </div>
                                    <h2 style='color:#0f172a;font-size:20px;font-weight:700;margin:0 0 14px;text-align:center;'>Datos Actualizados</h2>
                                    <p style='color:#475569;font-size:15px;line-height:1.6;margin:0 0 18px;'>
                                        Estimado/a <strong>$nombre</strong>, le informamos que la información de su centro oftalmológico <strong>«$nombreCentro»</strong> ha sido actualizada por el equipo de administración de RetinAI.
                                    </p>
                                    <p style='color:#475569;font-size:15px;line-height:1.6;margin:0 0 28px;'>
                                        Puede ingresar a la plataforma para revisar los cambios y continuar gestionando su establecimiento.
                                    </p>
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td align='center'>
                                                <a href='$loginUrl' style='display:inline-block;background:#1A56DB;color:#fff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;'>
                                                    Ir a RetinAI
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style='background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;'>
                                    <p style='color:#94a3b8;font-size:12px;margin:0;'>
                                        &copy; " . date('Y') . " RetinAI. Todos los derechos reservados.<br>
                                        Mensaje automático, no responda este correo.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td></tr>
                </table>
            </body>
            </html>";

            $mail->AltBody = "Estimado/a {$nombre},\n\nLos datos de su centro «{$nombreCentro}» han sido actualizados por administración.\nPor favor ingrese a la plataforma para revisar los cambios.\n\nRetinAI";

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
