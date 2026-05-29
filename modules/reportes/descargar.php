<?php
session_start();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 2])) {
    header('Location: ../dashboard/index.php');
    exit;
}

// descargar.php redirige a generar.php ya que la descarga es inmediata.
// Si en el futuro se guardan archivos físicos, aquí se servirían.
header('Location: index.php');
exit;
?>