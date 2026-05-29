<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

// Obtener filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_estado = isset($_GET['filter_estado']) ? $_GET['filter_estado'] : '';
$filter_zona = isset($_GET['filter_zona']) ? $_GET['filter_zona'] : '';

// Construir consulta base
$sql = "SELECT s.*, sv.estado as validacion_estado_actual
        FROM sitios s
        LEFT JOIN sitios_validacion sv ON s.id = sv.sitio_id 
            AND sv.id = (SELECT MAX(id) FROM sitios_validacion WHERE sitio_id = s.id)";

$params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(s.inventario_id ILIKE :search OR s.nombre ILIKE :search OR s.calle ILIKE :search OR s.colonia ILIKE :search)";
    $params[':search'] = "%{$search}%";
}
if (!empty($filter_estado)) {
    $where_clauses[] = "s.estado = :estado";
    $params[':estado'] = $filter_estado;
}
if (!empty($filter_zona)) {
    $where_clauses[] = "s.zona = :zona";
    $params[':zona'] = $filter_zona;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY s.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sitios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$stats = [
    'total' => count($sitios),
    'activos' => 0,
    'mantenimiento' => 0,
    'pendientes' => 0
];

foreach ($sitios as $s) {
    $estado = strtolower($s['estado']);
    if ($estado === 'activo') $stats['activos']++;
    elseif ($estado === 'mantenimiento') $stats['mantenimiento']++;
    elseif ($estado === 'pendiente') $stats['pendientes']++;
}

// Total de activos tecnológicos
$total_activos = 0;
foreach ($sitios as $s) {
    $total_activos += ($s['activos_computadoras'] + $s['activos_servidores'] + $s['activos_impresoras'] + $s['activos_otros']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Sitios | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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

    <!-- Botón menú móvil -->
    <button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <main class="dashboard-content">
        
        <!-- ENCABEZADO CON BOTONES -->
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
            <div>
                <h1 class="page-title">Registro de Sitios</h1>
                <p class="page-subtitle">Gestión de inmuebles y activos tecnológicos municipales</p>
            </div>
            
            <!-- BOTONES -->
            <div class="d-flex gap-2">
                <a href="mantenimiento_listado.php" class="btn btn-outline-warning">
                    <i class="fas fa-list-check"></i> Ver Mantenimientos
                </a>
                <a href="registro.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Registrar Sitio
                </a>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div class="stat-card" style="background: var(--bg-light); padding: 15px; border-radius: 8px; border-left: 4px solid var(--primary);">
                <div style="color: var(--text-muted); font-size: 14px;">Total Sitios</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);"><?php echo $stats['total']; ?></div>
                <div style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-building"></i> <?php echo $total_activos; ?> activos tecnológicos</div>
            </div>
            <div class="stat-card" style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                <div style="color: #155724; font-size: 14px;">Activos</div>
                <div style="font-size: 28px; font-weight: 700; color: #155724;"><?php echo $stats['activos']; ?></div>
            </div>
            <div class="stat-card" style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                <div style="color: #856404; font-size: 14px;">En Mantenimiento</div>
                <div style="font-size: 28px; font-weight: 700; color: #856404;"><?php echo $stats['mantenimiento']; ?></div>
            </div>
            <div class="stat-card" style="background: #e2d4f0; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">
                <div style="color: #4a148c; font-size: 14px;">Pendientes</div>
                <div style="font-size: 28px; font-weight: 700; color: #4a148c;"><?php echo $stats['pendientes']; ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
                    <div class="form-group" style="flex: 2;">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, dirección o zona..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <select name="filter_estado" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo $filter_estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                            <option value="mantenimiento" <?php echo $filter_estado == 'mantenimiento' ? 'selected' : ''; ?>>En Mantenimiento</option>
                            <option value="fuera_servicio" <?php echo $filter_estado == 'fuera_servicio' ? 'selected' : ''; ?>>Fuera de Servicio</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="filter_zona" class="form-control">
                            <option value="">Todas las zonas</option>
                            <option value="Norte" <?php echo $filter_zona == 'Norte' ? 'selected' : ''; ?>>Norte</option>
                            <option value="Sur" <?php echo $filter_zona == 'Sur' ? 'selected' : ''; ?>>Sur</option>
                            <option value="Centro" <?php echo $filter_zona == 'Centro' ? 'selected' : ''; ?>>Centro</option>
                            <option value="Oriente" <?php echo $filter_zona == 'Oriente' ? 'selected' : ''; ?>>Oriente</option>
                            <option value="Poniente" <?php echo $filter_zona == 'Poniente' ? 'selected' : ''; ?>>Poniente</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
                </form>
            </div>
        </div>

        <!-- Tabla de Sitios -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3><i class="fas fa-building"></i> Inventario de Sitios</h3>
                <span class="badge badge-secondary"><?php echo count($sitios); ?> sitios</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre / Dirección</th>
                                <th>Zona</th>
                                <th>Tipo</th>
                                <th>Total Activos</th>
                                <th>Estado</th>
                                <th>Validación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sitios)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-search" style="font-size: 24px; opacity: 0.3;"></i><br>
                                    No se encontraron sitios con los filtros seleccionados
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($sitios as $sitio): ?>
                                <tr>
                                    <td><strong class="text-primary"><?php echo htmlspecialchars($sitio['inventario_id']); ?></strong></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($sitio['nombre']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($sitio['calle'] . ' ' . $sitio['numero_exterior'] . ', ' . $sitio['colonia']); ?></small>
                                    </td>
                                    <td><span class="badge badge-outline"><?php echo htmlspecialchars($sitio['zona']); ?></span></td>
                                    <td><?php echo htmlspecialchars($sitio['tipo_inmueble']); ?></td>
                                    <td>
                                        <?php 
                                        $total = $sitio['activos_computadoras'] + $sitio['activos_servidores'] + $sitio['activos_impresoras'] + $sitio['activos_otros'];
                                        echo "<strong>{$total} activos</strong><br>";
                                        echo "<small class='text-muted'>";
                                        if($sitio['activos_computadoras'] > 0) echo "💻 {$sitio['activos_computadoras']} ";
                                        if($sitio['activos_servidores'] > 0) echo "🖥️ {$sitio['activos_servidores']} ";
                                        if($sitio['activos_impresoras'] > 0) echo "🖨️ {$sitio['activos_impresoras']} ";
                                        echo "</small>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $estado_class = match(strtolower($sitio['estado'])) {
                                            'activo' => 'badge-success',
                                            'mantenimiento' => 'badge-warning',
                                            'fuera_servicio' => 'badge-danger',
                                            default => 'badge-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $estado_class; ?>"><?php echo htmlspecialchars($sitio['estado']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $val_estado = $sitio['validacion_estado_actual'] ?? $sitio['validacion_estado'];
                                        $val_class = match(strtolower($val_estado)) {
                                            'aprobada' => 'badge-success',
                                            'rechazada' => 'badge-danger',
                                            default => 'badge-info'
                                        };
                                        ?>
                                        <span class="badge <?php echo $val_class; ?>"><?php echo htmlspecialchars($val_estado); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="ver.php?id=<?php echo $sitio['id']; ?>" class="btn btn-sm btn-icon btn-outline-primary" title="Ver detalle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="editar.php?id=<?php echo $sitio['id']; ?>" class="btn btn-sm btn-icon btn-outline-secondary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="mantenimiento_programar.php?id=<?php echo $sitio['id']; ?>" 
                                               class="btn btn-sm btn-icon btn-warning" 
                                               title="Programar Mantenimiento">
                                                <i class="fas fa-tools"></i>
                                            </a>
                                            <?php if (strtolower($val_estado) === 'pendiente' && ($_SESSION['rol_id'] == 1 || $_SESSION['rol_id'] == 2)): ?>
                                                <a href="validar.php?id=<?php echo $sitio['id']; ?>&accion=aprobar" class="btn btn-sm btn-icon btn-success" title="Aprobar">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="validar.php?id=<?php echo $sitio['id']; ?>&accion=rechazar" class="btn btn-sm btn-icon btn-danger" title="Rechazar">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['rol_id'] == 1): ?>
                                                <a href="eliminar.php?id=<?php echo $sitio['id']; ?>" 
                                                   class="btn btn-sm btn-icon btn-danger" 
                                                   title="Eliminar"
                                                   onclick="return confirm('¿Estás seguro de eliminar este sitio? Esta acción no se puede deshacer.');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>