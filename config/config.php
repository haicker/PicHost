<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'image_hosting');

define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_DIR', 'uploads/');

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// 网站基础URL，用于生成完整的图片链接
define('BASE_URL', 'http://localhost/img');

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
?>