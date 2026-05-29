<?php
session_start();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 2])) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$tipo_reporte = $_POST['tipo_reporte'] ?? '';
$fecha_ini    = $_POST['fecha_ini'] ?? '';
$fecha_fin    = $_POST['fecha_fin'] ?? '';

if (empty($tipo_reporte)) {
    $_SESSION['error_message'] = 'Selecciona un tipo de reporte.';
    header('Location: index.php');
    exit;
}

// ============================================
// NOMBRES LEGIBLES POR TIPO
// ============================================
$nombres = [
    'camaras_zona'              => 'Cámaras por Zona',
    'inventario_completo'       => 'Inventario Completo',
    'mantenimientos_tecnico'    => 'Mantenimientos por Técnico',
    'estadisticas_operatividad' => 'Estadísticas de Operatividad',
    'tiempos_respuesta'         => 'Tiempos de Respuesta',
    'validaciones_pendientes'   => 'Validaciones Pendientes',
];

$nombre_reporte = $nombres[$tipo_reporte] ?? $tipo_reporte;

// ============================================
// FILTRO DE FECHAS (reutilizable)
// ============================================
function filtro_fechas(string $campo, string $ini, string $fin, array &$params): string {
    $where = [];
    if (!empty($ini)) {
        $where[] = "{$campo} >= :fecha_ini";
        $params[':fecha_ini'] = $ini . ' 00:00:00';
    }
    if (!empty($fin)) {
        $where[] = "{$campo} <= :fecha_fin";
        $params[':fecha_fin'] = $fin . ' 23:59:59';
    }
    return count($where) ? 'AND ' . implode(' AND ', $where) : '';
}

// ============================================
// CONSULTAS POR TIPO
// ============================================
$cabecera = [];
$filas    = [];
$params   = [];

switch ($tipo_reporte) {

    // ------------------------------------------
    case 'camaras_zona':
        $cabecera = ['Zona', 'Total Cámaras', 'Activas', 'Pendientes', 'Mantenimiento', 'Fuera de Servicio'];
        $stmt = $pdo->query("
            SELECT zona,
                COUNT(*) AS total,
                SUM(CASE WHEN estatus = 'activa' THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN estatus = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estatus = 'mantenimiento' THEN 1 ELSE 0 END) AS mantenimiento,
                SUM(CASE WHEN estatus = 'fuera_servicio' THEN 1 ELSE 0 END) AS fuera_servicio
            FROM camaras
            GROUP BY zona
            ORDER BY zona
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $filas[] = [$row['zona'], $row['total'], $row['activas'],
                        $row['pendientes'], $row['mantenimiento'], $row['fuera_servicio']];
        }
        break;

    // ------------------------------------------
    case 'inventario_completo':
        $cabecera = ['ID Inventario', 'Marca', 'Modelo', 'Tipo', 'Zona', 'Dirección',
                     'Colonia', 'Estatus', 'Fecha Instalación', 'Número Serie', 'Coordenadas'];
        $filtro = filtro_fechas('c.fecha_registro', $fecha_ini, $fecha_fin, $params);
        $stmt = $pdo->prepare("
            SELECT c.inventario_id, c.marca, c.modelo, c.tipo_camara, c.zona,
                   c.direccion, c.colonia, c.estatus, c.fecha_instalacion,
                   c.numero_serie, c.latitud, c.longitud
            FROM camaras c
            WHERE 1=1 {$filtro}
            ORDER BY c.zona, c.inventario_id
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $filas[] = [
                $row['inventario_id'], $row['marca'], $row['modelo'] ?? '—',
                $row['tipo_camara'], $row['zona'], $row['direccion'],
                $row['colonia'] ?? '—', ucfirst($row['estatus']),
                $row['fecha_instalacion'] ? date('d/m/Y', strtotime($row['fecha_instalacion'])) : '—',
                $row['numero_serie'] ?? '—',
                $row['latitud'] . ', ' . $row['longitud'],
            ];
        }
        break;

    // ------------------------------------------
    case 'mantenimientos_tecnico':
        $cabecera = ['Técnico', 'Total Tareas', 'Pendientes', 'En Proceso',
                     'Completadas', 'Validadas', 'Canceladas'];
        $filtro = filtro_fechas('mt.fecha_creacion', $fecha_ini, $fecha_fin, $params);
        $stmt = $pdo->prepare("
            SELECT u.nombre_completo AS tecnico,
                COUNT(*) AS total,
                SUM(CASE WHEN mt.estado = 'pendiente'   THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN mt.estado = 'en_proceso'  THEN 1 ELSE 0 END) AS en_proceso,
                SUM(CASE WHEN mt.estado = 'completado'  THEN 1 ELSE 0 END) AS completadas,
                SUM(CASE WHEN mt.estado = 'validado'    THEN 1 ELSE 0 END) AS validadas,
                SUM(CASE WHEN mt.estado = 'cancelado'   THEN 1 ELSE 0 END) AS canceladas
            FROM mantenimiento_tareas mt
            LEFT JOIN usuarios u ON mt.tecnico_id = u.id
            WHERE 1=1 {$filtro}
            GROUP BY u.nombre_completo
            ORDER BY total DESC
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $filas[] = [$row['tecnico'] ?? 'Sin asignar', $row['total'], $row['pendientes'],
                        $row['en_proceso'], $row['completadas'], $row['validadas'], $row['canceladas']];
        }
        break;

    // ------------------------------------------
    case 'estadisticas_operatividad':
        $cabecera = ['Zona', 'Total', 'Activas', '% Operatividad', 'Fuera de Servicio', 'En Mantenimiento', 'Pendientes'];
        $stmt = $pdo->query("
            SELECT zona,
                COUNT(*) AS total,
                SUM(CASE WHEN estatus = 'activa' THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN estatus = 'fuera_servicio' THEN 1 ELSE 0 END) AS fuera,
                SUM(CASE WHEN estatus = 'mantenimiento' THEN 1 ELSE 0 END) AS mant,
                SUM(CASE WHEN estatus = 'pendiente' THEN 1 ELSE 0 END) AS pendientes
            FROM camaras
            GROUP BY zona
            ORDER BY zona
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pct = $row['total'] > 0 ? round(($row['activas'] / $row['total']) * 100, 1) : 0;
            $filas[] = [$row['zona'], $row['total'], $row['activas'],
                        $pct . '%', $row['fuera'], $row['mant'], $row['pendientes']];
        }
        break;

    // ------------------------------------------
    case 'tiempos_respuesta':
        $cabecera = ['ID Tarea', 'Cámara', 'Técnico', 'Tipo', 'Fecha Programada',
                     'Fecha Inicio', 'Fecha Completado', 'Días de Resolución', 'Estado'];
        $filtro = filtro_fechas('mt.fecha_creacion', $fecha_ini, $fecha_fin, $params);
        $stmt = $pdo->prepare("
            SELECT mt.id, c.inventario_id, u.nombre_completo AS tecnico,
                   mt.tipo, mt.fecha_programada, mt.fecha_inicio,
                   mt.fecha_completado, mt.estado
            FROM mantenimiento_tareas mt
            LEFT JOIN camaras c ON mt.camara_id = c.id
            LEFT JOIN usuarios u ON mt.tecnico_id = u.id
            WHERE mt.estado IN ('completado','validado') {$filtro}
            ORDER BY mt.fecha_completado DESC
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dias = '—';
            if ($row['fecha_inicio'] && $row['fecha_completado']) {
                $inicio    = new DateTime($row['fecha_inicio']);
                $completado = new DateTime($row['fecha_completado']);
                $dias = $inicio->diff($completado)->days;
            }
            $filas[] = [
                'MNT-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
                $row['inventario_id'] ?? '—',
                $row['tecnico'] ?? '—',
                ucfirst($row['tipo']),
                $row['fecha_programada'] ? date('d/m/Y', strtotime($row['fecha_programada'])) : '—',
                $row['fecha_inicio'] ? date('d/m/Y', strtotime($row['fecha_inicio'])) : '—',
                $row['fecha_completado'] ? date('d/m/Y', strtotime($row['fecha_completado'])) : '—',
                $dias,
                ucfirst($row['estado']),
            ];
        }
        break;

    // ------------------------------------------
    case 'validaciones_pendientes':
        $cabecera = ['ID Cámara', 'Marca', 'Tipo', 'Zona', 'Dirección',
                     'Registrado por', 'Fecha Registro', 'Estado Validación'];
        $stmt = $pdo->query("
            SELECT c.inventario_id, c.marca, c.tipo_camara, c.zona, c.direccion,
                   u.nombre_completo AS registrado_por,
                   cv.fecha_registro, cv.estado
            FROM camaras_validacion cv
            LEFT JOIN camaras c ON cv.camara_id = c.id
            LEFT JOIN usuarios u ON cv.usuario_registro_id = u.id
            ORDER BY cv.fecha_registro DESC
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $filas[] = [
                $row['inventario_id'] ?? '—', $row['marca'] ?? '—',
                $row['tipo_camara'] ?? '—', $row['zona'] ?? '—',
                $row['direccion'] ?? '—', $row['registrado_por'] ?? '—',
                $row['fecha_registro'] ? date('d/m/Y H:i', strtotime($row['fecha_registro'])) : '—',
                ucfirst($row['estado']),
            ];
        }
        break;

    default:
        $_SESSION['error_message'] = 'Tipo de reporte no válido.';
        header('Location: index.php');
        exit;
}

// ============================================
// GENERAR CSV Y DESCARGAR
// ============================================
$nombre_archivo = strtolower(str_replace(' ', '_', $nombre_reporte)) . '_' . date('Ymd_His') . '.csv';

// Registrar en bitácora
$desc_fechas = '';
if (!empty($fecha_ini) || !empty($fecha_fin)) {
    $desc_fechas = ' — Período: ' . ($fecha_ini ? date('d/m/Y', strtotime($fecha_ini)) : 'inicio')
                 . ' al ' . ($fecha_fin ? date('d/m/Y', strtotime($fecha_fin)) : 'hoy');
}

registrar_bitacora(
    $pdo,
    $_SESSION['usuario_id'],
    'exportar',
    'reportes',
    "Generó reporte: {$nombre_reporte}{$desc_fechas}"
);

// Headers de descarga
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Título del reporte
fputcsv($output, [$nombre_reporte . ' — SIGEVEM Ecatepec'], ',');
fputcsv($output, ['Generado el: ' . date('d/m/Y H:i') . ' por ' . $_SESSION['nombre']], ',');
if (!empty($fecha_ini) || !empty($fecha_fin)) {
    fputcsv($output, ['Período: ' . ($fecha_ini ?: 'inicio') . ' al ' . ($fecha_fin ?: 'hoy')], ',');
}
fputcsv($output, [], ','); // línea vacía

// Cabecera
fputcsv($output, $cabecera, ',');

// Datos
foreach ($filas as $fila) {
    fputcsv($output, $fila, ',');
}

// Totales
fputcsv($output, [], ',');
fputcsv($output, ['Total de registros: ' . count($filas)], ',');

fclose($output);
exit;
?>