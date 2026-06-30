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
        telegram_url VARCHAR(500),
        local_path VARCHAR(500),
        upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        storage_type ENUM('local', 'github', 'webdav', 'telegram') DEFAULT 'local'
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
            if (!in_array('telegram_url', $columns)) {
                $db->exec("ALTER TABLE images ADD COLUMN telegram_url VARCHAR(500) AFTER webdav_url");
            }
            $typeCol = $db->query("SHOW COLUMNS FROM images LIKE 'storage_type'")->fetch();
            if ($typeCol && strpos($typeCol['Type'], 'telegram') === false) {
                $db->exec("ALTER TABLE images MODIFY COLUMN storage_type ENUM('local', 'github', 'webdav', 'telegram') DEFAULT 'local'");
            }
        }

        // 添加索引（忽略已存在的错误）
        $indexes = [
            "ALTER TABLE images ADD INDEX idx_upload_time (upload_time DESC)",
            "ALTER TABLE images ADD INDEX idx_storage_type (storage_type)",
            "ALTER TABLE images ADD INDEX idx_filename (filename)"
        ];
        foreach ($indexes as $idxSql) {
            try { $db->exec($idxSql); } catch (PDOException $e) {}
        }
    } catch (PDOException $e) {
        die("数据库初始化失败: " . $e->getMessage());
    }
}

// 仅首次安装或升级时执行 DDL，普通请求跳过
if (!defined('INSTALLED') && !file_exists('.installed')) {
    initDatabase();
}
?>