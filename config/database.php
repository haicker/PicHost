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
        webdav_url VARCHAR(500),
        local_path VARCHAR(500),
        upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        storage_type ENUM('local', 'github', 'webdav') DEFAULT 'local'
    )";
    
    try {
        $db->exec($sql);
        
        // 迁移：为已存在的表补充缺失的列或修改ENUM
        $checkTable = $db->query("SHOW TABLES LIKE 'images'")->fetch();
        if ($checkTable) {
            $columns = $db->query("SHOW COLUMNS FROM images")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('tags', $columns)) {
                $db->exec("ALTER TABLE images ADD COLUMN tags TEXT AFTER original_name");
            }
            if (!in_array('webdav_url', $columns)) {
                $db->exec("ALTER TABLE images ADD COLUMN webdav_url VARCHAR(500) AFTER github_url");
            }
            // 更新storage_type的ENUM值以包含'webdav'
            $typeCol = $db->query("SHOW COLUMNS FROM images LIKE 'storage_type'")->fetch();
            if ($typeCol && strpos($typeCol['Type'], 'webdav') === false) {
                $db->exec("ALTER TABLE images MODIFY COLUMN storage_type ENUM('local', 'github', 'webdav') DEFAULT 'local'");
            }
        }
    } catch (PDOException $e) {
        die("数据库初始化失败: " . $e->getMessage());
    }
}

initDatabase();
?>