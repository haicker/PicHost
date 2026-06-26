<?php
require_once __DIR__ . '/../config/database.php';

function getImages($limit = null, $offset = 0) {
    $db = getDBConnection();

    $limitClause = $limit !== null ? " LIMIT " . (int)$limit . " OFFSET " . (int)$offset : "";

    $stmt = $db->query("SELECT id, filename, original_name, tags, file_size, mime_type, github_url, webdav_url, local_path, upload_time, storage_type FROM images ORDER BY upload_time DESC" . $limitClause);
    
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $baseUrl = rtrim(getConfig('base_url'), '/');
    
    foreach ($images as &$image) {
        if ($image['storage_type'] === 'github' && !empty($image['github_url'])) {
            $image['url'] = $image['github_url'];
        } elseif ($image['storage_type'] === 'webdav' && !empty($image['webdav_url'])) {
            $image['url'] = $baseUrl . '/proxy.php?id=' . $image['id'];
        } else {
            $localPath = ltrim($image['local_path'], '/');
            $image['url'] = $baseUrl . '/' . $localPath;
        }
    }
    
    return $images;
}

function getImageCount() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT COUNT(*) as count FROM images");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function saveImageToDB($imageData) {
    $db = getDBConnection();
    $sql = "INSERT INTO images (filename, original_name, tags, file_size, mime_type, github_url, webdav_url, local_path, storage_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        $imageData['filename'],
        $imageData['original_name'],
        $imageData['tags'],
        $imageData['file_size'],
        $imageData['mime_type'],
        $imageData['github_url'],
        $imageData['webdav_url'],
        $imageData['local_path'],
        $imageData['storage_type']
    ]);
}

function uploadToWebDAV($filePath, $filename) {
    $webdavUrl = getConfig('webdav_url');
    $webdavUsername = getConfig('webdav_username');
    $webdavPassword = getConfig('webdav_password');
    $webdavPath = getConfig('webdav_path');

    if (empty($webdavUrl) || empty($webdavUsername) || empty($webdavPassword)) {
        error_log("WebDAV upload failed: Missing required configuration");
        return false;
    }

    if (!file_exists($filePath)) {
        error_log("WebDAV upload failed: File not found - " . $filePath);
        return false;
    }

    $remotePath = $webdavPath;
    if (substr($remotePath, -1) !== '/') {
        $remotePath .= '/';
    }
    $remotePath .= $filename;

    $fullUrl = rtrim($webdavUrl, '/') . '/' . ltrim($remotePath, '/');

    $fp = fopen($filePath, 'rb');
    if (!$fp) {
        error_log("WebDAV upload failed: Could not open file - " . $filePath);
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath));
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $webdavUsername . ':' . $webdavPassword);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream',
        'User-Agent: PHP-Image-Hosting'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    fclose($fp);
    curl_close($ch);

    error_log("WebDAV Upload Response - HTTP Code: " . $httpCode . ", URL: " . $fullUrl);
    if ($error) {
        error_log("cURL Error: " . $error);
    }

    if ($httpCode === 201 || $httpCode === 200 || $httpCode === 204) {
        return $fullUrl;
    }

    return false;
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
            // 将 html_url 转换为 raw URL
            // https://github.com/owner/repo/blob/branch/path -> https://raw.githubusercontent.com/owner/repo/branch/path
            $htmlUrl = $responseData['content']['html_url'];
            $rawUrl = str_replace('github.com', 'raw.githubusercontent.com', $htmlUrl);
            $rawUrl = str_replace('/blob/', '/', $rawUrl);
            return $rawUrl;
        }
    }
    
    // 记录上传失败的详细信息
    error_log("GitHub upload failed - HTTP Code: " . $httpCode . ", Response: " . $response);
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
    
    // 精确验证：用 finfo 确认 MIME
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
    
    $stmt = $db->prepare("SELECT id, local_path, storage_type FROM images WHERE id = ?");
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
    if ($username === ADMIN_USERNAME) {
        $stored = ADMIN_PASSWORD;
        if (preg_match('/^\$2[ayb]\$/', $stored)) {
            $match = password_verify($password, $stored);
        } else {
            $match = $password === $stored;
        }
        if ($match) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            return true;
        }
    }
    return false;
}

function adminLogout() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    session_destroy();
}

// 加密设置项
function encryptSetting($value) {
    if ($value === '' || $value === null) return '';
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', CONFIG_KEY, 0, $iv);
    return '__ENC__' . base64_encode($iv . '::' . $encrypted);
}

// 解密设置项
function decryptSetting($value) {
    if ($value === '' || $value === null) return '';
    if (strpos($value, '__ENC__') !== 0) return $value;
    $data = base64_decode(substr($value, 7));
    if ($data === false) return '';
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return '';
    $iv = $parts[0];
    $encrypted = $parts[1];
    return openssl_decrypt($encrypted, 'aes-256-cbc', CONFIG_KEY, 0, $iv);
}

// 需要加/解密的敏感字段列表
function getSensitiveFields() {
    return ['github_token', 'webdav_username', 'webdav_password'];
}


// 获取系统设置（带静态缓存，单次请求内只读一次文件）
function getSettings() {
    static $settings = null;
    if ($settings !== null) return $settings;

    $settingsFile = 'config/settings.json';
    $defaultSettings = [
        'github_token' => '',
        'github_repo_owner' => '',
        'github_repo_name' => '',
        'github_repo_path' => 'images',
        'webdav_url' => '',
        'webdav_username' => '',
        'webdav_password' => '',
        'webdav_path' => 'images',
        'base_url' => '',
        'default_storage' => 'local',
        'require_login' => false,
        'bg_image' => ''
    ];

    if (file_exists($settingsFile)) {
        $savedSettings = json_decode(file_get_contents($settingsFile), true);
        if ($savedSettings) {
            foreach (getSensitiveFields() as $field) {
                if (isset($savedSettings[$field])) {
                    $savedSettings[$field] = decryptSetting($savedSettings[$field]);
                }
            }
            $settings = array_merge($defaultSettings, $savedSettings);
            return $settings;
        }
    }

    $settings = $defaultSettings;
    return $settings;
}

// 获取配置值（优先使用动态设置，否则使用常量）
function getConfig($key) {
    $settings = getSettings();

    // 优先从动态设置中获取值（非空值）
    if (isset($settings[$key]) && $settings[$key] !== '') {
        return $settings[$key];
    }

    // 对于非 GitHub 配置，可以使用常量作为后备
    $nonGithubConstants = [
        'base_url' => 'BASE_URL'
    ];

    if (isset($nonGithubConstants[$key]) && defined($nonGithubConstants[$key]) && constant($nonGithubConstants[$key]) !== '') {
        return constant($nonGithubConstants[$key]);
    }

    // 最后兜底：自动检测 base_url
    if ($key === 'base_url') {
        $protocol = isHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        return $protocol . '://' . $host . $scriptDir;
    }

    // 默认值
    if ($key === 'default_storage') {
        return 'local';
    }

    return '';
}

// 检查 GitHub 配置是否完整（只使用动态配置）
function isGitHubConfigured() {
    $token = getConfig('github_token');
    $owner = getConfig('github_repo_owner');
    $repo = getConfig('github_repo_name');

    return !empty($token) && !empty($owner) && !empty($repo);
}

// 检查 WebDAV 配置是否完整
function isWebDAVConfigured() {
    $url = getConfig('webdav_url');
    $username = getConfig('webdav_username');
    $password = getConfig('webdav_password');

    return !empty($url) && !empty($username) && !empty($password);
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}


function isHttps() {
    // 标准 HTTPS 检测
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    // 端口检测
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) return true;
    // 反向代理检测
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return true;
    // Cloudflare 检测
    if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
        $cfVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
        if (isset($cfVisitor['scheme']) && $cfVisitor['scheme'] === 'https') return true;
    }
    return false;
}
?>