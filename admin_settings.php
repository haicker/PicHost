<?php
// 安装检测 - 如果未安装，跳转到安装页面
if (!file_exists('.installed')) {
    header('Location: install.php');
    exit;
}

require_once 'config/config.php';
require_once 'includes/functions.php';

if (!isAdminLoggedIn()) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$settingsFile = 'config/settings.json';

// 加载设置
$settings = [
    'github_token' => '',
    'github_repo_owner' => '',
    'github_repo_name' => '',
    'github_repo_path' => '',
    'base_url' => '',
    'require_login' => false,
    'default_storage' => 'local' // 默认存储类型：local 或 github
];

if (file_exists($settingsFile)) {
    $savedSettings = json_decode(file_get_contents($settingsFile), true);
    if ($savedSettings) {
        $settings = array_merge($settings, $savedSettings);
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = [
        'github_token' => trim($_POST['github_token'] ?? ''),
        'github_repo_owner' => trim($_POST['github_repo_owner'] ?? ''),
        'github_repo_name' => trim($_POST['github_repo_name'] ?? ''),
        'github_repo_path' => trim($_POST['github_repo_path'] ?? ''),
        'base_url' => trim($_POST['base_url'] ?? ''),
        'require_login' => isset($_POST['require_login']) && $_POST['require_login'] === '1',
        'default_storage' => trim($_POST['default_storage'] ?? 'local')
    ];

    // 验证基础URL格式
    if (!empty($newSettings['base_url']) && !filter_var($newSettings['base_url'], FILTER_VALIDATE_URL)) {
        $message = '错误：基础URL格式不正确';
    } else {
        // 如果选择了GitHub存储，但GitHub配置不完整，给出警告
        if ($newSettings['default_storage'] === 'github') {
            if (empty($newSettings['github_token']) || empty($newSettings['github_repo_owner']) || empty($newSettings['github_repo_name'])) {
                $message = '警告：选择了GitHub存储，但GitHub配置不完整。系统将使用本地存储。';
            }
        }

        // 确保配置目录存在
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }

        // 保存设置到文件
        if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT))) {
            if (empty($message)) {
                $message = '设置保存成功！';
            }
            $settings = $newSettings;
        } else {
            $message = '错误：设置保存失败';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - PicHost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .settings-card {
            border-left: 4px solid #0d6efd;
        }
        .github-section {
            border-left: 4px solid #28a745;
        }
        .domain-section {
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="bi bi-images"></i> 图床管理后台
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    欢迎, <?php echo $_SESSION['admin_username']; ?>
                </span>
                <a class="nav-link" href="admin.php">图片管理</a>
                <a class="nav-link active" href="admin_settings.php">系统设置</a>
                <a class="nav-link" href="index.php">返回前台</a>
                <a class="nav-link" href="admin.php?action=logout">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo strpos($message, '错误') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5><i class="bi bi-gear"></i> 系统设置</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <!-- GitHub配置区域 -->
                            <div class="card github-section mb-4">
                                <div class="card-header">
                                    <h6><i class="bi bi-github"></i> GitHub配置</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="github_token" class="form-label">GitHub Token</label>
                                        <input type="password" class="form-control" id="github_token" name="github_token" 
                                               value="<?php echo htmlspecialchars($settings['github_token']); ?>" 
                                               placeholder="输入GitHub Personal Access Token">
                                        <div class="form-text">
                                            需要在GitHub生成Personal Access Token，并授予repo权限
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="github_repo_owner" class="form-label">仓库所有者</label>
                                                <input type="text" class="form-control" id="github_repo_owner" name="github_repo_owner" 
                                                       value="<?php echo htmlspecialchars($settings['github_repo_owner']); ?>" 
                                                       placeholder="用户名或组织名">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="github_repo_name" class="form-label">仓库名称</label>
                                                <input type="text" class="form-control" id="github_repo_name" name="github_repo_name" 
                                                       value="<?php echo htmlspecialchars($settings['github_repo_name']); ?>" 
                                                       placeholder="仓库名称">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="github_repo_path" class="form-label">存储路径</label>
                                                <input type="text" class="form-control" id="github_repo_path" name="github_repo_path" 
                                                       value="<?php echo htmlspecialchars($settings['github_repo_path']); ?>" 
                                                       placeholder="images">
                                                <div class="form-text">
                                                    图片在仓库中的存储目录
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 域名配置区域 -->
                            <div class="card domain-section mb-4">
                                <div class="card-header">
                                    <h6><i class="bi bi-globe"></i> 域名配置</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="base_url" class="form-label">基础URL</label>
                                        <input type="url" class="form-control" id="base_url" name="base_url" 
                                               value="<?php echo htmlspecialchars($settings['base_url']); ?>" 
                                               placeholder="https://example.com/img" required>
                                        <div class="form-text">
                                            用于生成图片的完整访问链接，必须包含协议（http://或https://）
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 存储配置区域 -->
                            <div class="card mb-4" style="border-left: 4px solid #6f42c1;">
                                <div class="card-header">
                                    <h6><i class="bi bi-hdd-stack"></i> 存储配置</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">默认存储类型</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="default_storage" id="storage_local" value="local"
                                                   <?php echo ($settings['default_storage'] ?? 'local') === 'local' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="storage_local">
                                                <strong>本地存储</strong> - 图片保存在服务器本地
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="default_storage" id="storage_github" value="github"
                                                   <?php echo ($settings['default_storage'] ?? 'local') === 'github' ? 'checked' : ''; ?>
                                                   <?php echo empty($settings['github_token']) || empty($settings['github_repo_owner']) || empty($settings['github_repo_name']) ? 'disabled' : ''; ?>>
                                            <label class="form-check-label" for="storage_github">
                                                <strong>GitHub存储</strong> - 图片上传到GitHub仓库
                                                <?php if (empty($settings['github_token']) || empty($settings['github_repo_owner']) || empty($settings['github_repo_name'])): ?>
                                                    <span class="badge bg-warning text-dark ms-2">需先配置GitHub</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            选择图片的默认存储位置。如果选择GitHub存储但配置不完整，系统将自动使用本地存储。
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 上传权限配置区域 -->
                            <div class="card mb-4" style="border-left: 4px solid #dc3545;">
                                <div class="card-header">
                                    <h6><i class="bi bi-shield-lock"></i> 上传权限配置</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="require_login" name="require_login" value="1"
                                                   <?php echo $settings['require_login'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="require_login">
                                                <strong>拒绝游客上传</strong>
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            启用后，未登录用户将无法上传图片，必须先登录管理员账户
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="admin.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> 返回管理后台
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> 保存设置
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 当前配置信息 -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6><i class="bi bi-info-circle"></i> 当前配置信息</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>GitHub配置状态</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Token配置:</strong> <?php echo empty($settings['github_token']) ? '<span class="text-danger">未配置</span>' : '<span class="text-success">已配置</span>'; ?></li>
                                    <li><strong>仓库信息:</strong> <?php echo empty($settings['github_repo_owner']) || empty($settings['github_repo_name']) ? '<span class="text-danger">未配置</span>' : '<span class="text-success">' . $settings['github_repo_owner'] . '/' . $settings['github_repo_name'] . '</span>'; ?></li>
                                    <li><strong>存储路径:</strong> <?php echo empty($settings['github_repo_path']) ? 'images' : $settings['github_repo_path']; ?></li>
                                </ul>

                                <h6 class="mt-3">存储配置状态</h6>
                                <ul class="list-unstyled">
                                    <li><strong>默认存储:</strong>
                                        <?php
                                        $defaultStorage = $settings['default_storage'] ?? 'local';
                                        if ($defaultStorage === 'github' && (!empty($settings['github_token']) && !empty($settings['github_repo_owner']) && !empty($settings['github_repo_name']))) {
                                            echo '<span class="text-success">GitHub存储</span>';
                                        } elseif ($defaultStorage === 'github') {
                                            echo '<span class="text-warning">GitHub存储（配置不完整，实际使用本地存储）</span>';
                                        } else {
                                            echo '<span class="text-primary">本地存储</span>';
                                        }
                                        ?>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>域名配置状态</h6>
                                <ul class="list-unstyled">
                                    <li><strong>基础URL:</strong> <?php echo empty($settings['base_url']) ? '<span class="text-danger">未配置</span>' : '<span class="text-success">' . $settings['base_url'] . '</span>'; ?></li>
                                    <li><strong>配置文件:</strong> <?php echo file_exists($settingsFile) ? '<span class="text-success">已创建</span>' : '<span class="text-danger">未创建</span>'; ?></li>
                                </ul>
                                <h6 class="mt-3">上传权限状态</h6>
                                <ul class="list-unstyled">
                                    <li><strong>登录要求:</strong> <?php echo $settings['require_login'] ? '<span class="text-danger">已启用</span>' : '<span class="text-success">未启用</span>'; ?></li>
                                    <li><strong>当前状态:</strong> <?php echo $settings['require_login'] ? '需要登录才能上传' : '允许匿名上传'; ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>