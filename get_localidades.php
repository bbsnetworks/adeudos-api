<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/conexion.php';

$res = $conexion->query("SELECT nombrelocalidad FROM localidad ORDER BY nombrelocalidad ASC");
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r['nombrelocalidad'];

echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
