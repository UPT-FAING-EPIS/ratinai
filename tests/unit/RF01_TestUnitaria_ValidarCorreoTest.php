<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../services/MailService.php';

class MailServiceTest extends TestCase {

    public function testSendVerificationCodeInvalidEmail() {
        $resultado = MailService::sendVerificationCode('correo-invalido', 'Juan', '123456');
        $this->assertFalse($resultado, "Debe retornar false al proveer un correo inválido.");
    }

    public function testSendSolicitudAprobadaInvalidEmail() {
        $resultado = MailService::sendSolicitudAprobada('correo@invalido@com', 'Juan', 'Clinica');
        $this->assertFalse($resultado, "Debe retornar false al proveer un correo inválido.");
    }

    public function testSendTempPasswordInvalidEmail() {
        $resultado = MailService::sendTempPassword('correo-invalido', 'Juan', 'pass123');
        $this->assertFalse($resultado, "Debe retornar false al proveer un correo inválido.");
    }
}
