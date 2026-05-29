<?php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'sigevem_db2');
define('DB_USER', 'postgres');
define('DB_PASS', 'MI PASSWORD');

// Zona horaria de México
date_default_timezone_set('America/Mexico_City');

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Sincronizar zona horaria en PostgreSQL
    $pdo->exec("SET timezone = 'America/Mexico_City'");

} catch (PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    die("Error de conexión a la base de datos");
}
?>