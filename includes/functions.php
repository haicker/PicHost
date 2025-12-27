<?php
require_once __DIR__ . '/../config/database.php';

function getImages() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM images ORDER BY upload_time DESC");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 为每张图片生成正确的URL
    foreach ($images as &$image) {
        if ($image['storage_type'] === 'github' && !empty($image['github_url'])) {
            $image['url'] = $image['github_url'];
        } else {
            // 本地图片，确保使用完整URL
            $localPath = $image['local_path'];
            // 检查是否已经是完整URL
            if (filter_var($localPath, FILTER_VALIDATE_URL)) {
                $image['url'] = $localPath;
            } else {
                // 确保路径格式正确，避免重复斜杠
                $localPath = ltrim($localPath, '/');
                $image['url'] = rtrim(getConfig('base_url'), '/') . '/' . $localPath;
            }
        }
    }
    
    return $images;
}

function saveImageToDB($imageData) {
    $db = getDBConnection();
    $sql = "INSERT INTO images (filename, original_name, tags, file_size, mime_type, github_url, local_path, storage_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        $imageData['filename'],
        $imageData['original_name'],
        $imageData['tags'],
        $imageData['file_size'],
        $imageData['mime_type'],
        $imageData['github_url'],
        $imageData['local_path'],
        $imageData['storage_type']
    ]);
}

function uploadToGitHub($filePath, $filename) {
    $repoOwner = getConfig('github_repo_owner');
    $repoName = getConfig('github_repo_name');
    $repoPath = getConfig('github_repo_path');
    $token = getConfig('github_token');

    // 验证必要参数
    if (empty($repoOwner) || empty($repoName) || empty($token)) {
        error_log("GitHub upload failed: Missing required configuration");
        return false;
    }

    $apiUrl = "https://api.github.com/repos/" . $repoOwner . "/" . $repoName . "/contents/" . $repoPath . "/" . $filename;

    if (!file_exists($filePath)) {
        error_log("GitHub upload failed: File not found - " . $filePath);
        return false;
    }

    $fileContent = base64_encode(file_get_contents($filePath));

    // 首先检查文件是否已存在（获取SHA）
    $existingSha = null;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'User-Agent: PHP-Image-Hosting'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        // 文件已存在，获取SHA
        $existingData = json_decode($response, true);
        if (isset($existingData['sha'])) {
            $existingSha = $existingData['sha'];
            error_log("File exists, SHA: " . $existingSha);
        }
    } elseif ($httpCode !== 404) {
        // 其他错误
        error_log("GitHub API check failed - HTTP Code: " . $httpCode . ", Response: " . $response);
        return false;
    }

    // 准备上传数据
    $data = [
        'message' => 'Upload image: ' . $filename,
        'content' => $fileContent
    ];

    if ($existingSha) {
        $data['sha'] = $existingSha;
    }

    // 使用PUT方法上传/更新文件
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'User-Agent: PHP-Image-Hosting',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // 记录详细日志用于调试
    error_log("GitHub API Upload Response - HTTP Code: " . $httpCode . ", Response: " . $response);
    if ($error) {
        error_log("cURL Error: " . $error);
    }

    if ($httpCode === 201 || $httpCode === 200) {
        $responseData = json_decode($response, true);
        if (isset($responseData['content']['download_url'])) {
            return $responseData['content']['download_url'];
        } elseif (isset($responseData['content']['html_url'])) {
            // 备用URL
            return str_replace('github.com', 'raw.githubusercontent.com', $responseData['content']['html_url']);
        }
    }

    return false;
}

// 辅助函数：更新已存在的文件
function updateGitHubFile($filePath, $filename, $repoOwner, $repoName, $repoPath, $token) {
    $apiUrl = "https://api.github.com/repos/" . $repoOwner . "/" . $repoName . "/contents/" . $repoPath . "/" . $filename;

    // 首先获取文件的SHA
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'User-Agent: PHP-Image-Hosting'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return false;
    }

    $existingFile = json_decode($response, true);
    if (!isset($existingFile['sha'])) {
        return false;
    }

    // 更新文件
    $fileContent = base64_encode(file_get_contents($filePath));
    $data = [
        'message' => 'Update image: ' . $filename,
        'content' => $fileContent,
        'sha' => $existingFile['sha']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'User-Agent: PHP-Image-Hosting',
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        return $responseData['content']['download_url'] ?? false;
    }

    return false;
}

function validateImage($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败');
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('文件大小超过限制');
    }
    
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    $allowedMimes = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('不支持的文件类型');
    }
    
    return $mimeType;
}

function generateFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

function deleteImage($id) {
    $db = getDBConnection();
    
    $stmt = $db->prepare("SELECT * FROM images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        return false;
    }
    
    if ($image['storage_type'] === 'local' && file_exists($image['local_path'])) {
        unlink($image['local_path']);
    }
    
    $stmt = $db->prepare("DELETE FROM images WHERE id = ?");
    return $stmt->execute([$id]);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function adminLogin($username, $password) {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

function adminLogout() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    session_destroy();
}

// 获取系统设置
function getSettings() {
    $settingsFile = 'config/settings.json';
    $defaultSettings = [
        'github_token' => '',
        'github_repo_owner' => '',
        'github_repo_name' => '',
        'github_repo_path' => 'images',
        'base_url' => ''
    ];
    
    if (file_exists($settingsFile)) {
        $savedSettings = json_decode(file_get_contents($settingsFile), true);
        if ($savedSettings) {
            return array_merge($defaultSettings, $savedSettings);
        }
    }
    
    return $defaultSettings;
}

// 获取配置值（只使用动态设置）
function getConfig($key) {
    $settings = getSettings();

    // 只从动态设置中获取值
    if (isset($settings[$key])) {
        return $settings[$key];
    }

    // 对于非GitHub配置，可以使用常量作为后备
    $nonGithubConstants = [
        'base_url' => 'BASE_URL'
    ];

    if (isset($nonGithubConstants[$key]) && defined($nonGithubConstants[$key])) {
        return constant($nonGithubConstants[$key]);
    }

    // 默认值
    if ($key === 'default_storage') {
        return 'local';
    }

    return '';
}

// 检查GitHub配置是否完整（只使用动态配置）
function isGitHubConfigured() {
    $token = getConfig('github_token');
    $owner = getConfig('github_repo_owner');
    $repo = getConfig('github_repo_name');

    return !empty($token) && !empty($owner) && !empty($repo);
}
?>