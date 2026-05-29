<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

try {
    // Obtener filtros
    $search = $_GET['search'] ?? '';
    $zona = $_GET['zona'] ?? '';
    $estado = $_GET['estado'] ?? '';
    
    // Construir query
    $sql = "SELECT 
        c.id,
        c.inventario_id,
        c.marca,
        c.modelo,
        c.tipo_camara,
        c.zona,
        c.direccion,
        c.colonia,
        c.latitud,
        c.longitud,
        c.estatus,
        c.fecha_instalacion,
        c.numero_serie
    FROM camaras c
    WHERE 1=1";
    
    $params = [];
    
    // Filtro de búsqueda
    if (!empty($search)) {
        $sql .= " AND (c.inventario_id ILIKE :search OR c.marca ILIKE :search OR c.direccion ILIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    
    // Filtro por zona
    if (!empty($zona)) {
        $sql .= " AND c.zona = :zona";
        $params[':zona'] = $zona;
    }
    
    // Filtro por estado
    if (!empty($estado)) {
        $sql .= " AND c.estatus = :estado";
        $params[':estado'] = $estado;
    }
    
    $sql .= " ORDER BY c.inventario_id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $camaras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear respuesta
    header('Content-Type: application/json');
    echo json_encode($camaras);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>