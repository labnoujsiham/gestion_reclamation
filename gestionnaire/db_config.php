<?php
/**
 * Database Configuration - ReclaNova
 * This file handles the database connection
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_reclamations');
define('DB_USER', 'root');          // Change to your MySQL username
define('DB_PASS', '');              // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection
 * @return PDO|null
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // In production, log this error instead of displaying
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
?>