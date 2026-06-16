<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../controllers/AnalisisController.php';
require_once __DIR__ . '/../../models/AnalisisModel.php';
require_once __DIR__ . '/../../models/PacienteModel.php';

class AnalisisControllerTest extends TestCase {
    private $analisisController;
    private $analisisModelMock;
    private $pacienteModelMock;

    protected function setUp(): void {
        $this->analisisController = new AnalisisController();

        // Mock dependencies
        $this->analisisModelMock = $this->createMock(AnalisisModel::class);
        $this->pacienteModelMock = $this->createMock(PacienteModel::class);

        // Use reflection to set the private mocked model properties
        $reflector = new ReflectionClass(AnalisisController::class);

        $modelProperty = $reflector->getProperty('model');
        $modelProperty->setAccessible(true);
        $modelProperty->setValue($this->analisisController, $this->analisisModelMock);
    }

    protected function tearDown(): void {
        // Clean up superglobals
        $_SERVER = [];
        $_POST = [];
        $_FILES = [];
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAnalizarFailsWithInvalidHttpMethod() {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->analisisController->analizar();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Método no permitido', $response['error']);
    }

    public function testAnalizarFailsWithoutSession() {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        ob_start();
        $this->analisisController->analizar();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Su sesión ha expirado. Por favor inicie sesión nuevamente.', $response['error']);
        $this->assertTrue($response['expired']);
    }

    public function testAnalizarFailsWithNoFile() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = 1;
        $_SESSION['rol_codigo'] = 'MED';

        ob_start();
        $this->analisisController->analizar();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Error al subir la imagen', $response['error']);
    }

    public function testAnalizarFailsWithInvalidFileType() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = 1;
        $_SESSION['rol_codigo'] = 'MED';
        $_FILES['imagen'] = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/php1234',
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        ob_start();
        $this->analisisController->analizar();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Formato no permitido. Por favor sube una imagen en formato JPG o PNG.', $response['error']);
    }

    public function testAnalizarFailsWithFileSizeTooLarge() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = 1;
        $_SESSION['rol_codigo'] = 'MED';
        $_FILES['imagen'] = [
            'name' => 'large_image.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/php1234',
            'error' => UPLOAD_ERR_OK,
            'size' => 11 * 1024 * 1024 // 11 MB
        ];

        ob_start();
        $this->analisisController->analizar();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('El archivo supera el tamaño máximo permitido de 10 MB.', $response['error']);
    }
}
