<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

$id = $_GET['id'] ?? 0;

if ($id == 0) {
    header('Location: index.php?error=invalid_id');
    exit;
}

if ($id == $_SESSION['usuario_id']) {
    header('Location: index.php?error=no_self_reset');
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre_completo, email FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: index.php?error=not_found');
    exit;
}

$errores       = [];
$exito         = '';
$nueva_password = '';

// ============================================
// PROCESAR RESETEO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nueva_password = bin2hex(random_bytes(4)); // 8 caracteres

    try {
        $password_hash = password_hash($nueva_password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $stmt->execute([$password_hash, $id]);

        registrar_bitacora(
            $pdo,
            $_SESSION['usuario_id'],
            'resetear',
            'usuarios',
            "Reseteó contraseña de: {$usuario['nombre_completo']} ({$usuario['email']})",
            $id
        );

        $exito = "Contraseña reseteada exitosamente.";

    } catch (PDOException $e) {
        $errores[] = "Error al resetear la contraseña: " . $e->getMessage();
    }
}

$fecha_actual = date('l, j \d\e F Y — H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resetear Contraseña | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/usuarios.css">
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left">
            <img src="../../assets/img/logo-ecatepec-largo.png" alt="SIGEVEM" class="header-logo">
        </div>
        <div class="header-center">
            <h2 class="system-title">SIGEVEM</h2>
            <p class="system-subtitle">Sistema Integral de Gestión y Geolocalización de Infraestructura de Videovigilancia Municipal</p>
        </div>
        <div class="header-right">
            <div class="user-badge">
                <span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            </div>
        </div>
    </header>

    <?php include '../../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Resetear Contraseña</h1>
                <p class="page-subtitle">Generar nueva contraseña temporal</p>
                <p class="page-date"><?php echo $fecha_actual; ?></p>
            </div>
            <a href="ver.php?id=<?php echo $id; ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <strong>Error:</strong>
            <ul><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <?php if ($exito): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <strong><?php echo $exito; ?></strong>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Usuario</h3>
            </div>
            <div class="card-body">
                <div class="user-info-simple">
                    <div class="info-row"><label>Nombre:</label><span><?php echo htmlspecialchars($usuario['nombre_completo']); ?></span></div>
                    <div class="info-row"><label>Email:</label><span><?php echo htmlspecialchars($usuario['email']); ?></span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-key"></i> Nueva Contraseña Temporal</h3>
            </div>
            <div class="card-body">
                <?php if ($exito && $nueva_password): ?>
                <div class="password-result">
                    <div class="password-box">
                        <label>Nueva Contraseña Temporal:</label>
                        <div class="password-display">
                            <code id="passwordCode"><?php echo $nueva_password; ?></code>
                            <button type="button" class="btn-copy" onclick="copiarPassword()" title="Copiar">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Importante:</strong>
                            <ul class="mb-0">
                                <li>Esta contraseña solo se muestra una vez.</li>
                                <li>Copia la contraseña antes de salir de esta página.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="form-actions">
                        <a href="ver.php?id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="fas fa-check"></i> Listo
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="reset-confirm">
                    <p class="mb-4">
                        <i class="fas fa-info-circle"></i>
                        Se generará una contraseña temporal de 8 caracteres para
                        <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Medidas de seguridad:</strong>
                            <ul class="mb-0">
                                <li>La contraseña actual será reemplazada permanentemente.</li>
                                <li>Se registrará esta acción en la bitácora del sistema.</li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST">
                        <div class="form-actions">
                            <a href="ver.php?id=<?php echo $id; ?>" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> Generar Nueva Contraseña
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    function copiarPassword() {
        const password = document.getElementById('passwordCode').textContent;
        navigator.clipboard.writeText(password).then(() => {
            alert('Contraseña copiada al portapapeles');
        }).catch(err => {
            alert('Error al copiar: ' + err);
        });
    }
    </script>
</body>
</html>