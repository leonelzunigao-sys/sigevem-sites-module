<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

try {
    // Consulta ponderada de mantenimientos en último año (más datos históricos)
    $sql = "
        SELECT 
            c.id,
            c.latitud,
            c.longitud,
            c.inventario_id,
            c.marca,
            c.zona,
            COUNT(mt.id) as total_mantenimientos,
            -- Cálculo ponderado: Preventivo=1, Correctivo=2, Emergencia=3
            SUM(
                CASE 
                    WHEN LOWER(mt.tipo) = 'preventivo' THEN 1
                    WHEN LOWER(mt.tipo) = 'correctivo' THEN 2
                    WHEN LOWER(mt.tipo) = 'emergencia' THEN 3
                    ELSE 1
                END
            ) as peso_total
        FROM camaras c
        INNER JOIN mantenimiento_tareas mt ON c.id = mt.camara_id
        WHERE c.latitud IS NOT NULL 
          AND c.longitud IS NOT NULL
          AND mt.fecha_programada >= (CURRENT_DATE - INTERVAL '1 year')
        GROUP BY c.id, c.latitud, c.longitud, c.inventario_id, c.marca, c.zona
        ORDER BY peso_total DESC
    ";

    $stmt = $pdo->query($sql);
    $camaras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalización con valor fijo (no depende del máximo real)
    $max_peso = 5; // Valor de referencia para normalización
    
    $heatmap_data = [];
    foreach ($camaras as $camara) {
        // Normalización suave contra rango fijo
        $intensidad = ($camara['peso_total'] / $max_peso);
        $intensidad = min(1.0, $intensidad); // Máximo 1.0
        $intensidad = max(0.1, $intensidad); // Mínimo 0.1 para que se vea
        $intensidad = round($intensidad, 2);
        
        $heatmap_data[] = [
            (float)$camara['latitud'],
            (float)$camara['longitud'],
            $intensidad,
            [
                'id' => $camara['id'],
                'inventario_id' => $camara['inventario_id'],
                'marca' => $camara['marca'],
                'zona' => $camara['zona'],
                'total' => $camara['total_mantenimientos'],
                'peso' => $camara['peso_total']
            ]
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($heatmap_data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>