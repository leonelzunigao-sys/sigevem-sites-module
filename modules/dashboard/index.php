<?php
session_start();

// Verificar que haya sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Incluir configuración
require_once '../../config/database.php';
require_once '../../config/session.php';

// Obtener rol del usuario
$rol_id = $_SESSION['rol_id'];
$rol_nombre = $_SESSION['rol_nombre'];

// Redirigir según el rol
switch ($rol_id) {
    case 1: // Administrador
        include 'admin.php';
        break;
    case 2: // Supervisor
        include 'supervisor.php';
        break;
    case 3: // Técnico
        include 'tecnico.php';
        break;
    default:
        echo "<h1>Error: Rol no reconocido</h1>";
        echo "<a href='../auth/logout.php'>Cerrar sesión</a>";
        break;
}
?>