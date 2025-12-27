<?php
// 安装检测 - 如果未安装，跳转到安装页面
if (!file_exists('.installed')) {
    header('Location: install.php');
    exit;
}

require_once 'config/config.php';
require_once 'includes/functions.php';

// 加载设置
$settingsFile = 'config/settings.json';
$settings = [
    'require_login' => false
];

if (file_exists($settingsFile)) {
    $savedSettings = json_decode(file_get_contents($settingsFile), true);
    if ($savedSettings) {
        $settings = array_merge($settings, $savedSettings);
    }
}

// 检查是否需要登录才能上传
$requireLogin = $settings['require_login'] ?? false;
$isLoggedIn = isAdminLoggedIn();

// 如果需要登录但未登录，显示提示信息
$showLoginPrompt = $requireLogin && !$isLoggedIn;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP 图床 - 简单快速的图片托管服务</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="shortcut icon" href="favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">PicHost</a>
            <div class="navbar-nav ms-auto">
                <?php if ($isLoggedIn): ?>
                    <span class="navbar-text me-3">
                        欢迎, <?php echo $_SESSION['admin_username']; ?>
                    </span>
                    <a class="nav-link" href="admin.php">管理后台</a>
                    <a class="nav-link" href="admin.php?action=logout">退出登录</a>
                <?php else: ?>
                    <a class="nav-link" href="admin_login.php">管理员登录</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                
                <?php if ($showLoginPrompt): ?>
                    <!-- 需要登录提示 -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-shield-alt me-2"></i>上传权限受限</h3>
                        </div>
                        <div class="card-body text-center py-5">
                            <i class="fas fa-lock display-1 text-warning mb-4"></i>
                            <h4 class="text-warning mb-3">需要登录才能上传图片</h4>
                            <p class="text-muted mb-4">系统管理员已启用登录上传功能，请先登录管理员账户</p>
                            <a href="admin_login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>立即登录
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- 正常上传区域 -->
                    <div class="card" id="uploadCard">
                        <div class="card-header">
                            <h3><i class="fas fa-cloud-upload-alt me-2"></i>图片上传</h3>
                            <?php if ($isLoggedIn): ?>
                                <small class="text-white-50"><i class="fas fa-check-circle me-1"></i>已登录</small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form id="uploadForm" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label for="image" class="form-label">选择图片文件</label>
                                    <div class="upload-area" id="dropArea">
                                        <i class="fas fa-file-image display-1 text-muted mb-3"></i>
                                        <p class="mb-2">拖放图片到此处或点击选择文件</p>
                                        <small class="text-muted">支持 JPG, PNG, GIF, WebP 格式</small>
                                        <input type="file" class="form-control d-none" id="image" name="image" accept="image/*" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="tags" class="form-label">图片标签</label>
                                    <input type="text" class="form-control" id="tags" name="tags" placeholder="例如：风景,旅游,自然（多个标签用逗号分隔）">
                                    <div class="form-text">添加标签有助于图片分类和管理</div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-upload me-2"></i>上传图片
                                    </button>
                                </div>
                            </form>
                            <div id="uploadProgress" class="mt-4" style="display: none;">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>上传进度</span>
                                    <span id="progressPercent">0%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 上传结果显示区域 -->
                <div class="card mt-4" id="uploadResult" style="display: none;">
                    <div class="card-header">
                        <h4>上传结果</h4>
                    </div>
                    <div class="card-body">
                        <div id="uploadedImageContainer"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>