<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/conexion.php';

$idcliente = isset($_GET['idcliente']) ? (int)$_GET['idcliente'] : 0;
$year      = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($idcliente <= 0) {
  echo json_encode([
    'success' => false,
    'message' => 'ID de cliente inválido'
  ]);
  exit;
}

/*
  1) Obtener meses pagados del año seleccionado
     Usamos pagos.fecha (mes cubierto)
*/
$sqlPagos = "
  SELECT DISTINCT MONTH(fecha) AS mes
  FROM pagos
  WHERE cliente = ?
    AND YEAR(fecha) = ?
  ORDER BY mes ASC
";

$stmt = $conexion->prepare($sqlPagos);
$stmt->bind_param('ii', $idcliente, $year);
$stmt->execute();
$res = $stmt->get_result();

$paidMonths = [];
while ($row = $res->fetch_assoc()) {
  $paidMonths[] = (int)$row['mes'];
}
$stmt->close();

/*
  2) Obtener último corte desde clientes
*/
$sqlCliente = "
  SELECT corte
  FROM clientes
  WHERE idcliente = ?
  LIMIT 1
";

$stmt = $conexion->prepare($sqlCliente);
$stmt->bind_param('i', $idcliente);
$stmt->execute();
$res = $stmt->get_result();

$ultimoCorte = null;
if ($row = $res->fetch_assoc()) {
  $ultimoCorte = $row['corte'];
}
$stmt->close();

/*
  3) Respuesta
*/
echo json_encode([
  'success'       => true,
  'idcliente'     => $idcliente,
  'year'          => $year,
  'paidMonths'    => $paidMonths,   // [1,2,3,4,5]
  'ultimo_corte'  => $ultimoCorte   // yyyy-mm-dd
], JSON_UNESCAPED_UNICODE);
