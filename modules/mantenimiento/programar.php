<?php
session_start();

// Solo Admin y Supervisor pueden programar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] == 3) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

// Obtener técnicos para el select (esto lo mantenemos)
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
    <link rel="stylesheet" href="../../assets/css/mantenimiento.css">
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
    }
    .dropzone {
        border: 2px dashed var(--gray-300);
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: white;
    }
    .dropzone:hover {
        border-color: var(--primary);
        background: var(--bg-light);
    }
    .dropzone i {
        font-size: 48px;
        color: var(--gray-400);
        margin-bottom: 10px;
    }
    .dropzone input[type="file"] {
        display: none;
    }
    
    /* ==========================================
       NUEVO: Estilos para el buscador de cámaras
       ========================================== */
    .sugerencias-lista {
        position: absolute;
        background: #fff;
        border: 1px solid #ccc;
        border-top: none;
        list-style: none;
        padding: 0;
        margin: 0;
        width: 100%;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: none;
        border-radius: 0 0 4px 4px;
    }

    .sugerencias-lista li {
        padding: 12px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }

    .sugerencias-lista li:hover {
        background-color: #f8f9fa;
    }

    .sugerencias-lista li strong {
        color: var(--primary);
        display: block;
        margin-bottom: 3px;
    }

    .sugerencias-lista li small {
        color: #666;
        font-size: 13px;
    }

    .camara-seleccionada {
        background: #e8f5e9;
        border-left: 3px solid var(--primary);
        padding: 10px;
        margin-top: 10px;
        border-radius: 4px;
        display: none;
    }

    .camara-seleccionada.visible {
        display: block;
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
            <p class="page-subtitle">Crear nueva tarea de mantenimiento</p>
        </div>

        <form action="programar_process.php" method="POST" enctype="multipart/form-data" id="programarForm">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-tools"></i> Información de la Tarea</h3>
                    <a href="index.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Regresar
                    </a>
                </div>
                <div class="card-body">
                    
                    <!-- Sección 1: Datos Generales -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-info-circle"></i> Datos Generales
                        </div>
                        
                        <div class="form-row">
                            <!-- ==========================================
                                 MODIFICADO: Buscador inteligente de cámaras
                                 ========================================== -->
                            <div class="form-group" style="position: relative;">
                                <label for="buscador-camara">Cámara *</label>
                                
                                <!-- Input visible para búsqueda -->
                                <input type="text" 
                                       id="buscador-camara" 
                                       class="form-control" 
                                       placeholder="Escribe ID, marca o ubicación..." 
                                       autocomplete="off"
                                       required>
                                
                                <!-- Input oculto: guarda el ID real para el INSERT -->
                                <input type="hidden" 
                                       id="camara_id_real" 
                                       name="camara_id" 
                                       required>
                                
                                <!-- Lista de sugerencias -->
                                <ul id="sugerencias-camaras" class="sugerencias-lista"></ul>
                                
                                <!-- Preview de cámara seleccionada -->
                                <div id="camara-preview" class="camara-seleccionada">
                                    <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                    <strong id="preview-nombre"></strong>
                                    <small id="preview-ubicacion" class="text-muted d-block"></small>
                                </div>
                                
                                <small class="form-text">Busca por ID (CAM-001), marca (Hikvision) o ubicación</small>
                            </div>
                            <!-- ========================================== -->
                            
                            <div class="form-group">
                                <label for="tipo">Tipo de Mantenimiento *</label>
                                <div class="radio-group">
                                    <label class="radio-inline">
                                        <input type="radio" name="tipo" value="preventivo" checked required>
                                        <span class="badge badge-info">Preventivo</span>
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" name="tipo" value="correctivo">
                                        <span class="badge badge-warning">Correctivo</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="tecnico_id">Técnico Asignado *</label>
                                <select class="form-control" id="tecnico_id" name="tecnico_id" required>
                                    <option value="">Seleccionar técnico</option>
                                    <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>" data-email="<?php echo htmlspecialchars($tecnico['email']); ?>">
                                        <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text">Solo se muestran usuarios con rol de Técnico</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="descripcion">Descripción del Trabajo *</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" 
                                          placeholder="Describe detalladamente el trabajo a realizar..." required></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Fechas -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-calendar-alt"></i> Fechas
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fecha_programada">Fecha Programada *</label>
                                <input type="date" class="form-control" id="fecha_programada" name="fecha_programada" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="fecha_limite">Fecha Límite *</label>
                                <input type="date" class="form-control" id="fecha_limite" name="fecha_limite" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Documentación -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-paperclip"></i> Documentación Adjunta
                        </div>
                        
                        <div class="form-group">
                            <label class="dropzone" onclick="document.getElementById('documentacion').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div><strong>Arrastra archivos o selecciona</strong></div>
                                <small class="text-muted">PDF, DOC, JPG, PNG (Máx. 10MB)</small>
                                <input type="file" id="documentacion" name="documentacion" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            </label>
                            <div id="fileInfo" class="mt-2 text-muted small"></div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="notificar_email" value="1" checked>
                                <span>Notificar al técnico por correo electrónico</span>
                            </label>
                        </div>
                    </div>

                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-calendar-check"></i> Programar Mantenimiento
                </button>
            </div>
        </form>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
    // ==========================================
    // NUEVO: Buscador inteligente de cámaras
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        const inputBuscador = document.getElementById('buscador-camara');
        const listaSugerencias = document.getElementById('sugerencias-camaras');
        const inputRealId = document.getElementById('camara_id_real');
        const camaraPreview = document.getElementById('camara-preview');
        const previewNombre = document.getElementById('preview-nombre');
        const previewUbicacion = document.getElementById('preview-ubicacion');

        // Escuchar cuando el usuario escribe
        inputBuscador.addEventListener('input', function() {
            const texto = this.value.trim();
            
            // Si el campo está vacío, limpiar todo
            if (texto.length < 2) {
                listaSugerencias.style.display = 'none';
                inputRealId.value = '';
                camaraPreview.classList.remove('visible');
                return;
            }

            // Petición Fetch al archivo que creamos
            clearTimeout(window.busquedaTimeout);
            window.busquedaTimeout = setTimeout(() => {
                fetch(`buscar_camaras.php?q=${encodeURIComponent(texto)}`)
                    .then(res => res.json())
                    .then(data => {
                        listaSugerencias.innerHTML = '';
                        
                        if (data.length > 0) {
                            listaSugerencias.style.display = 'block';
                            data.forEach(camara => {
                                const li = document.createElement('li');
                                li.innerHTML = `
                                    <strong>${camara.inventario_id}</strong>
                                    <small>${camara.marca} • ${camara.direccion} • Zona: ${camara.zona}</small>
                                `;
                                
                                li.addEventListener('click', function() {
                                    // Al hacer clic, llenamos los inputs
                                    inputBuscador.value = camara.inventario_id;
                                    inputRealId.value = camara.id;
                                    
                                    // Mostrar preview
                                    previewNombre.textContent = `${camara.inventario_id} - ${camara.marca}`;
                                    previewUbicacion.textContent = `${camara.direccion}, Zona ${camara.zona}`;
                                    camaraPreview.classList.add('visible');
                                    
                                    listaSugerencias.style.display = 'none';
                                });
                                
                                listaSugerencias.appendChild(li);
                            });
                        } else {
                            listaSugerencias.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error en búsqueda:', error);
                    });
            }, 300); // Espera 300ms antes de buscar
        });

        // Ocultar lista si se hace clic fuera
        document.addEventListener('click', function(e) {
            if (e.target !== inputBuscador && !listaSugerencias.contains(e.target)) {
                listaSugerencias.style.display = 'none';
            }
        });
    });
    // ==========================================

    // Validar que fecha_limite sea mayor o igual a fecha_programada
    document.getElementById('fecha_programada').addEventListener('change', function() {
        const fechaLimite = document.getElementById('fecha_limite');
        if (fechaLimite.value && fechaLimite.value < this.value) {
            fechaLimite.value = this.value;
        }
        fechaLimite.min = this.value;
    });

    // Mostrar nombre del archivo seleccionado
    document.getElementById('documentacion').addEventListener('change', function() {
        const fileInfo = document.getElementById('fileInfo');
        if (this.files && this.files[0]) {
            fileInfo.textContent = 'Archivo seleccionado: ' + this.files[0].name + ' (' + (this.files[0].size / 1024 / 1024).toFixed(2) + ' MB)';
        } else {
            fileInfo.textContent = '';
        }
    });

    // Validar formulario antes de enviar
    document.getElementById('programarForm').addEventListener('submit', function(e) {
        const fechaProgramada = new Date(document.getElementById('fecha_programada').value);
        const fechaLimite = new Date(document.getElementById('fecha_limite').value);
        
        if (fechaLimite < fechaProgramada) {
            e.preventDefault();
            alert('La fecha límite debe ser mayor o igual a la fecha programada');
            return false;
        }

        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Programando...';
        btn.disabled = true;
    });
    </script>
</body>
</html>