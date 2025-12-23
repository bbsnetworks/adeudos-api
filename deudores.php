<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/conexion.php'; // <- tu conexion está en la misma carpeta

$minMonths    = isset($_GET['minMonths'])    ? max(0, (int)$_GET['minMonths'])    : 2; // atraso mínimo
$recentMonths = isset($_GET['recentMonths']) ? max(0, (int)$_GET['recentMonths']) : 2; // "recién instalado" = <= recentMonths

$page   = isset($_GET['page'])  ? max(1, (int)$_GET['page']) : 1;
$limit  = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

$q    = trim($_GET['q'] ?? '');
$plan = trim($_GET['plan'] ?? '');
$loc  = trim($_GET['localidad'] ?? '');


$bind   = '';
$params = [];

/* WHERE base:
   - Excluir equipo = 'COMPRADO'
*/
$where = "1=1 AND (c.equipo IS NULL OR TRIM(UPPER(c.equipo)) <> 'COMPRADO')";

if ($q !== '') {
  $where .= " AND (
    c.nombre LIKE ?
    OR REPLACE(c.telefono,'-','') LIKE REPLACE(?,'-','')
    OR CAST(c.idcliente AS CHAR) LIKE ?
  )";
  $bind  .= 'sss';
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}

if ($plan !== '') {
  $where .= " AND c.paquete = ?";
  $bind  .= 's';
  $params[] = $plan;
}

if ($loc !== '') {
  $where .= " AND c.localidad = ?";
  $bind  .= 's';
  $params[] = $loc;
}


/* Subconsulta:
   - ultima_pago: MAX(p.fechapago)
   - tiene_pago: 1 si tiene al menos un pago
   - meses_atraso: meses desde ultima_pago (solo válido si tiene_pago=1)
   - meses_desde_instalacion: meses desde c.instalacion (puede ser NULL)
*/
$sub = "
  SELECT
    c.idcliente,
    c.nombre,
    c.telefono,
    c.paquete,
    c.mensualidad,
    c.localidad,
    c.nodo,
    c.instalacion,
    MAX(p.fecha) AS ultima_pago, -- <- último mes pagado (periodo)
CASE WHEN MAX(p.fecha) IS NULL THEN 0 ELSE 1 END AS tiene_pago,
CASE
  WHEN MAX(p.fecha) IS NOT NULL
    THEN TIMESTAMPDIFF(MONTH, LAST_DAY(MAX(p.fecha)), CURDATE())
  ELSE NULL
END AS meses_atraso,
    CASE
      WHEN c.instalacion IS NULL THEN NULL
      ELSE TIMESTAMPDIFF(MONTH, c.instalacion, CURDATE())
    END AS meses_desde_instalacion
  FROM clientes c
  LEFT JOIN contratos ct
    ON ct.idcontrato = c.idcliente
  LEFT JOIN pagos p
    ON p.cliente = c.idcliente
  WHERE {$where}
    AND (ct.idcontrato IS NULL OR ct.status <> 'cancelado')
  GROUP BY c.idcliente
";


/* Reglas en el exterior:
   - tiene_pago = 1 (excluir “nunca pagaron”)
   - (meses_desde_instalacion IS NULL OR meses_desde_instalacion > recentMonths) (excluir recién instalados)
   - meses_atraso >= minMonths (atraso real)
*/
$countSql = "
  SELECT COUNT(*) AS total
  FROM ({$sub}) t
  WHERE
    t.tiene_pago = 1
    AND (t.meses_desde_instalacion IS NULL OR t.meses_desde_instalacion > ?)
    AND t.meses_atraso >= ?
";
$stmt = $conexion->prepare($countSql);
$bindCount   = $bind . 'ii';
$paramsCount = [...$params, $recentMonths, $minMonths];
if ($bindCount) $stmt->bind_param($bindCount, ...$paramsCount);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$dataSql = "
  SELECT
    t.idcliente,
    t.nombre,
    t.telefono,
    t.paquete,
    t.mensualidad,
    t.localidad,
    t.nodo,
    t.ultima_pago,
    t.meses_atraso,
    (COALESCE(t.meses_atraso,0) * COALESCE(t.mensualidad,0)) AS adeudo_estimado
  FROM ({$sub}) t
  WHERE
    t.tiene_pago = 1
    AND (t.meses_desde_instalacion IS NULL OR t.meses_desde_instalacion > ?)
    AND t.meses_atraso >= ?
  ORDER BY t.meses_atraso DESC, adeudo_estimado DESC, t.nombre ASC
  LIMIT ? OFFSET ?
";
$stmt = $conexion->prepare($dataSql);
$bindData   = $bind . 'iiii';
$paramsData = [...$params, $recentMonths, $minMonths, $limit, $offset];
if ($bindData) $stmt->bind_param($bindData, ...$paramsData);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($r = $res->fetch_assoc()) {
  $out[] = [
    'id'              => (int)$r['idcliente'],
    'idcliente'       => (int)$r['idcliente'],
    'nombre'          => $r['nombre'],
    'telefono'        => $r['telefono'],
    'plan'            => $r['paquete'],
    'mensualidad'     => (int)$r['mensualidad'],
    'ultima_pago'     => $r['ultima_pago'],
    'meses_atraso'    => (int)$r['meses_atraso'],
    'adeudo_estimado' => (float)$r['adeudo_estimado'],
    'localidad'       => $r['localidad'],
    'nodo'            => $r['nodo'],
  ];
}
$stmt->close();

echo json_encode([
  'success' => true,
  'page'    => $page,
  'limit'   => $limit,
  'total'   => $total,
  'data'    => $out,
], JSON_UNESCAPED_UNICODE);
