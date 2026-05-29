<?php
require_once '../../config/database.php';

// Recibimos el texto que se está escribiendo
$q = isset($_GET['q']) ? $_GET['q'] : '';

if (empty($q)) {
    echo json_encode([]);
    exit;
}

// Consulta optimizada: Busca por ID, Marca o Ubicación
// Limitamos a 10 resultados para que sea instantáneo
$sql = "SELECT id, inventario_id, marca, direccion, zona 
        FROM camaras 
        WHERE inventario_id ILIKE :q 
           OR marca ILIKE :q 
           OR direccion ILIKE :q
        ORDER BY inventario_id ASC
        LIMIT 10";

$stmt = $pdo->prepare($sql);
$stmt->execute([':q' => "%$q%"]);
$camaras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Devolvemos JSON para JavaScript
header('Content-Type: application/json');
echo json_encode($camaras);
?>