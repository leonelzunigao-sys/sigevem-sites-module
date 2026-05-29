<?php
session_start();

// Verificar que sea método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Incluir configuración
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/bitacora_helper.php'; // ← AGREGADO

// Obtener datos del formulario
$email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$recordar = isset($_POST['recordar']);

// Validar campos requeridos
if (empty($email) || empty($password)) {
    $_SESSION['error_login'] = 'Por favor ingrese correo y contraseña';
    header('Location: login.php');
    exit;
}

try {
    // Consultar usuario en la base de datos
    $sql = "SELECT 
                u.id, 
                u.nombre_completo, 
                u.email, 
                u.password_hash, 
                u.rol_id, 
                u.estatus,
                r.nombre as rol_nombre,
                r.descripcion as rol_descripcion
            FROM usuarios u
            INNER JOIN roles r ON u.rol_id = r.id
            WHERE u.email = :email 
            AND u.estatus = 'activo'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar si existe el usuario y la contraseña es correcta
    if ($usuario && password_verify($password, $usuario['password_hash'])) {

        // Crear sesión
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre']     = $usuario['nombre_completo'];
        $_SESSION['email']      = $usuario['email'];
        $_SESSION['rol_id']     = $usuario['rol_id'];
        $_SESSION['rol_nombre'] = $usuario['rol_nombre'];
        $_SESSION['login_time'] = time();

        // Actualizar último acceso
        $update_stmt = $pdo->prepare("UPDATE usuarios 
                                      SET fecha_ultimo_acceso = NOW(), 
                                          ultimo_ip = :ip 
                                      WHERE id = :id");
        $update_stmt->execute([
            ':ip' => $_SERVER['REMOTE_ADDR'],
            ':id' => $usuario['id']
        ]);

        // ← Registrar login exitoso en bitácora
        registrar_bitacora(
            $pdo,
            $usuario['id'],
            'login',
            'auth',
            'Inicio de sesión exitoso — Rol: ' . $usuario['rol_nombre']
        );

        // Si marcó "Recordar sesión", extender la sesión
        if ($recordar) {
            ini_set('session.gc_maxlifetime', 2592000);
            session_set_cookie_params(2592000);
        }

        // Redirigir al dashboard
        header('Location: ../dashboard/index.php');
        exit;

    } else {
        // Credenciales incorrectas
        $_SESSION['error_login'] = 'Correo electrónico o contraseña incorrectos';

        // ← Registrar intento fallido en bitácora
        // usuario_id = 0 indica intento anónimo
        registrar_bitacora(
            $pdo,
            0,
            'login_fallido',
            'auth',
            'Intento de acceso fallido con correo: ' . $email
        );

        header('Location: login.php');
        exit;
    }

} catch (PDOException $e) {
    error_log("Error de login: " . $e->getMessage());
    $_SESSION['error_login'] = 'Error en el sistema. Intente más tarde.';
    header('Location: login.php');
    exit;
}
?>