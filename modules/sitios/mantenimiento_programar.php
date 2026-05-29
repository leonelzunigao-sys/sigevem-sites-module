<?php
session_start();

// Solo Admin (1) y Supervisor (2) pueden programar mantenimiento
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2)) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

$sitio_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sitio_id <= 0) {
    $_SESSION['error_message'] = 'ID de sitio no válido';
    header('Location: index.php');
    exit;
}

// Obtener datos del sitio
$sql = "SELECT * FROM sitios WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $sitio_id]);
$sitio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sitio) {
    $_SESSION['error_message'] = 'Sitio no encontrado';
    header('Location: index.php');
    exit;
}

// Obtener técnicos (usuarios con rol_id = 3)
$tecnicos_sql = "SELECT id, nombre_completo, email FROM usuarios WHERE rol_id = 3 ORDER BY nombre_completo ASC";
$tecnicos_stmt = $pdo->query($tecnicos_sql);
$tecnicos = $tecnicos_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar Mantenimiento | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <style>
    .form-section {
        background: var(--bg-light);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .form-section-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--gray-200);
    }
    .site-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        background: white;
        padding: 15px;
        border-radius: 6px;
        border-left: 4px solid var(--primary);
    }
    .summary-item {
        display: flex;
        flex-direction: column;
    }
    .summary-label {
        font-size: 11px;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 600;
    }
    .summary-value {
        font-weight: 500;
        color: var(--text-dark);
    }
    .required::after {
        content: " *";
        color: #dc3545;
    }
    </style>
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
        <div class="page-header">
            <h1 class="page-title">Programar Mantenimiento</h1>
            <p class="page-subtitle">Sitio: <?php echo htmlspecialchars($sitio['inventario_id'] . ' — ' . $sitio['nombre']); ?></p>
        </div>

        <form action="mantenimiento_process.php" method="POST" id="mantenimientoForm">
            <input type="hidden" name="sitio_id" value="<?php echo $sitio_id; ?>">
            
            <!-- Resumen del Sitio (Solo lectura) -->
            <div class="card">
                <div class="card-body">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Información del Sitio
                    </div>
                    <div class="site-summary">
                        <div class="summary-item">
                            <span class="summary-label">ID</span>
                            <span class="summary-value"><?php echo htmlspecialchars($sitio['inventario_id']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Nombre</span>
                            <span class="summary-value"><?php echo htmlspecialchars($sitio['nombre']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tipo</span>
                            <span class="summary-value"><?php echo htmlspecialchars($sitio['tipo_inmueble']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Zona</span>
                            <span class="summary-value"><?php echo htmlspecialchars($sitio['zona']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Dirección</span>
                            <span class="summary-value"><?php echo htmlspecialchars($sitio['calle'] . ' ' . $sitio['numero_exterior']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Estado Actual</span>
                            <span class="summary-value">
                                <span class="badge badge-<?php echo $sitio['estado'] == 'activo' ? 'success' : ($sitio['estado'] == 'mantenimiento' ? 'warning' : 'danger'); ?>">
                                    <?php echo htmlspecialchars($sitio['estado']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos del Mantenimiento -->
            <div class="card">
                <div class="card-body">
                    <div class="form-section-title">
                        <i class="fas fa-tools"></i> Datos del Mantenimiento
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required" for="tipo">Tipo de Mantenimiento *</label>
                            <select class="form-control" id="tipo" name="tipo" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="preventivo">🟢 Preventivo (Programado)</option>
                                <option value="correctivo">🟡 Correctivo (Por falla)</option>
                                <option value="emergencia">🔴 Emergencia (Crítico)</option>
                            </select>
                            <small class="form-text">Selecciona el tipo según la naturaleza del trabajo</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="required" for="tecnico_id">Técnico Responsable *</label>
                            <select class="form-control" id="tecnico_id" name="tecnico_id" required>
                                <option value="">Seleccionar técnico</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>">
                                        <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text">Solo se muestran usuarios con rol de Técnico</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="required" for="fecha_programada">Fecha Programada *</label>
                            <input type="date" class="form-control" id="fecha_programada" name="fecha_programada" 
                                   value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_limite">Fecha Límite (Opcional)</label>
                            <input type="date" class="form-control" id="fecha_limite" name="fecha_limite" 
                                   min="<?php echo date('Y-m-d'); ?>">
                            <small class="form-text">Fecha máxima para completar el trabajo</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required" for="descripcion">Descripción del Trabajo *</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="4" 
                                  placeholder="Describe las actividades a realizar, equipos a revisar, repuestos necesarios, etc." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="prioridad">Prioridad</label>
                        <select class="form-control" id="prioridad" name="prioridad">
                            <option value="baja">🟢 Baja</option>
                            <option value="media" selected>🟡 Media</option>
                            <option value="alta">🔴 Alta</option>
                            <option value="critica">⚫ Crítica</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-between">
                <a href="ver.php?id=<?php echo $sitio_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-warning btn-lg">
                    <i class="fas fa-calendar-check"></i> Programar Mantenimiento
                </button>
            </div>
        </form>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
    // Validación del formulario
    document.getElementById('mantenimientoForm').addEventListener('submit', function(e) {
        const tipo = document.getElementById('tipo').value;
        const tecnico = document.getElementById('tecnico_id').value;
        const fecha = document.getElementById('fecha_programada').value;
        const descripcion = document.getElementById('descripcion').value;
        
        if (!tipo || !tecnico || !fecha || !descripcion) {
            e.preventDefault();
            alert('Por favor completa todos los campos obligatorios (*)');
            return false;
        }
        
        // Confirmación
        if (!confirm('¿Confirmar programación de mantenimiento para este sitio?')) {
            e.preventDefault();
            return false;
        }
        
        // Deshabilitar botón para evitar doble envío
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Programando...';
        btn.disabled = true;
    });
    </script>
</body>
</html>