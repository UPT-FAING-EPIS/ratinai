<?php
use PHPUnit\Framework\TestCase;

// Como los modelos usan clases reales que dependen de la BD asumiendo require config/config.php,
// simularemos la estructura mediante un mock.
require_once __DIR__ . '/../../models/DoctorModel.php';

class DoctorModelTest extends TestCase {

    private $dbMock;
    private $stmtMock;
    private $doctorModel;

    protected function setUp(): void {
        // 1. Mock de PDOStatement (stmt)
        $this->stmtMock = $this->createMock(\PDOStatement::class);

        // 2. Mock de PDO
        $this->dbMock = $this->createMock(\PDO::class);

        // 3. Crear una clase anónima o usar reflexión para inyectar $this->dbMock 
        // a DoctorModel sin pasar por la configuración real de DB si la clase ya tiene constructor.
        
        $this->doctorModel = $this->getMockBuilder(DoctorModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Usar reflexión para setear la propiedad privada 'db' en DoctorModel
        $reflection = new \ReflectionClass(DoctorModel::class);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->doctorModel, $this->dbMock);
    }

    public function testResetPassword() {
        // Datos de entrada de prueba
        $doctorId = 15;
        $estId = 2;
        $nuevoHash = password_hash('NuevaTemp123', PASSWORD_BCRYPT);

        // Configuramos el mock de statement
        $this->stmtMock->expects($this->once())
             ->method('execute')
             ->with([
                 ':pwd' => $nuevoHash,
                 ':id'  => $doctorId,
                 ':eid' => $estId
             ])
             ->willReturn(true);

        // Configuramos el mock de base de datos para esperar la query SQL correcta
        $expectedSql = "UPDATE usuarios SET password=:pwd, es_password_temporal=1
             WHERE id=:id AND establecimiento_id=:eid AND rol_codigo='MED'";
             
        // Limpiamos los saltos de línea extra para que el assert coincida si la clase tiene espacios
        $this->dbMock->expects($this->once())
             ->method('prepare')
             ->with($this->callback(function($sql) {
                 return strpos($sql, 'es_password_temporal=1') !== false 
                        && strpos($sql, 'UPDATE usuarios SET password=:pwd') !== false;
             }))
             ->willReturn($this->stmtMock);

        // Ejecucción del método a probar
        $resultado = $this->doctorModel->resetPassword($doctorId, $estId, $nuevoHash);

        // Validaciones (Aserciones)
        $this->assertTrue($resultado, "El método resetPassword debería retornar true tras asignar exitosamente el hash temporal.");
    }
}