<?php
session_start();

// Solo Admin (1) y Supervisor (2) pueden programar mantenimiento
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2)) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$sitio_id = 0; // valor por defecto

try {
    $pdo->beginTransaction();

    // Datos del formulario
    $sitio_id          = intval($_POST['sitio_id']);
    $tipo              = trim($_POST['tipo']);
    $tecnico_id        = intval($_POST['tecnico_id']);
    $fecha_programada  = $_POST['fecha_programada'];
    $fecha_limite      = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;
    $descripcion       = trim($_POST['descripcion']);
    $prioridad         = $_POST['prioridad'] ?? 'media';
    $programado_por_id = $_SESSION['usuario_id'];

    // Validaciones obligatorias
    if (empty($tipo) || empty($tecnico_id) || empty($fecha_programada) || empty($descripcion)) {
        throw new Exception('Todos los campos marcados con * son obligatorios');
    }

    // Verificar que el sitio exista
    $check_sitio = $pdo->prepare("SELECT id, nombre, inventario_id FROM sitios WHERE id = :id");
    $check_sitio->execute([':id' => $sitio_id]);
    $sitio_data = $check_sitio->fetch(PDO::FETCH_ASSOC);
    
    if (!$sitio_data) {
        throw new Exception('El sitio que intentas mantener no existe');
    }

    // Verificar que el técnico exista y tenga rol de técnico
    $check_tecnico = $pdo->prepare("SELECT id, nombre_completo FROM usuarios WHERE id = :id AND rol_id = 3");
    $check_tecnico->execute([':id' => $tecnico_id]);
    if (!$check_tecnico->fetch()) {
        throw new Exception('El técnico seleccionado no es válido');
    }

    // Insertar orden de mantenimiento
    $insert_stmt = $pdo->prepare("INSERT INTO sitios_mantenimiento (
        sitio_id, tecnico_id, programado_por_id,
        tipo, descripcion, prioridad,
        fecha_programada, fecha_limite,
        estado, fecha_creacion
    ) VALUES (
        :sitio_id, :tecnico_id, :programado_por_id,
        :tipo, :descripcion, :prioridad,
        :fecha_programada, :fecha_limite,
        'pendiente', NOW()
    ) RETURNING id");

    $insert_stmt->execute([
        ':sitio_id'          => $sitio_id,
        ':tecnico_id'        => $tecnico_id,
        ':programado_por_id' => $programado_por_id,
        ':tipo'              => $tipo,
        ':descripcion'       => $descripcion,
        ':prioridad'         => $prioridad,
        ':fecha_programada'  => $fecha_programada,
        ':fecha_limite'      => $fecha_limite,
    ]);

    $mantenimiento_id = $insert_stmt->fetchColumn();

    // 🔄 OPCIONAL: Actualizar estado del sitio a "mantenimiento"
    // Si prefieres que el estado NO cambie automáticamente, comenta estas líneas
    $pdo->prepare("UPDATE sitios SET estado = 'mantenimiento' WHERE id = :id")
        ->execute([':id' => $sitio_id]);

    // Bitácora
    registrar_bitacora(
        $pdo,
        $programado_por_id,
        'programar_mantenimiento',
        'sitios',
        "Programó mantenimiento {$tipo} para sitio: {$sitio_data['inventario_id']} - {$sitio_data['nombre']} (Técnico ID: {$tecnico_id}, Fecha: {$fecha_programada})",
        $sitio_id
    );

    $pdo->commit();

    $_SESSION['success_message'] = "Mantenimiento programado exitosamente para el sitio {$sitio_data['inventario_id']}.";
    header('Location: ver.php?id=' . $sitio_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: mantenimiento_programar.php?id=' . $sitio_id);
    exit;
}
?>