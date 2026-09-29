<?php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
}

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../controllers/PacienteController.php';

class RF07_TestUnitaria_RecuperarCodigoHistorialTest extends TestCase {
    private $controller;
    private $modelMock;
    private $previousErrorHandler;

    protected function setUp(): void {
        $this->previousErrorHandler = set_error_handler(function ($severity, $message) {
            return str_contains($message, 'Cannot modify header information');
        });
        $this->modelMock = $this->createMock(PacienteModel::class);
        $reflection = new ReflectionClass(PacienteController::class);
        $this->controller = $reflection->newInstanceWithoutConstructor();

        $property = $reflection->getProperty('model');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->modelMock);
    }

    protected function tearDown(): void {
        restore_error_handler();
        $_SERVER = [];
        $_POST = [];
        $_SESSION = [];
        parent::tearDown();
    }

    public function testRechazaDniConFormatoInvalidoSinConsultarElModelo(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION = ['user_id' => 5, 'rol_codigo' => 'MED'];
        $_POST['dni'] = '1234ABCD';

        $this->modelMock->expects($this->never())->method('recuperarCodigoHistorialPorDNI');
        ob_start();
        $this->controller->recuperar_codigo_historial();
        $respuesta = json_decode(ob_get_clean(), true);

        $this->assertFalse($respuesta['success']);
        $this->assertSame('El DNI debe contener exactamente 8 dígitos numéricos.', $respuesta['error']);
    }

    public function testDevuelveElCodigoParaUnDniDelHistorialDelMedico(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION = ['user_id' => 5, 'rol_codigo' => 'MED'];
        $_POST['dni'] = '76352371';

        $this->modelMock->expects($this->once())
            ->method('recuperarCodigoHistorialPorDNI')
            ->with('76352371', 5)
            ->willReturn(['id' => 7, 'dni' => '76352371', 'codigo_paciente' => 'PAC-54321']);

        ob_start();
        $this->controller->recuperar_codigo_historial();
        $respuesta = json_decode(ob_get_clean(), true);

        $this->assertTrue($respuesta['success']);
        $this->assertSame('PAC-54321', $respuesta['paciente']['codigo_paciente']);
    }
}
