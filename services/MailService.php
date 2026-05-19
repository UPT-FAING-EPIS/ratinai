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
}
