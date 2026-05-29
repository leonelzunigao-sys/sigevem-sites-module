<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

try {
    $sql = "SELECT ruta_archivo FROM evidencia_fotografica 
            WHERE camara_id = :camara_id 
            ORDER BY fecha_subida DESC 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':camara_id' => $id]);
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($imagen ?: ['ruta_archivo' => null]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>