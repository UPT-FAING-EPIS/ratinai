<?php
require_once __DIR__ . '/../../utils/PasswordHelper.php';

class PasswordHelperTest extends PHPUnit_Framework_TestCase {
    public function testGenerateTempLength() {
        $pass = PasswordHelper::generateTemp(12);
        $this->assertEquals(12, strlen($pass), "La longitud de la contraseña generada no es correcta.");
    }
    
    public function testGenerateTempIsAlphanumeric() {
        $pass = PasswordHelper::generateTemp(12);
        $this->assertTrue(ctype_alnum($pass), "La contraseña contiene caracteres no alfanuméricos.");
    }
    
    public function testGenerateTempIsUnique() {
        $pass1 = PasswordHelper::generateTemp(12);
        $pass2 = PasswordHelper::generateTemp(12);
        $this->assertNotEquals($pass1, $pass2, "Las contraseñas generadas no son únicas.");
    }
}
