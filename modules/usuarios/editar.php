<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

$id = $_GET['id'] ?? 0;

if ($id == $_SESSION['usuario_id']) {
    header('Location: index.php?error=no_self_edit');
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: index.php?error=not_found');
    exit;
}

$roles   = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$errores = [];

// ============================================
// PROCESAR FORMULARIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $rol_id          = $_POST['rol_id'] ?? '';

    if (empty($nombre_completo)) $errores[] = "El nombre completo es obligatorio.";
    if (empty($email))           $errores[] = "El correo electrónico es obligatorio.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El correo electrónico no es válido.";

    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() > 0) $errores[] = "El correo ya está registrado por otro usuario.";
    }

    if (empty($rol_id)) $errores[] = "El rol es obligatorio.";

    if (empty($errores)) {
        try {
            // Guardar valores anteriores para la descripción
            $nombre_anterior = $usuario['nombre_completo'];
            $rol_anterior    = $usuario['rol_nombre'];

            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET nombre_completo = ?, email = ?, rol_id = ?, fecha_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nombre_completo, $email, $rol_id, $id]);

            // Obtener nombre del nuevo rol
            $stmt_rol = $pdo->prepare("SELECT nombre FROM roles WHERE id = ?");
            $stmt_rol->execute([$rol_id]);
            $rol_nuevo = $stmt_rol->fetchColumn();

            $descripcion = "Editó usuario: {$nombre_anterior}";
            if ($nombre_anterior !== $nombre_completo) $descripcion .= " → nombre: {$nombre_completo}";
            if ($rol_anterior    !== $rol_nuevo)       $descripcion .= " → rol: {$rol_anterior} → {$rol_nuevo}";

            registrar_bitacora(
                $pdo,
                $_SESSION['usuario_id'],
                'editar',
                'usuarios',
                $descripcion,
                $id
            );

            header('Location: index.php?editado=1');
            exit;

        } catch (PDOException $e) {
            $errores[] = "Error al actualizar el usuario: " . $e->getMessage();
        }
    }
}

$fecha_actual = date('l, j \d\e F Y — H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario | SIGEVEM</title>
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
                <span class="badge-datetime" id="headerDatetime"></span>
                <span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            </div>
        </div>
    </header>

    <?php include '../../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Editar Usuario</h1>
                <p class="page-subtitle">Modificar información del usuario</p>
                <p class="page-date"><?php echo $fecha_actual; ?></p>
            </div>
            <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>

        <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <strong>Error:</strong>
            <ul><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Información del Usuario</h3>
            </div>
            <div class="card-body">
                <div class="user-info-grid">
                    <div class="info-item"><label>ID:</label><span><?php echo $usuario['id']; ?></span></div>
                    <div class="info-item">
                        <label>Estado:</label>
                        <span class="badge badge-<?php echo $usuario['estatus'] == 'activo' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($usuario['estatus']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Fecha de Creación:</label>
                        <span><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Último Acceso:</label>
                        <span>
                            <?php echo !empty($usuario['fecha_ultimo_acceso'])
                                ? date('d/m/Y H:i', strtotime($usuario['fecha_ultimo_acceso']))
                                : '<span class="text-muted">Nunca</span>'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-edit"></i> Editar Información</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-usuarios">
                    <div class="form-group">
                        <label for="nombre_completo"><i class="fas fa-user"></i> Nombre Completo *</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" class="form-control"
                               value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Correo Electrónico *</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="rol_id"><i class="fas fa-user-tag"></i> Rol del Sistema *</label>
                        <select id="rol_id" name="rol_id" class="form-control" required>
                            <option value="">Seleccionar rol...</option>
                            <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo $rol['id']; ?>"
                                    <?php echo $usuario['rol_id'] == $rol['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="info-box" id="infoPermisos">
                        <h4><i class="fas fa-info-circle"></i> PERMISOS DEL ROL: <span id="rolNombre"></span></h4>
                        <ul id="listaPermisos"></ul>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-lock"></i>
                        <div>
                            <strong>¿Cambiar contraseña?</strong>
                            <p class="mb-0">Usa la opción "Resetear Contraseña" para generar una nueva.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancelar</a>
                        <a href="reset_password.php?id=<?php echo $id; ?>" class="btn btn-warning">
                            <i class="fas fa-key"></i> Resetear Contraseña
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    const permisosPorRol = {
        '1': { nombre: 'ADMINISTRADOR', permisos: [
            { text: 'Dashboard (completo)', allowed: true },
            { text: 'Cámaras (CRUD completo)', allowed: true },
            { text: 'Mantenimiento (programar/ejecutar/validar)', allowed: true },
            { text: 'Usuarios (gestión completa)', allowed: true },
            { text: 'Bitácora (todas las acciones)', allowed: true },
            { text: 'Reportes (todos)', allowed: true }
        ]},
        '2': { nombre: 'SUPERVISOR', permisos: [
            { text: 'Dashboard (completo)', allowed: true },
            { text: 'Cámaras (CRUD completo)', allowed: true },
            { text: 'Mantenimiento (programar/validar)', allowed: true },
            { text: 'Usuarios (solo ver)', allowed: false },
            { text: 'Bitácora (todas las acciones)', allowed: true },
            { text: 'Reportes (todos)', allowed: true }
        ]},
        '3': { nombre: 'TÉCNICO', permisos: [
            { text: 'Dashboard (sus tareas)', allowed: true },
            { text: 'Cámaras (crear/editar)', allowed: true },
            { text: 'Mantenimiento (ejecutar)', allowed: true },
            { text: 'Usuarios (no acceso)', allowed: false },
            { text: 'Bitácora (solo sus acciones)', allowed: true },
            { text: 'Reportes (no acceso)', allowed: false }
        ]}
    };

    function actualizarPermisos(rolId) {
        const infoBox = document.getElementById('infoPermisos');
        const rolNombre = document.getElementById('rolNombre');
        const listaPermisos = document.getElementById('listaPermisos');
        if (rolId && permisosPorRol[rolId]) {
            const rol = permisosPorRol[rolId];
            rolNombre.textContent = rol.nombre;
            listaPermisos.innerHTML = '';
            rol.permisos.forEach(p => {
                const li = document.createElement('li');
                li.innerHTML = `<i class="fas fa-${p.allowed ? 'check-circle text-success' : 'times-circle text-danger'}"></i> ${p.text}`;
                listaPermisos.appendChild(li);
            });
            infoBox.style.display = 'block';
        } else {
            infoBox.style.display = 'none';
        }
    }

    document.getElementById('rol_id').addEventListener('change', function() { actualizarPermisos(this.value); });
    document.addEventListener('DOMContentLoaded', function() { actualizarPermisos('<?php echo $usuario['rol_id']; ?>'); });
    </script>
</body>
</html>