<?php
// WebDAV 图片代理脚本
// 用于访问 WebDAV 存储的图片时处理认证

require_once 'config/config.php';
require_once 'includes/functions.php';

if (!defined('INSTALLED') && !file_exists('.installed')) {
    http_response_code(503);
    exit('System not installed');
}

$imageId = $_GET['id'] ?? null;

if (!$imageId) {
    http_response_code(400);
    exit('Missing image ID');
}

// 获取图片信息
$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM images WHERE id = ?");
$stmt->execute([$imageId]);
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$image) {
    http_response_code(404);
    exit('Image not found');
}

// 如果是 WebDAV 存储，需要代理访问
if ($image['storage_type'] === 'webdav' && !empty($image['webdav_url'])) {
    $webdavUrl = $image['webdav_url'];
    $webdavUsername = getConfig('webdav_username');
    $webdavPassword = getConfig('webdav_password');
    
    header('Cache-Control: public, max-age=31536000');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    header('Content-Type: ' . $image['mime_type']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webdavUrl);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $webdavUsername . ':' . $webdavPassword);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FILE, fopen('php://output', 'wb'));
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $result !== false) {
        exit;
    } else {
        http_response_code(500);
        exit('Failed to fetch image from WebDAV');
    }
} else {
    // 非 WebDAV 存储，直接重定向到原始 URL
    $baseUrl = rtrim(getConfig('base_url'), '/');
    if ($image['storage_type'] === 'github' && !empty($image['github_url'])) {
        header('Location: ' . $image['github_url']);
    } else {
        $localPath = ltrim($image['local_path'], '/');
        header('Location: ' . $baseUrl . '/' . $localPath);
    }
    exit;
}
?>
