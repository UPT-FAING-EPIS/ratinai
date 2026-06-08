<?php
require_once __DIR__ . '/config/config.php';
$db = (new Database())->getConnection();
$stmt = $db->query("DESCRIBE establecimientos");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols, JSON_PRETTY_PRINT);
