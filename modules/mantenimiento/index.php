<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../config/database.php';

// Filtro por rol — técnico solo ve sus tareas
$es_tecnico = $_SESSION['rol_id'] == 3;
$filtro_tecnico = $es_tecnico ? "AND tecnico_id = {$_SESSION['usuario_id']}" : "";
$filtro_tecnico_where = $es_tecnico ? "WHERE tecnico_id = {$_SESSION['usuario_id']}" : "";

// Obtener KPIs
$kpi_pendientes = $pdo->query("SELECT COUNT(*) FROM mantenimiento_tareas WHERE estado = 'pendiente' {$filtro_tecnico}")->fetchColumn();
$kpi_proceso    = $pdo->query("SELECT COUNT(*) FROM mantenimiento_tareas WHERE estado = 'en_proceso' {$filtro_tecnico}")->fetchColumn();
$kpi_completados = $pdo->query("SELECT COUNT(*) FROM mantenimiento_tareas WHERE estado = 'completado' {$filtro_tecnico}")->fetchColumn();

// Obtener tareas con información de cámaras y técnicos
$sql = "SELECT 
    mt.*,
    c.inventario_id,
    c.marca,
    c.direccion,
    u.nombre_completo as tecnico_nombre,
    u2.nombre_completo as programado_por
FROM mantenimiento_tareas mt
LEFT JOIN camaras c ON mt.camara_id = c.id
LEFT JOIN usuarios u ON mt.tecnico_id = u.id
LEFT JOIN usuarios u2 ON mt.programado_por_id = u2.id
{$filtro_tecnico_where}
ORDER BY mt.fecha_programada DESC";

$stmt = $pdo->query($sql);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Cámaras | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/mantenimiento.css">
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

        <!-- Botón menú móvil -->
<button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Mantenimiento de Cámaras</h1>
            <p class="page-subtitle">Programación y seguimiento de tareas</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success mb-3">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger mb-3">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="kpi-container mb-4">
            <div class="kpi-card kpi-warning">
                <div class="kpi-number"><?php echo $kpi_pendientes; ?></div>
                <div class="kpi-label">Pendientes</div>
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="kpi-card kpi-info">
                <div class="kpi-number"><?php echo $kpi_proceso; ?></div>
                <div class="kpi-label">En Proceso</div>
                <i class="fas fa-clock"></i>
            </div>
            <div class="kpi-card kpi-success">
                <div class="kpi-number"><?php echo $kpi_completados; ?></div>
                <div class="kpi-label">Completados</div>
                <i class="fas fa-check-circle"></i>
            </div>
        </div>

        <!-- Filtros y Botón -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="search-box" style="flex: 1; min-width: 300px;">
                        <div class="input-with-icon">
                            <i class="fas fa-search input-icon"></i>
                            <input type="text" class="form-control" placeholder="Buscar por cámara, técnico o descripción..." id="searchInput">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <select class="form-control" style="width: auto;" id="filterEstado">
                            <option value="">Todos los estados</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="completado">Completado</option>
                            <option value="validado">Validado</option>
                        </select>
                        <select class="form-control" style="width: auto;" id="filterTipo">
                            <option value="">Todos los tipos</option>
                            <option value="preventivo">Preventivo</option>
                            <option value="correctivo">Correctivo</option>
                        </select>
                    </div>
                    <?php if ($_SESSION['rol_id'] != 3): // No técnicos ?>
                    <a href="programar.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Programar Mantenimiento
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabla de Tareas -->
        <div class="card">
            <div class="card-header">
                <h3>Tareas de Mantenimiento</h3>
                <span class="badge badge-primary"><?php echo count($tareas); ?> tareas</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover" id="tareasTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cámara</th>
                                <th>Tipo</th>
                                <th>Técnico</th>
                                <th>Programado</th>
                                <th>Límite</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tareas as $tarea): ?>
                            <tr data-id="<?php echo $tarea['id']; ?>">
                                <td><strong class="text-primary">MNT-<?php echo str_pad($tarea['id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($tarea['inventario_id']); ?></strong>
                                        <div class="text-muted small"><?php echo htmlspecialchars(substr($tarea['descripcion'], 0, 50)) . '...'; ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $tarea['tipo'] == 'preventivo' ? 'info' : 'warning'; ?>">
                                        <?php echo ucfirst($tarea['tipo']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($tarea['tecnico_nombre'] ?? 'Sin asignar'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($tarea['fecha_programada'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($tarea['fecha_limite'])); ?></td>
                                <td>
                                    <?php
                                    $estado_class = [
                                        'pendiente' => 'badge-warning',
                                        'en_proceso' => 'badge-info',
                                        'completado' => 'badge-success',
                                        'validado' => 'badge-success'
                                    ];
                                    $estado_label = [
                                        'pendiente' => 'Pendiente',
                                        'en_proceso' => 'En Proceso',
                                        'completado' => 'Completado',
                                        'validado' => 'Validado'
                                    ];
                                    $estado = $tarea['estado'];
                                    ?>
                                    <span class="badge <?php echo $estado_class[$estado] ?? 'badge-secondary'; ?>">
                                        <?php echo $estado_label[$estado] ?? $estado; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="ver.php?id=<?php echo $tarea['id']; ?>" class="btn btn-outline-primary" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($_SESSION['rol_id'] == 3 && $tarea['estado'] == 'pendiente'): ?>
                                        <a href="ejecutar.php?id=<?php echo $tarea['id']; ?>" class="btn btn-outline-success" title="Ejecutar">
                                            <i class="fas fa-play"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['rol_id'] == 1 && $tarea['estado'] == 'completado'): ?>
                                        <a href="validar.php?id=<?php echo $tarea['id']; ?>" class="btn btn-outline-warning" title="Validar">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['rol_id'] == 1 && in_array($tarea['estado'], ['pendiente', 'en_proceso'])): ?>
                                        <a href="cancelar.php?id=<?php echo $tarea['id']; ?>" class="btn btn-outline-danger" 
                                           onclick="return confirm('¿Cancelar esta tarea?')" title="Cancelar">
                                            <i class="fas fa-times"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
    // Filtros
    document.getElementById('searchInput').addEventListener('input', filterTable);
    document.getElementById('filterEstado').addEventListener('change', filterTable);
    document.getElementById('filterTipo').addEventListener('change', filterTable);

    function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const filterEstado = document.getElementById('filterEstado').value.toLowerCase();
    const filterTipo = document.getElementById('filterTipo').value.toLowerCase();
    const rows = document.querySelectorAll('#tareasTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        
        // Obtener el badge de estado
        const estadoBadge = row.querySelector('td:nth-child(7) .badge');
        const estadoText = estadoBadge ? estadoBadge.textContent.toLowerCase().trim() : '';
        
        // Obtener el badge de tipo
        const tipoBadge = row.querySelector('td:nth-child(3) .badge');
        const tipoText = tipoBadge ? tipoBadge.textContent.toLowerCase().trim() : '';
        
        // Mapear estados
        const estadoMap = {
            'pendiente': 'pendiente',
            'en proceso': 'en_proceso',
            'completado': 'completado',
            'validado': 'validado'
        };
        
        const estadoNormalizado = estadoMap[estadoText] || estadoText;
        
        // Aplicar filtros
        const matchSearch = text.includes(search);
        const matchEstado = !filterEstado || estadoNormalizado === filterEstado || estadoText.includes(filterEstado);
        const matchTipo = !filterTipo || tipoText.includes(filterTipo);
        
        row.style.display = (matchSearch && matchEstado && matchTipo) ? '' : 'none';
    });
}
    </script>
</body>
</html>