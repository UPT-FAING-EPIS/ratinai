<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/PacienteModel.php';

class PacienteModelTest extends TestCase {

    private $dbMock;
    private $stmtMock;
    private $pacienteModel;

    protected function setUp(): void {
        $this->stmtMock = $this->createMock(\PDOStatement::class);
        $this->dbMock = $this->createMock(\PDO::class);

        $this->pacienteModel = $this->getMockBuilder(PacienteModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $reflection = new \ReflectionClass(PacienteModel::class);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->pacienteModel, $this->dbMock);
    }

    public function testListarPacientesConAnalisisMedico() {
        $idMedico = 5;

        // Simulamos la respuesta de la BD
        $pacientesMock = [
            [
                'id' => 1,
                'dni' => '76352371',
                'codigo_paciente' => 'PAC-00001',
                'total_analisis' => 2,
                'total_carpetas' => 1,
                'total_alertas' => 0
            ]
        ];

        $this->stmtMock->expects($this->once())
             ->method('execute')
             ->willReturn(true);

        $this->stmtMock->expects($this->once())
             ->method('fetchAll')
             ->with(\PDO::FETCH_ASSOC)
             ->willReturn($pacientesMock);

        $this->dbMock->expects($this->once())
             ->method('prepare')
             ->with($this->callback(function($sql) {
                 return strpos($sql, 'SELECT') !== false 
                        && strpos($sql, 'FROM pacientes p') !== false
                        && strpos($sql, 'INNER JOIN analisis_retinales a') !== false;
             }))
             ->willReturn($this->stmtMock);

        $resultado = $this->pacienteModel->listarPacientesConAnalisisMedico($idMedico);

        $this->assertIsArray($resultado);
        $this->assertCount(1, $resultado);
        $this->assertEquals('76352371', $resultado[0]['dni']);
    }

    public function testObtenerDetallePaciente() {
        $idPaciente = 1;
        $idMedico = 5;

        $carpetasMock = [
            ['id' => 1, 'nombre' => 'Testeos - Glaucoma', 'total_analisis' => 1]
        ];

        $sinCarpetaMock = [
            ['id' => 2, 'resultado_principal' => 'Catarata', 'probabilidad_principal' => 93.1]
        ];

        // Se ejecutarán 2 queries: una para carpetas y otra para sin carpeta
        $stmtCarpetasMock = $this->createMock(\PDOStatement::class);
        $stmtCarpetasMock->method('execute')->willReturn(true);
        $stmtCarpetasMock->method('fetchAll')->willReturn($carpetasMock);

        $stmtSinCarpetaMock = $this->createMock(\PDOStatement::class);
        $stmtSinCarpetaMock->method('execute')->willReturn(true);
        $stmtSinCarpetaMock->method('fetchAll')->willReturn($sinCarpetaMock);

        // match las queries
        $this->dbMock->expects($this->exactly(2))
             ->method('prepare')
             ->willReturnCallback(function($sql) use ($stmtCarpetasMock, $stmtSinCarpetaMock) {
                 if (strpos($sql, 'FROM carpetas_paciente c') !== false) {
                     return $stmtCarpetasMock;
                 }
                 if (strpos($sql, 'FROM analisis_retinales a') !== false) {
                     return $stmtSinCarpetaMock;
                 }
                 return $this->createMock(\PDOStatement::class);
             });

        $detalle = $this->pacienteModel->obtenerDetallePaciente($idPaciente, $idMedico);

        $this->assertIsArray($detalle);
        $this->assertArrayHasKey('carpetas', $detalle);
        $this->assertArrayHasKey('sin_carpeta', $detalle);
        
        $this->assertEquals('Testeos - Glaucoma', $detalle['carpetas'][0]['nombre']);
        $this->assertEquals('Catarata', $detalle['sin_carpeta'][0]['resultado_principal']);
        $this->assertEquals(93.1, $detalle['sin_carpeta'][0]['probabilidad_principal']);
    }
}
