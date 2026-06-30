<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$settings = getSettings();

// 检查是否需要登录才能上传
$requireLogin = $settings['require_login'] ?? false;

if ($requireLogin && !isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '需要登录后才能上传图片']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

try {
    $file = $_FILES['image'];
    $tags = $_POST['tags'] ?? '';
    
    $mimeType = validateImage($file);
    $filename = generateFilename($file['name']);
    
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    $localPath = UPLOAD_DIR . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $localPath)) {
        throw new Exception('文件保存失败');
    }
    
    // 生成缩略图
    generateThumbnail($localPath, $filename);
    
    $githubUrl = null;
    $webdavUrl = null;
    $telegramUrl = null;
    $storageType = 'local';

    // 获取默认存储类型
    $defaultStorage = $settings['default_storage'] ?? 'local';

    // 根据配置决定存储方式
    $fallbackMessage = '';
    if ($defaultStorage === 'webdav' && isWebDAVConfigured()) {
        $webdavUrl = uploadToWebDAV($localPath, $filename);
        if ($webdavUrl) {
            $storageType = 'webdav';
        } else {
            error_log("WebDAV upload failed, falling back to local storage");
            $fallbackMessage = 'WebDAV 服务器连接失败，已自动回退到本地存储';
        }
    } elseif ($defaultStorage === 'github' && isGitHubConfigured()) {
        $githubUrl = uploadToGitHub($localPath, $filename);
        if ($githubUrl) {
            $storageType = 'github';
        } else {
            error_log("GitHub upload failed, falling back to local storage");
            $fallbackMessage = 'GitHub 上传失败，已自动回退到本地存储';
        }
    } elseif ($defaultStorage === 'telegram' && isTelegramConfigured()) {
        $telegramUrl = uploadToTelegram($localPath, $filename);
        if ($telegramUrl) {
            $storageType = 'telegram';
        } else {
            error_log("Telegram upload failed, falling back to local storage");
            $fallbackMessage = 'Telegram 上传失败，已自动回退到本地存储';
        }
    }
    
    $imageData = [
        'filename' => $filename,
        'original_name' => $file['name'],
        'tags' => $tags,
        'file_size' => $file['size'],
        'mime_type' => $mimeType,
        'github_url' => $githubUrl,
        'webdav_url' => $webdavUrl,
        'telegram_url' => $telegramUrl,
        'local_path' => $localPath,
        'storage_type' => $storageType
    ];
    
    if (saveImageToDB($imageData)) {
        // 获取刚插入的图片ID
        $db = getDBConnection();
        $imageId = $db->lastInsertId();
        
        // 生成正确的图片访问URL
        if ($webdavUrl) {
            $imageUrl = rtrim(getConfig('base_url'), '/') . '/proxy.php?id=' . $imageId;
        } elseif ($githubUrl) {
            $imageUrl = $githubUrl;
        } elseif ($telegramUrl) {
            $imageUrl = rtrim(getConfig('base_url'), '/') . '/proxy.php?id=' . $imageId;
        } else {
            // 确保本地图片URL格式正确
            $localPath = ltrim($localPath, '/');
            $imageUrl = rtrim(getConfig('base_url'), '/') . '/' . $localPath;
        }
        
        $response = [
            'success' => true,
            'message' => '图片上传成功' . ($fallbackMessage ? ' (' . $fallbackMessage . ')' : ''),
            'url' => $imageUrl,
            'storage_type' => $storageType,
            'tags' => $tags,
            'fallback_message' => $fallbackMessage
        ];
    } else {
        throw new Exception('数据库保存失败');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>