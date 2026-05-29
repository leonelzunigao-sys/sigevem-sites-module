<?php
session_start();

// Solo Admin puede ver detalles de usuarios
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

// ============================================
// OBTENER ID DEL USUARIO
// ============================================
$id = $_GET['id'] ?? 0;

// ============================================
// OBTENER DATOS DEL USUARIO
// ============================================
$stmt = $pdo->prepare("
    SELECT u.*, r.nombre as rol_nombre 
    FROM usuarios u 
    LEFT JOIN roles r ON u.rol_id = r.id 
    WHERE u.id = ?
");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: index.php?error=not_found');
    exit;
}

$fecha_actual = date('l, j \d\e F Y — H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Usuario | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/usuarios.css">
</head>
<body>
    <!-- Header -->
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

    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Detalles del Usuario</h1>
                <p class="page-subtitle">Información completa del usuario</p>
                <p class="page-date"><?php echo $fecha_actual; ?></p>
            </div>
            <div class="btn-group">
                <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <!-- Información Principal -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Información Personal</h3>
            </div>
            <div class="card-body">
                <div class="user-detail-grid">
                    <div class="detail-item">
                        <label>ID de Usuario:</label>
                        <span><?php echo $usuario['id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Nombre Completo:</label>
                        <span><?php echo htmlspecialchars($usuario['nombre_completo']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Correo Electrónico:</label>
                        <span><?php echo htmlspecialchars($usuario['email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Rol del Sistema:</label>
                        <span class="badge badge-<?php 
                            echo $usuario['rol_id'] == 1 ? 'warning' : 
                                ($usuario['rol_id'] == 2 ? 'purple' : 'info'); 
                        ?>">
                            <?php echo htmlspecialchars($usuario['rol_nombre']); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Estado:</label>
                        <span class="badge badge-<?php echo $usuario['estatus'] == 'activo' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($usuario['estatus']); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Fecha de Creación:</label>
                        <span><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Último Acceso:</label>
                        <span>
                            <?php if (!empty($usuario['fecha_ultimo_acceso'])): ?>
                                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_ultimo_acceso'])); ?>
                            <?php else: ?>
                                <span class="text-muted">Nunca ha accedido</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Última IP:</label>
                        <span>
                            <?php if (!empty($usuario['ultimo_ip'])): ?>
                                <code><?php echo htmlspecialchars($usuario['ultimo_ip']); ?></code>
                            <?php else: ?>
                                <span class="text-muted">No registrada</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Permisos del Rol -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt"></i> Permisos del Rol</h3>
            </div>
            <div class="card-body">
                <div class="permisos-list" id="permisosList">
                    <!-- Se llena con JavaScript -->
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Acciones Rápidas</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions-grid">
                    <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="quick-action-btn btn-primary">
                        <i class="fas fa-edit"></i>
                        <span>Editar Usuario</span>
                    </a>
                    <a href="reset_password.php?id=<?php echo $usuario['id']; ?>" class="quick-action-btn btn-warning">
                        <i class="fas fa-key"></i>
                        <span>Resetear Contraseña</span>
                    </a>
                    <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                    <a href="toggle_estado.php?id=<?php echo $usuario['id']; ?>" class="quick-action-btn <?php echo $usuario['estatus'] == 'activo' ? 'btn-danger' : 'btn-success'; ?>">
                        <i class="fas fa-<?php echo $usuario['estatus'] == 'activo' ? 'ban' : 'check'; ?>"></i>
                        <span><?php echo $usuario['estatus'] == 'activo' ? 'Desactivar' : 'Activar'; ?></span>
                    </a>
                    <a href="eliminar.php?id=<?php echo $usuario['id']; ?>" class="quick-action-btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                        <i class="fas fa-trash"></i>
                        <span>Eliminar Usuario</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
    // ============================================
    // MOSTRAR PERMISOS DEL ROL
    // ============================================
    const permisosPorRol = {
        '1': {
            nombre: 'ADMINISTRADOR',
            permisos: [
                { icon: 'check-circle', text: 'Dashboard (completo)', allowed: true },
                { icon: 'check-circle', text: 'Cámaras (CRUD completo)', allowed: true },
                { icon: 'check-circle', text: 'Mapa (completo)', allowed: true },
                { icon: 'check-circle', text: 'Mantenimiento (programar/ejecutar/validar)', allowed: true },
                { icon: 'check-circle', text: 'Usuarios (gestión completa)', allowed: true },
                { icon: 'check-circle', text: 'Bitácora (todas las acciones)', allowed: true },
                { icon: 'check-circle', text: 'Reportes (todos)', allowed: true }
            ]
        },
        '2': {
            nombre: 'SUPERVISOR',
            permisos: [
                { icon: 'check-circle', text: 'Dashboard (completo)', allowed: true },
                { icon: 'check-circle', text: 'Cámaras (CRUD completo)', allowed: true },
                { icon: 'check-circle', text: 'Mapa (completo)', allowed: true },
                { icon: 'check-circle', text: 'Mantenimiento (programar/validar)', allowed: true },
                { icon: 'times-circle', text: 'Usuarios (solo ver)', allowed: false },
                { icon: 'check-circle', text: 'Bitácora (todas las acciones)', allowed: true },
                { icon: 'check-circle', text: 'Reportes (todos)', allowed: true }
            ]
        },
        '3': {
            nombre: 'TÉCNICO',
            permisos: [
                { icon: 'check-circle', text: 'Dashboard (sus tareas)', allowed: true },
                { icon: 'check-circle', text: 'Cámaras (crear/editar)', allowed: true },
                { icon: 'check-circle', text: 'Mapa (completo)', allowed: true },
                { icon: 'check-circle', text: 'Mantenimiento (ejecutar)', allowed: true },
                { icon: 'times-circle', text: 'Usuarios (no acceso)', allowed: false },
                { icon: 'check-circle', text: 'Bitácora (solo sus acciones)', allowed: true },
                { icon: 'times-circle', text: 'Reportes (no acceso)', allowed: false }
            ]
        }
    };

    const rolId = '<?php echo $usuario['rol_id']; ?>';
    const permisosList = document.getElementById('permisosList');

    if (permisosPorRol[rolId]) {
        const rol = permisosPorRol[rolId];
        
        rol.permisos.forEach(permiso => {
            const div = document.createElement('div');
            div.className = 'permiso-item';
            div.innerHTML = `
                <i class="fas fa-${permiso.icon} ${permiso.allowed ? 'text-success' : 'text-danger'}"></i>
                <span>${permiso.text}</span>
            `;
            permisosList.appendChild(div);
        });
    }
    </script>
</body>
</html>