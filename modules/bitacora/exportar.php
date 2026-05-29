<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../config/database.php';

// ============================================
// FILTROS (mismos que index.php)
// ============================================
$busqueda  = trim($_GET['busqueda'] ?? '');
$accion    = trim($_GET['accion'] ?? '');
$modulo    = trim($_GET['modulo'] ?? '');
$fecha_ini = trim($_GET['fecha_ini'] ?? '');
$fecha_fin = trim($_GET['fecha_fin'] ?? '');
$solo_mias = isset($_GET['solo_mias']) && $_GET['solo_mias'] == '1';

$where  = [];
$params = [];

if ($_SESSION['rol_id'] == 3) {
    $where[]  = 'b.usuario_id = :forzado_id';
    $params[':forzado_id'] = $_SESSION['usuario_id'];
} elseif ($solo_mias) {
    $where[]  = 'b.usuario_id = :solo_id';
    $params[':solo_id'] = $_SESSION['usuario_id'];
}

if ($busqueda !== '') {
    $where[]  = "(u.nombre_completo ILIKE :busqueda OR b.accion ILIKE :busqueda OR b.modulo ILIKE :busqueda OR b.descripcion ILIKE :busqueda)";
    $params[':busqueda'] = '%' . $busqueda . '%';
}

if ($accion !== '') {
    $where[]  = 'LOWER(b.accion) = LOWER(:accion)';
    $params[':accion'] = $accion;
}

if ($modulo !== '') {
    $where[]  = 'LOWER(b.modulo) = LOWER(:modulo)';
    $params[':modulo'] = $modulo;
}

if ($fecha_ini !== '') {
    $where[]  = 'b.fecha >= :fecha_ini';
    $params[':fecha_ini'] = $fecha_ini . ' 00:00:00';
}

if ($fecha_fin !== '') {
    $where[]  = 'b.fecha <= :fecha_fin';
    $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
}

$sql_where = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT 
            b.fecha,
            u.nombre_completo AS usuario,
            r.nombre   AS rol,
            b.accion,
            b.modulo,
            b.descripcion
        FROM bitacora_sistema b
        LEFT JOIN usuarios u ON b.usuario_id = u.id
        LEFT JOIN roles    r ON u.rol_id = r.id
        $sql_where
        ORDER BY b.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// GENERAR CSV
// ============================================
$nombre_archivo = 'bitacora_sigevem_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para que Excel abra correctamente con acentos
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Cabecera
fputcsv($output, ['Fecha y Hora', 'Usuario', 'Rol', 'Acción', 'Módulo', 'Descripción'], ',');

// Filas
foreach ($registros as $reg) {
    fputcsv($output, [
        (new DateTime($reg['fecha']))->format('d/m/Y H:i:s'),
        $reg['usuario']     ?? 'Sistema',
        $reg['rol']         ?? 'Sistema',
        ucfirst($reg['accion']),
        ucfirst($reg['modulo']),
        $reg['descripcion'] ?? '',
    ], ',');
}

fclose($output);
exit;