<?php
session_start();

// Opcional: Si quieres que solo usuarios logueados puedan ver el mapa
// if (!isset($_SESSION['usuario_id'])) { exit; }

require_once '../../config/database.php';

try {
    // Consulta para obtener sitios con coordenadas válidas
    $sql = "SELECT 
                id, 
                inventario_id, 
                nombre, 
                latitud, 
                longitud, 
                estado, 
                validacion_estado,
                tipo_inmueble, 
                zona,
                activos_computadoras, 
                activos_servidores, 
                activos_impresoras, 
                activos_otros
            FROM sitios 
            WHERE latitud IS NOT NULL AND longitud IS NOT NULL
            ORDER BY inventario_id ASC";

    $stmt = $pdo->query($sql);
    $sitios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($sitios as $sitio) {
        // Calcular total de activos
        $total_activos = (int)$sitio['activos_computadoras'] + 
                         (int)$sitio['activos_servidores'] + 
                         (int)$sitio['activos_impresoras'] + 
                         (int)$sitio['activos_otros'];

        $data[] = [
            'id'                => $sitio['id'],
            'inventario_id'     => $sitio['inventario_id'],
            'nombre'            => $sitio['nombre'],
            'latitud'           => (float)$sitio['latitud'],
            'longitud'          => (float)$sitio['longitud'],
            'estado'            => $sitio['estado'],
            'validacion_estado' => $sitio['validacion_estado'],
            'tipo_inmueble'     => $sitio['tipo_inmueble'],
            'zona'              => $sitio['zona'],
            'total_activos'     => $total_activos,
            // Icono sugerido para el mapa (edificio)
            'icon'              => 'building' 
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>