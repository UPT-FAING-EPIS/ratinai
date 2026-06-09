<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/EstablecimientoModel.php';

/**
 * RF-04: Pruebas unitarias para EstablecimientoModel
 *
 * Valida la integridad lógica del modelo sin tocar la BD real.
 * Utiliza Mocks de PDO/PDOStatement e inyección por Reflexión.
 */
class EstablecimientoModelTest extends TestCase {

    private $dbMock;
    private $stmtMock;
    private EstablecimientoModel $model;

    protected function setUp(): void {
        // 1. Mock de PDOStatement
        $this->stmtMock = $this->createMock(\PDOStatement::class);

        // 2. Mock de PDO
        $this->dbMock = $this->createMock(\PDO::class);

        // 3. Instanciar EstablecimientoModel sin ejecutar su constructor real
        $this->model = $this->getMockBuilder(EstablecimientoModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // 4. Inyectar la conexión falsa vía Reflexión
        $reflection = new \ReflectionClass(EstablecimientoModel::class);
        $property   = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->model, $this->dbMock);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEST 1: update() construye el SQL correcto con los 5 parámetros
    // ─────────────────────────────────────────────────────────────────────────
    public function testUpdateEjecutaQueryCorrectamente(): void {
        $id        = 7;
        $nombre    = 'Clínica Las Palmas';
        $direccion = 'Av. Principal 123';
        $tipo      = 'privado';
        $ruc       = '20512345678';

        // El statement mock debe recibir exactamente los 5 valores en orden
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$nombre, $direccion, $tipo, $ruc, $id])
            ->willReturn(true);

        // PDO::prepare debe recibir un SQL con UPDATE establecimientos
        $this->dbMock
            ->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                return strpos($sql, 'UPDATE establecimientos') !== false
                    && strpos($sql, 'nombre = ?')   !== false
                    && strpos($sql, 'WHERE id = ?') !== false;
            }))
            ->willReturn($this->stmtMock);

        $resultado = $this->model->update($id, $nombre, $direccion, $tipo, $ruc);

        $this->assertTrue(
            $resultado,
            'update() debe retornar true cuando execute() es exitoso.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEST 2: getAdminByEstablecimiento() prioriza id_usuario del establecimiento
    // ─────────────────────────────────────────────────────────────────────────
    public function testGetAdminPriorizaIdUsuario(): void {
        $idEst = 3;

        // Primera query: buscar id_usuario en establecimientos
        $stmtEst = $this->createMock(\PDOStatement::class);
        $stmtEst->method('execute')->willReturn(true);
        $stmtEst->method('fetch')->willReturn(['id_usuario' => 42]);

        // Segunda query: buscar usuario por id = 42
        $stmtUser = $this->createMock(\PDOStatement::class);
        $stmtUser->method('execute')->willReturn(true);
        $stmtUser->method('fetch')->willReturn([
            'id'        => 42,
            'nombre'    => 'Dr. Titular',
            'correo'    => 'titular@clinica.pe',
            'rol_codigo'=> 'ADM',
        ]);

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtEst, $stmtUser);

        $admins = $this->model->getAdminByEstablecimiento($idEst);

        $this->assertCount(1, $admins,
            'Debe retornar exactamente un admin cuando existe id_usuario.'
        );
        $this->assertEquals('Dr. Titular', $admins[0]['nombre'],
            'El nombre del admin debe coincidir con el titular del establecimiento.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEST 3: getAdminByEstablecimiento() usa fallback si id_usuario está vacío
    // ─────────────────────────────────────────────────────────────────────────
    public function testGetAdminUsaFallbackSiNoHayIdUsuario(): void {
        $idEst = 5;

        // Primera query: id_usuario es null → fallback
        $stmtEst = $this->createMock(\PDOStatement::class);
        $stmtEst->method('execute')->willReturn(true);
        $stmtEst->method('fetch')->willReturn(['id_usuario' => null]);

        // Segunda query (fallback): buscar por establecimiento_id + rol ADM
        $stmtFallback = $this->createMock(\PDOStatement::class);
        $stmtFallback->method('execute')->willReturn(true);
        $stmtFallback->method('fetchAll')->willReturn([
            ['id' => 10, 'nombre' => 'Admin Fallback', 'correo' => 'fb@clinica.pe', 'rol_codigo' => 'ADM'],
        ]);

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtEst, $stmtFallback);

        $admins = $this->model->getAdminByEstablecimiento($idEst);

        $this->assertCount(1, $admins,
            'El fallback debe retornar al menos un admin por establecimiento_id + rol_codigo ADM.'
        );
        $this->assertEquals('Admin Fallback', $admins[0]['nombre']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEST 4: getMedicosByEstablecimiento() construye SQL con rol_codigo = MED
    // ─────────────────────────────────────────────────────────────────────────
    public function testGetMedicosUsaRolMED(): void {
        $idEst = 3;

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([
            ['id' => 20, 'nombre' => 'Dr. Médico A', 'rol_codigo' => 'MED', 'activo' => 1],
            ['id' => 21, 'nombre' => 'Dr. Médico B', 'rol_codigo' => 'MED', 'activo' => 0],
        ]);

        $this->dbMock
            ->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                return strpos($sql, "rol_codigo = 'MED'") !== false
                    && strpos($sql, 'establecimiento_id = ?') !== false;
            }))
            ->willReturn($this->stmtMock);

        $medicos = $this->model->getMedicosByEstablecimiento($idEst);

        $this->assertCount(2, $medicos,
            'Debe retornar los 2 médicos (activos e inactivos) del establecimiento.'
        );
        $this->assertEquals('Dr. Médico A', $medicos[0]['nombre']);
    }
}
