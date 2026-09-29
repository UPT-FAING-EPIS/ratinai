<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/AnalisisModel.php';

class AnalisisModelTest extends TestCase {

    private $dbMock;
    private $stmtMock;
    private $analisisModel;

    protected function setUp(): void {
        $this->stmtMock = $this->createMock(\PDOStatement::class);
        $this->dbMock = $this->createMock(\PDO::class);

        $this->analisisModel = $this->getMockBuilder(AnalisisModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $reflection = new \ReflectionClass(AnalisisModel::class);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->analisisModel, $this->dbMock);
    }

    public function testGetSeguimientoCasosCriticos(): void {
        $casosMock = [
            [
                'id' => 52,
                'id_paciente' => 8,
                'fecha_analisis' => '2026-06-22 23:58:00',
                'resultado_principal' => 'diabetes',
                'probabilidad_principal' => 93.8,
                'diagnostico_medico' => 'Control prioritario',
                'dni' => '71987400',
                'codigo_paciente' => 'PAC-28337'
            ]
        ];

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($casosMock);

        $this->dbMock->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                return strpos($sql, 'FROM analisis_retinales a') !== false
                    && strpos($sql, 'LEFT JOIN pacientes p') !== false
                    && strpos($sql, 'a.alerta_anomalia = 1') !== false
                    && strpos($sql, 'ORDER BY a.probabilidad_principal DESC') !== false;
            }))
            ->willReturn($this->stmtMock);

        $resultado = $this->analisisModel->getSeguimientoCasosCriticos(5, 20);

        $this->assertIsArray($resultado);
        $this->assertCount(1, $resultado);
        $this->assertEquals('PAC-28337', $resultado[0]['codigo_paciente']);
        $this->assertEquals('diabetes', $resultado[0]['resultado_principal']);
    }
}
