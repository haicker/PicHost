<?php

function getDBConnection() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    
    return $connection;
}

function initDatabase() {
    $db = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        tags TEXT,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        github_url VARCHAR(500),
        local_path VARCHAR(500),
        upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        storage_type ENUM('local', 'github') DEFAULT 'local'
    )";
    
    try {
        $db->exec($sql);
        
        // 检查表是否存在，如果存在则添加tags字段（如果不存在）
        $checkTable = $db->query("SHOW TABLES LIKE 'images'")->fetch();
        if ($checkTable) {
            // 检查tags字段是否存在
            $checkColumn = $db->query("SHOW COLUMNS FROM images LIKE 'tags'")->fetch();
            if (!$checkColumn) {
                // 添加tags字段
                $db->exec("ALTER TABLE images ADD COLUMN tags TEXT AFTER original_name");
            }
        }
    } catch (PDOException $e) {
        die("数据库初始化失败: " . $e->getMessage());
    }
}

initDatabase();
?>