<?php
session_start();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 2])) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

// ============================================
// DATOS PARA GRÁFICAS
// ============================================

// Cámaras por zona
$zonas_stmt = $pdo->query("SELECT zona, COUNT(*) as total FROM camaras GROUP BY zona ORDER BY zona");
$zonas_data = $zonas_stmt->fetchAll(PDO::FETCH_ASSOC);

// Estado de cámaras
$estados_stmt = $pdo->query("SELECT estatus, COUNT(*) as total FROM camaras GROUP BY estatus");
$estados_data = $estados_stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// REPORTES GENERADOS RECIENTEMENTE
// ============================================
$reportes_stmt = $pdo->query("
    SELECT b.id, b.fecha, b.descripcion, b.accion,
           u.nombre_completo AS usuario_nombre
    FROM bitacora_sistema b
    LEFT JOIN usuarios u ON b.usuario_id = u.id
    WHERE b.modulo = 'reportes'
    ORDER BY b.fecha DESC
    LIMIT 10
");
$reportes_recientes = $reportes_stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ESTADÍSTICAS RÁPIDAS
// ============================================
$total_camaras    = $pdo->query("SELECT COUNT(*) FROM camaras")->fetchColumn();
$total_activas    = $pdo->query("SELECT COUNT(*) FROM camaras WHERE estatus = 'activa'")->fetchColumn();
$total_mant       = $pdo->query("SELECT COUNT(*) FROM mantenimiento_tareas WHERE estado NOT IN ('cancelado')")->fetchColumn();
$total_pendientes = $pdo->query("SELECT COUNT(*) FROM camaras_validacion WHERE estado = 'pendiente'")->fetchColumn();

// Serializar para JS
$zonas_labels = json_encode(array_column($zonas_data, 'zona'));
$zonas_totals = json_encode(array_column($zonas_data, 'total'));
$estados_labels = json_encode(array_column($estados_data, 'estatus'));
$estados_totals = json_encode(array_column($estados_data, 'total'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reportes.css">
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

<button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<?php include '../../includes/sidebar.php'; ?>

<main class="dashboard-content">

    <!-- Encabezado -->
    <div class="reportes-header">
        <div class="reportes-title">
            <h1><i class="fas fa-chart-bar"></i> Reportes del Sistema</h1>
            <p>Genera y programa reportes del sistema</p>
        </div>
        <button class="btn-generar" onclick="abrirModal()">
            <i class="fas fa-plus"></i> Generar Reporte
        </button>
    </div>

    <!-- Tipos de Reporte -->
    <p class="section-title"><i class="fas fa-list"></i> Tipos de Reporte Disponibles</p>
    <div class="reportes-tipos">

        <div class="tipo-card" onclick="abrirModalConTipo('camaras_zona')">
            <div class="tipo-card-icon icon-camaras">
                <i class="fas fa-video"></i>
            </div>
            <div class="tipo-card-info">
                <h4>Cámaras por Zona</h4>
                <p>Distribución de cámaras por zonas del municipio</p>
            </div>
        </div>

        <div class="tipo-card" onclick="abrirModalConTipo('inventario_completo')">
            <div class="tipo-card-icon icon-inventario">
                <i class="fas fa-list-alt"></i>
            </div>
            <div class="tipo-card-info">
                <h4>Inventario Completo</h4>
                <p>Lista completa de cámaras con todos los detalles técnicos</p>
            </div>
        </div>

        <div class="tipo-card" onclick="abrirModalConTipo('mantenimientos_tecnico')">
            <div class="tipo-card-icon icon-mant">
                <i class="fas fa-tools"></i>
            </div>
            <div class="tipo-card-info">
                <h4>Mantenimientos por Técnico</h4>
                <p>Desempeño y tareas ejecutadas por técnico</p>
            </div>
        </div>

        <div class="tipo-card" onclick="abrirModalConTipo('estadisticas_operatividad')">
            <div class="tipo-card-icon icon-stats">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="tipo-card-info">
                <h4>Estadísticas de Operatividad</h4>
                <p>Porcentaje de operatividad de cámaras por periodo</p>
            </div>
        </div>

        <div class="tipo-card" onclick="abrirModalConTipo('tiempos_respuesta')">
            <div class="tipo-card-icon icon-tiempos">
                <i class="fas fa-clock"></i>
            </div>
            <div class="tipo-card-info">
                <h4>Tiempos de Respuesta</h4>
                <p>Tiempo promedio de resolución de mantenimientos</p>
            </div>
        </div>

        <div class="tipo-card" onclick="abrirModalConTipo('validaciones_pendientes')">
            <div class="tipo-card-icon icon-validacion">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="tipo-card-info">
                <h4>Validaciones Pendientes</h4>
                <p>Cámaras pendientes de validación por técnico</p>
            </div>
        </div>

    </div>

    <!-- Gráficas -->
    <div class="graficas-grid">
        <div class="grafica-card">
            <div class="grafica-header">
                <i class="fas fa-bar-chart"></i> Cámaras por Zona
            </div>
            <div class="grafica-body">
                <canvas id="graficaZonas" width="400" height="220"></canvas>
            </div>
        </div>
        <div class="grafica-card">
            <div class="grafica-header">
                <i class="fas fa-pie-chart"></i> Estado de Cámaras
            </div>
            <div class="grafica-body">
                <canvas id="graficaEstados" width="300" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Reportes Generados -->
    <div class="reportes-tabla-container">
        <div class="reportes-tabla-header">
            <h3><i class="fas fa-history"></i> Reportes Generados</h3>
            <span class="badge-count"><?php echo count($reportes_recientes); ?> recientes</span>
        </div>

        <?php if (count($reportes_recientes) > 0): ?>
        <table class="reportes-tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Generado por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportes_recientes as $rep): ?>
                <tr>
                    <td class="td-nombre"><?php echo htmlspecialchars($rep['descripcion']); ?></td>
                    <td class="td-fecha">
                        <?php echo (new DateTime($rep['fecha']))->format('d/m/Y H:i'); ?>
                    </td>
                    <td>
                        <span class="badge-tipo">
                            <i class="fas fa-file-csv"></i> CSV
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($rep['usuario_nombre'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="tabla-vacia">
            <i class="fas fa-chart-bar"></i>
            <p>Aún no se han generado reportes.</p>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- Modal Generar Reporte -->
<div class="modal-overlay" id="modalReporte" onclick="cerrarModalFuera(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-chart-bar"></i> Generar Nuevo Reporte</h3>
            <button class="modal-close" onclick="cerrarModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="generar.php" method="POST" id="formReporte">
            <div class="modal-body">

                <div class="modal-form-group">
                    <label for="tipo_reporte">Tipo de Reporte <span class="req">*</span></label>
                    <select name="tipo_reporte" id="tipo_reporte" class="modal-select" required>
                        <option value="">Seleccionar tipo</option>
                        <option value="camaras_zona">Cámaras por Zona</option>
                        <option value="inventario_completo">Inventario Completo</option>
                        <option value="mantenimientos_tecnico">Mantenimientos por Técnico</option>
                        <option value="estadisticas_operatividad">Estadísticas de Operatividad</option>
                        <option value="tiempos_respuesta">Tiempos de Respuesta</option>
                        <option value="validaciones_pendientes">Validaciones Pendientes</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label>Rango de Fechas <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                    <div class="fechas-row">
                        <input type="date" name="fecha_ini" id="fecha_ini" class="modal-input" placeholder="Fecha inicio">
                        <input type="date" name="fecha_fin" id="fecha_fin" class="modal-input" placeholder="Fecha fin">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="formato">Formato de Exportación</label>
                    <select name="formato" id="formato" class="modal-select">
                        <option value="csv">Excel / CSV</option>
                    </select>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancelar-modal" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-generar-modal" id="btnGenerar">
                    <i class="fas fa-download"></i> Generar Reporte
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="../../assets/js/dashboard.js"></script>
<script>
// ============================================
// GRÁFICAS
// ============================================
const zonasLabels  = <?php echo $zonas_labels; ?>;
const zonasTotals  = <?php echo $zonas_totals; ?>;
const estadosLabels = <?php echo $estados_labels; ?>;
const estadosTotals = <?php echo $estados_totals; ?>;

const coloresPrimary = [
    '#69163e', '#8b1d52', '#a82460', '#c42b6e',
    '#17a2b8', '#28a745', '#fd7e14', '#6f42c1'
];

const coloresEstado = {
    'activa':      '#28a745',
    'pendiente':   '#ffc107',
    'mantenimiento': '#17a2b8',
    'fuera_servicio': '#dc3545',
    'inactiva':    '#6c757d'
};

// Gráfica de barras — Cámaras por Zona
new Chart(document.getElementById('graficaZonas'), {
    type: 'bar',
    data: {
        labels: zonasLabels,
        datasets: [{
            label: 'Cámaras',
            data: zonasTotals,
            backgroundColor: coloresPrimary.slice(0, zonasLabels.length),
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Gráfica de dona — Estado de Cámaras
new Chart(document.getElementById('graficaEstados'), {
    type: 'doughnut',
    data: {
        labels: estadosLabels,
        datasets: [{
            data: estadosTotals,
            backgroundColor: estadosLabels.map(e => coloresEstado[e] || '#6c757d'),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 12 }, padding: 12 }
            }
        }
    }
});

// ============================================
// MODAL
// ============================================
function abrirModal() {
    document.getElementById('modalReporte').classList.add('active');
}

function abrirModalConTipo(tipo) {
    document.getElementById('tipo_reporte').value = tipo;
    document.getElementById('modalReporte').classList.add('active');
}

function cerrarModal() {
    document.getElementById('modalReporte').classList.remove('active');
}

function cerrarModalFuera(e) {
    if (e.target === document.getElementById('modalReporte')) cerrarModal();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarModal();
});

// Deshabilitar botón al enviar para evitar doble click
document.getElementById('formReporte').addEventListener('submit', function() {
    const btn = document.getElementById('btnGenerar');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
    btn.disabled = true;
});
</script>
</body>
</html>