<?php
// ============================================
// BITÁCORA HELPER
// SIGEVEM - Ecatepec
// ============================================
// Uso:
//   require_once '../../includes/bitacora_helper.php';
//   registrar_bitacora($pdo, $_SESSION['usuario_id'], 'aprobar', 'camaras', 'Aprobó CAM-0046');
// ============================================

function registrar_bitacora(
    PDO    $pdo,
    int    $usuario_id,
    string $accion,
    string $modulo,
    string $descripcion,
    ?int    $registro_id = null
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '::1';

        $stmt = $pdo->prepare("
            INSERT INTO bitacora_sistema
                (usuario_id, accion, modulo, descripcion, registro_id, ip_origen, fecha)
            VALUES
                (:usuario_id, :accion, :modulo, :descripcion, :registro_id, :ip_origen, NOW())
        ");

        $stmt->execute([
            ':usuario_id'  => $usuario_id,
            ':accion'      => strtolower(trim($accion)),
            ':modulo'      => strtolower(trim($modulo)),
            ':descripcion' => trim($descripcion),
            ':registro_id' => $registro_id,
            ':ip_origen'   => $ip,
        ]);

    } catch (Exception $e) {
        // No interrumpir el flujo principal si falla la bitácora
        error_log('Error bitácora: ' . $e->getMessage());
    }
}