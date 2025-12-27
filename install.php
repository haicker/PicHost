<?php
// 检查是否已安装 - 如果存在安装锁定文件，跳转到首页
if (file_exists('.installed')) {
    // 检查是否正在访问安装页面
    if (basename($_SERVER['PHP_SELF']) === 'install.php') {
        header('Location: index.php');
        exit;
    }
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            $error = validateSystemRequirements();
            if (!$error) {
                // 成功检查后，重定向到第二步
                header('Location: install.php?step=2');
                exit;
            }
            break;
            
        case 2:
            $error = validateDatabaseConfig($_POST);
            if (!$error) {
                if (saveDatabaseConfig($_POST)) {
                    // 成功保存后，重定向到第三步
                    header('Location: install.php?step=3');
                    exit;
                } else {
                    $error = '数据库配置保存失败';
                }
            }
            break;
            
        case 3:
            $error = validateAdminConfig($_POST);
            if (!$error) {
                if (saveAdminConfig($_POST)) {
                    // 成功保存后，重定向到第四步
                    header('Location: install.php?step=4');
                    exit;
                } else {
                    $error = '管理员配置保存失败';
                }
            }
            break;
            
        case 4:
            if (initializeDatabase()) {
                // 成功初始化后，重定向到第五步
                header('Location: install.php?step=5');
                exit;
            } else {
                $error = '数据库初始化失败';
            }
            break;
    }
}

function validateSystemRequirements() {
    $errors = [];
    
    // 检查PHP版本
    if (version_compare(PHP_VERSION, '7.4.0') < 0) {
        $errors[] = 'PHP版本需要7.4.0或更高，当前版本：' . PHP_VERSION;
    }
    
    // 检查扩展
    $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'fileinfo', 'json', 'mbstring'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = '缺少必要的PHP扩展：' . $ext;
        }
    }
    
    // 检查目录权限
    $writableDirs = ['.', 'config', 'uploads'];
    foreach ($writableDirs as $dir) {
        if (!is_writable($dir)) {
            $errors[] = '目录不可写：' . $dir;
        }
    }
    
    return empty($errors) ? '' : implode('<br>', $errors);
}

function validateDatabaseConfig($data) {
    $required = ['db_host', 'db_user', 'db_name'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return '请填写所有必填字段';
        }
    }
    
    // 测试数据库连接
    try {
        $dsn = "mysql:host={$data['db_host']};dbname={$data['db_name']}";
        $pdo = new PDO($dsn, $data['db_user'], $data['db_pass'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        return '数据库连接失败：' . $e->getMessage();
    }
    
    return '';
}

function saveDatabaseConfig($data) {
    $configContent = "<?php\n\n";
    $configContent .= "// 数据库配置\n";
    $configContent .= "define('DB_HOST', '" . addslashes($data['db_host']) . "');\n";
    $configContent .= "define('DB_USER', '" . addslashes($data['db_user']) . "');\n";
    $configContent .= "define('DB_PASS', '" . addslashes($data['db_pass'] ?? '') . "');\n";
    $configContent .= "define('DB_NAME', '" . addslashes($data['db_name']) . "');\n\n";
    
    $configContent .= "// 系统配置\n";
    $configContent .= "define('MAX_FILE_SIZE', 5 * 1024 * 1024);\n";
    $configContent .= "define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);\n";
    $configContent .= "define('UPLOAD_DIR', 'uploads/');\n";
    $configContent .= "define('BASE_URL', '" . addslashes($data['base_url'] ?? 'http://localhost/img') . "');\n\n";
    
    $configContent .= "// 管理员配置（将在下一步设置）\n";
    $configContent .= "define('ADMIN_USERNAME', '');\n";
    $configContent .= "define('ADMIN_PASSWORD', '');\n\n";
    
    $configContent .= "error_reporting(E_ALL);\n";
    $configContent .= "ini_set('display_errors', 1);\n\n";
    $configContent .= "session_start();\n";
    $configContent .= "?>";
    
    return file_put_contents('config/config.php', $configContent) !== false;
}

function validateAdminConfig($data) {
    if (empty($data['admin_username']) || empty($data['admin_password'])) {
        return '请填写管理员用户名和密码';
    }
    
    if (strlen($data['admin_password']) < 6) {
        return '密码长度至少6位';
    }
    
    if (empty($data['base_url'])) {
        return '请填写基础URL';
    }
    
    // 验证URL格式
    if (!filter_var($data['base_url'], FILTER_VALIDATE_URL)) {
        return '基础URL格式不正确，请包含http://或https://';
    }
    
    return '';
}

function saveAdminConfig($data) {
    if (!file_exists('config/config.php')) {
        return false;
    }
    
    $configContent = file_get_contents('config/config.php');
    $configContent = preg_replace(
        "/define\('ADMIN_USERNAME', ''\);/",
        "define('ADMIN_USERNAME', '" . addslashes($data['admin_username']) . "');",
        $configContent
    );
    $configContent = preg_replace(
        "/define\('ADMIN_PASSWORD', ''\);/",
        "define('ADMIN_PASSWORD', '" . addslashes($data['admin_password']) . "');",
        $configContent
    );
    
    return file_put_contents('config/config.php', $configContent) !== false;
}

function initializeDatabase() {
    if (!file_exists('config/config.php')) {
        return false;
    }
    
    require_once 'config/config.php';
    
    // 创建数据库连接并初始化表
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建images表
        $sql = "CREATE TABLE IF NOT EXISTS images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            description TEXT,
            file_size INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            github_url VARCHAR(500),
            local_path VARCHAR(500),
            upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            storage_type ENUM('local', 'github') DEFAULT 'local'
        )";
        
        $pdo->exec($sql);
        
        // 创建uploads目录
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        // 创建安装锁定文件
        file_put_contents('.installed', date('Y-m-d H:i:s') . " - 安装完成\n");
        
        return true;
        
    } catch (PDOException $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PicHost - 安装向导</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
            --glass-bg: rgba(255, 255, 255, 0.92);
            --glass-border: rgba(255, 255, 255, 0.3);
            --card-shadow: 0 20px 40px rgba(0,0,0,0.12);
            --input-focus: #667eea;
            --success-color: #28a745;
            --danger-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            line-height: 1.6;
            padding: 20px;
        }

        .install-container {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            max-width: 800px;
            width: 100%;
            margin: 2rem auto;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            animation: fadeInUp 0.6s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
        }

        .install-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            z-index: 10;
        }

        .install-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .install-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
            opacity: 0.7;
        }

        .install-header i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            display: block;
            text-shadow: 0 4px 12px rgba(0,0,0,0.25);
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .install-header h1 {
            font-weight: 800;
            margin-bottom: 0.5rem;
            font-size: 2.2rem;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .install-header p {
            opacity: 0.9;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem 0;
            position: relative;
            padding: 0 2rem;
        }

        .step {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 16px;
            font-weight: 700;
            font-size: 1.1rem;
            border: 3px solid white;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            position: relative;
            z-index: 2;
            cursor: default;
            font-family: 'Inter', sans-serif;
        }

        .step::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border-radius: 50%;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .step.active {
            background: var(--primary-gradient);
            color: white;
            transform: scale(1.12);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.45);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .step.completed {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.35);
            transform: scale(1.05);
        }

        .step.completed::after {
            content: '✓';
            position: absolute;
            top: -4px;
            right: -4px;
            width: 22px;
            height: 22px;
            background: var(--success-color);
            border-radius: 50%;
            color: white;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            font-weight: bold;
        }

        .step-line {
            flex: 1;
            height: 4px;
            background: linear-gradient(90deg, #e9ecef 0%, #dee2e6 100%);
            margin: 0 12px;
            border-radius: 2px;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.08);
        }

        .step-line::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--success-gradient);
            transition: left 0.6s cubic-bezier(0.23, 1, 0.32, 1);
            border-radius: 2px;
        }

        .step-line.completed::before {
            left: 0;
        }

        .card-body {
            padding: 2.5rem;
        }

        h3 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.25rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        h3 i {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 8px;
            border-radius: 12px;
            font-size: 1.2rem;
        }

        .requirement-check {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            padding: 1rem;
            background: rgba(248, 249, 250, 0.7);
            border-radius: 14px;
            transition: all 0.3s ease;
            border: 1px solid rgba(233, 236, 239, 0.8);
        }

        .requirement-check:hover {
            background: rgba(233, 236, 239, 0.8);
            transform: translateX(4px);
            border-color: rgba(206, 212, 218, 0.8);
        }

        .requirement-check i {
            margin-right: 1rem;
            font-size: 1.3rem;
            min-width: 28px;
            text-align: center;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            font-family: 'Inter', sans-serif;
            height: 52px;
        }

        .form-control:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
            background: white;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            display: block;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: #667eea;
            font-size: 1.1rem;
        }

        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.35rem;
            line-height: 1.5;
        }

        .btn {
            border-radius: 14px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            border: none;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
            font-family: 'Inter', sans-serif;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary-gradient);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 14px 28px rgba(102, 126, 234, 0.45);
        }

        .btn-success {
            background: var(--success-gradient);
            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.35);
        }

        .btn-success:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 14px 28px rgba(79, 172, 254, 0.45);
        }

        .btn-lg {
            padding: 1.25rem 3rem;
            font-size: 1.1rem;
            height: 56px;
        }

        .d-grid .btn {
            width: 100%;
        }

        .alert {
            border-radius: 14px;
            border: none;
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
            backdrop-filter: blur(8px);
            animation: slideInDown 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.08) 0%, rgba(40, 167, 69, 0.03) 100%);
            border-left-color: var(--success-color);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.08) 0%, rgba(220, 53, 69, 0.03) 100%);
            border-left-color: var(--danger-color);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.08) 0%, rgba(23, 162, 184, 0.03) 100%);
            border-left-color: #17a2b8;
            color: #0c5460;
        }

        .card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
        }

        .card-body {
            padding: 2rem;
        }

        .text-muted {
            color: #6c757d !important;
            font-size: 0.95rem;
        }

        h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .progress-container {
            margin: 1.5rem 0;
            padding: 0 2rem;
        }

        .progress-bar {
            height: 6px;
            background: linear-gradient(to right, var(--primary-gradient), var(--success-gradient));
            border-radius: 3px;
            transition: width 0.6s cubic-bezier(0.23, 1, 0.32, 1);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
            }
            50% {
                box-shadow: 0 12px 40px rgba(102, 126, 234, 0.7);
            }
            100% {
                box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(248, 249, 250, 0.7);
            border-radius: 12px;
            font-size: 0.9rem;
        }

        .feature-item i {
            color: #667eea;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .install-container {
                margin: 1rem;
                border-radius: 20px;
                animation: fadeInUp 0.4s ease-out;
            }

            .card-body {
                padding: 1.5rem;
            }

            .step {
                width: 48px;
                height: 48px;
                margin: 0 8px;
                font-size: 1rem;
            }

            .step-line {
                margin: 0 6px;
                height: 3px;
            }

            .btn {
                padding: 0.875rem 2rem;
                font-size: 0.95rem;
                height: 48px;
            }

            .btn-lg {
                padding: 1rem 2.5rem;
                height: 52px;
            }

            .install-header {
                padding: 2rem 1.5rem;
            }

            .install-header i {
                font-size: 2.5rem;
            }

            h3 {
                font-size: 1.3rem;
            }

            .step-indicator {
                padding: 0 1rem;
            }
        }

        @media (max-width: 576px) {
            .step {
                width: 42px;
                height: 42px;
                margin: 0 4px;
                font-size: 0.9rem;
            }

            .step-line {
                margin: 0 2px;
            }

            .card-body {
                padding: 1.25rem;
            }

            .form-control {
                padding: 0.875rem 1rem;
                height: 48px;
            }

            .btn {
                padding: 0.75rem 1.5rem;
                height: 46px;
            }

            .install-header h1 {
                font-size: 1.8rem;
            }

            .install-header p {
                font-size: 1rem;
            }
        }

        /* 添加安装完成页面的特殊样式 */
        .install-complete {
            text-align: center;
            padding: 2rem;
        }

        .install-complete i {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 1.5rem;
            display: block;
        }

        .install-complete h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .install-complete p {
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.7;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .action-buttons .btn {
            flex: 1;
            max-width: 200px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <i class="bi bi-cloud-arrow-up display-4"></i>
            <h1 class="mt-3">PicHost安装向导</h1>
            <p class="mb-0">轻松配置您的图片托管系统</p>
        </div>
        
        <div class="step-indicator">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="step <?php echo $i == $step ? 'active' : ($i < $step ? 'completed' : ''); ?>">
                    <?php echo $i; ?>
                </div>
                <?php if ($i < 5): ?>
                    <div class="step-line <?php echo $i < $step ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success || $step == 5): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $step == 5 ? '系统安装完成！' : $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($step == 1): ?>
                    <h3>步骤 1: 系统环境检查</h3>
                    <p class="text-muted">检查服务器环境是否满足运行要求</p>
                    
                    <div class="mb-4">
                        <h5>PHP版本检查</h5>
                        <div class="requirement-check">
                            <i class="bi bi-<?php echo version_compare(PHP_VERSION, '7.4.0') >= 0 ? 'check-circle text-success' : 'x-circle text-danger'; ?>"></i>
                            <span>PHP版本 >= 7.4.0</span>
                            <span class="ms-auto"><?php echo PHP_VERSION; ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>PHP扩展检查</h5>
                        <?php
                        $extensions = ['pdo', 'pdo_mysql', 'curl', 'fileinfo', 'json', 'mbstring'];
                        foreach ($extensions as $ext):
                            $loaded = extension_loaded($ext);
                        ?>
                            <div class="requirement-check">
                                <i class="bi bi-<?php echo $loaded ? 'check-circle text-success' : 'x-circle text-danger'; ?>"></i>
                                <span><?php echo $ext; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mb-4">
                        <h5>目录权限检查</h5>
                        <?php
                        $dirs = ['.', 'config', 'uploads'];
                        foreach ($dirs as $dir):
                            $writable = is_writable($dir);
                        ?>
                            <div class="requirement-check">
                                <i class="bi bi-<?php echo $writable ? 'check-circle text-success' : 'x-circle text-danger'; ?>"></i>
                                <span><?php echo $dir; ?> 目录</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            继续 <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 2): ?>
                    <h3>步骤 2: 数据库配置</h3>
                    <p class="text-muted">配置MySQL数据库连接信息</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_host" class="form-label">数据库主机</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_name" class="form-label">数据库名</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'image_hosting'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_user" class="form-label">数据库用户</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">数据库密码</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            保存配置 <i class="bi bi-check"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 3): ?>
                    <h3>步骤 3: 管理员配置</h3>
                    <p class="text-muted">设置管理员登录账号和密码</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admin_username" class="form-label">管理员用户名</label>
                                <input type="text" class="form-control" id="admin_username" name="admin_username" value="admin" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admin_password" class="form-label">管理员密码</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                <div class="form-text">密码长度至少6位</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>域名配置</h5>
                        <p class="text-muted small">设置网站的基础URL，用于生成图片的完整链接</p>
                        
                        <div class="mb-3">
                            <label for="base_url" class="form-label">基础URL</label>
                            <input type="url" class="form-control" id="base_url" name="base_url" 
                                   value="<?php echo htmlspecialchars($_POST['base_url'] ?? 'http://localhost/img'); ?>" 
                                   placeholder="https://example.com/img" required>
                            <div class="form-text">
                                必须包含协议（http://或https://），用于生成图片的完整访问链接
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            保存管理员配置 <i class="bi bi-check"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 4): ?>
                    <h3>步骤 4: 安装确认</h3>
                    <p class="text-muted">确认安装信息并完成安装</p>
                    
                    <div class="alert alert-info">
                        <h5>安装信息摘要</h5>
                        <ul class="mb-0">
                            <li>数据库主机: <?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?></li>
                            <li>数据库名: <?php echo htmlspecialchars($_POST['db_name'] ?? 'image_hosting'); ?></li>
                            <li>管理员账号: <?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?></li>
                            <li>基础URL: <?php echo htmlspecialchars($_POST['base_url'] ?? 'http://localhost/img'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            开始安装 <i class="bi bi-gear"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 5): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle display-1 text-success"></i>
                        <h3 class="mt-3">安装完成！</h3>
                        <p class="text-muted">PicHost已成功安装</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <i class="bi bi-images text-primary"></i>
                                        <h5>前台页面</h5>
                                        <a href="index.php" class="btn btn-outline-primary btn-sm">访问前台</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <i class="bi bi-gear text-success"></i>
                                        <h5>管理后台</h5>
                                        <a href="admin.php" class="btn btn-outline-success btn-sm">管理后台</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <i class="bi bi-file-text text-info"></i>
                                        <h5>使用文档</h5>
                                        <a href="README.md" class="btn btn-outline-info btn-sm">查看文档</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>安全提示：</strong> 安装完成后请删除 install.php 文件
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
            
            <?php if ($step > 1 && $step < 5): ?>
                <div class="text-center mt-3">
                    <a href="install.php?step=<?php echo $step - 1; ?>" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> 上一步
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>