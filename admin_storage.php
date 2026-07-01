<?php
if (!defined('INSTALLED') && !file_exists('.installed')) {
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

$settings = getSettings();
$settings['github_repo_path'] = $settings['github_repo_path'] ?? 'images';
$settings['webdav_path'] = $settings['webdav_path'] ?? 'images';
$settings['telegram_bot_token'] = $settings['telegram_bot_token'] ?? '';
$settings['telegram_chat_id'] = $settings['telegram_chat_id'] ?? '';
$settings['default_storage'] = $settings['default_storage'] ?? 'local';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = getSettings();

    if (isset($_POST['save_storage'])) {
        $newSettings['github_token'] = trim($_POST['github_token'] ?? '');
        $newSettings['github_repo_owner'] = trim($_POST['github_repo_owner'] ?? '');
        $newSettings['github_repo_name'] = trim($_POST['github_repo_name'] ?? '');
        $newSettings['github_repo_path'] = trim($_POST['github_repo_path'] ?? 'images');
        $newSettings['webdav_url'] = trim($_POST['webdav_url'] ?? '');
        $newSettings['webdav_username'] = trim($_POST['webdav_username'] ?? '');
        $newSettings['webdav_password'] = trim($_POST['webdav_password'] ?? '');
        $newSettings['webdav_path'] = trim($_POST['webdav_path'] ?? 'images');
        $newSettings['telegram_bot_token'] = trim($_POST['telegram_bot_token'] ?? '');
        $newSettings['telegram_chat_id'] = trim($_POST['telegram_chat_id'] ?? '');
        $newSettings['default_storage'] = trim($_POST['default_storage'] ?? 'local');

        if ($newSettings['default_storage'] === 'github') {
            if (empty($newSettings['github_token']) || empty($newSettings['github_repo_owner']) || empty($newSettings['github_repo_name'])) {
                $message = '警告：选择了GitHub存储，但GitHub配置不完整。系统将使用本地存储。';
            }
        }

        if ($newSettings['default_storage'] === 'webdav') {
            if (empty($newSettings['webdav_url']) || empty($newSettings['webdav_username']) || empty($newSettings['webdav_password'])) {
                $message = '警告：选择了WebDAV存储，但WebDAV配置不完整。系统将使用本地存储。';
            }
        }

        if ($newSettings['default_storage'] === 'telegram') {
            if (empty($newSettings['telegram_bot_token']) || empty($newSettings['telegram_chat_id'])) {
                $message = '警告：选择了Telegram存储，但Telegram配置不完整。系统将使用本地存储。';
            }
        }
    }

    if (!is_dir('config')) {
        mkdir('config', 0755, true);
    }

    // 加密敏感字段
    foreach (getSensitiveFields() as $field) {
        if (isset($newSettings[$field])) {
            $newSettings[$field] = encryptSetting($newSettings[$field]);
        }
    }

    if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT))) {
        if (empty($message)) {
            $message = '储存设置保存成功！';
        }
        $settings = $newSettings;
        foreach (getSensitiveFields() as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = decryptSetting($settings[$field]);
            }
        }
    } else {
        $message = '错误：设置保存失败';
    }
}

// 统计各存储类型图片数
$db = getDBConnection();
$storageStats = [];
$storageTypes = ['local', 'github', 'webdav', 'telegram'];
$imagesPerType = [];
$sizePerType = [];

foreach ($storageTypes as $type) {
    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM images WHERE storage_type = ?");
    $stmt->execute([$type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $storageStats[$type] = [
        'count' => (int)$row['count'],
        'size' => (int)$row['total_size']
    ];
}

// 总体统计
$totalImages = array_sum(array_column($storageStats, 'count'));
$totalSize = array_sum(array_column($storageStats, 'size'));

// 文件类型统计
$mimeStmt = $db->query("SELECT mime_type, COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM images GROUP BY mime_type ORDER BY count DESC");
$mimeStats = $mimeStmt->fetchAll(PDO::FETCH_ASSOC);

// 每日上传趋势（最近30天）
$dailyStmt = $db->query("SELECT DATE(upload_time) as date, COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM images WHERE upload_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(upload_time) ORDER BY date ASC");
$dailyStats = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

// 本地磁盘使用情况
$localDiskFree = @disk_free_space('uploads');
$localDiskTotal = @disk_total_space('uploads');

// 按小时统计今日上传
$todayStmt = $db->query("SELECT HOUR(upload_time) as hour, COUNT(*) as count FROM images WHERE DATE(upload_time) = CURDATE() GROUP BY HOUR(upload_time) ORDER BY hour");
$todayHourly = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

// 平均文件大小
$avgSize = $totalImages > 0 ? $totalSize / $totalImages : 0;

// 活跃存储类型数
$activeTypes = 0;
foreach ($storageStats as $stat) {
    if ($stat['count'] > 0) $activeTypes++;
}

// 最近上传的5张图片
$recentStmt = $db->query("SELECT id, original_name, file_size, storage_type, upload_time FROM images ORDER BY upload_time DESC LIMIT 5");
$recentImages = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'storage';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>储存管理 - PicHost</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="shortcut icon" href="favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="admin-wrapper">
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <button class="sidebar-close-btn" onclick="toggleSidebar()">&times;</button>
                <h5>PicHost</h5>
                <small>图床管理后台</small>
            </div>

            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link" href="admin.php">
                        <i class="fas fa-images"></i>
                        <span>图片管理</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link active" href="admin_storage.php">
                        <i class="fas fa-hdd"></i>
                        <span>储存管理</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link" href="admin_settings.php">
                        <i class="fas fa-cog"></i>
                        <span>系统设置</span>
                    </a>
                </li>

                <li><div class="sidebar-divider"></div></li>

                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link" href="index.php">
                        <i class="fas fa-globe"></i>
                        <span>返回前台</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <div class="user-avatar">
                    <?php echo strtoupper(mb_substr($_SESSION['admin_username'], 0, 1)); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="admin.php?action=logout" class="btn btn-sm btn-logout" title="退出">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>

        <main class="admin-main">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo strpos($message, '错误') !== false ? 'danger' : (strpos($message, '警告') !== false ? 'warning' : 'success'); ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- 总览统计卡片 -->
            <div class="row mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small mb-1"><i class="fas fa-images me-1"></i>图片总数</div>
                            <div class="h4 mb-0 fw-bold text-primary"><?php echo $totalImages; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small mb-1"><i class="fas fa-database me-1"></i>总占用</div>
                            <div class="h4 mb-0 fw-bold text-info"><?php echo formatFileSize($totalSize); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small mb-1"><i class="fas fa-server me-1"></i>已配存储</div>
                            <div class="h4 mb-0 fw-bold text-secondary"><?php echo $activeTypes; ?>/4</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small mb-1"><i class="fas fa-calendar-day me-1"></i>今日上传</div>
                            <div class="h4 mb-0 fw-bold text-success"><?php echo array_sum(array_column($todayHourly, 'count')); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
            <!-- 存储分布 + 默认存储类型 -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card storage-card storage-type h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-database me-2"></i>默认存储类型</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="default_storage" id="storage_local" value="local"
                                           <?php echo ($settings['default_storage'] ?? 'local') === 'local' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="storage_local">
                                        <strong>本地存储</strong> - 图片保存在服务器本地
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="default_storage" id="storage_github" value="github"
                                           <?php echo ($settings['default_storage'] ?? 'local') === 'github' ? 'checked' : ''; ?>
                                           <?php echo empty($settings['github_token']) || empty($settings['github_repo_owner']) || empty($settings['github_repo_name']) ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="storage_github">
                                        <strong>GitHub存储</strong> - 图片上传到GitHub仓库
                                        <?php if (empty($settings['github_token']) || empty($settings['github_repo_owner']) || empty($settings['github_repo_name'])): ?>
                                            <span class="badge bg-warning text-dark ms-2">需先配置</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="default_storage" id="storage_webdav" value="webdav"
                                           <?php echo ($settings['default_storage'] ?? 'local') === 'webdav' ? 'checked' : ''; ?>
                                           <?php echo empty($settings['webdav_url']) || empty($settings['webdav_username']) || empty($settings['webdav_password']) ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="storage_webdav">
                                        <strong>WebDAV存储</strong> - 图片上传到WebDAV服务器
                                        <?php if (empty($settings['webdav_url']) || empty($settings['webdav_username']) || empty($settings['webdav_password'])): ?>
                                            <span class="badge bg-info text-dark ms-2">需先配置</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="default_storage" id="storage_telegram" value="telegram"
                                           <?php echo ($settings['default_storage'] ?? 'local') === 'telegram' ? 'checked' : ''; ?>
                                           <?php echo empty($settings['telegram_bot_token']) || empty($settings['telegram_chat_id']) ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="storage_telegram">
                                        <strong>Telegram存储</strong> - 图片上传到Telegram频道/群组
                                        <?php if (empty($settings['telegram_bot_token']) || empty($settings['telegram_chat_id'])): ?>
                                            <span class="badge bg-danger text-white ms-2">需先配置</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <div class="form-text">
                                    如果选择的存储方式配置不完整，系统将自动使用本地存储。
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>存储分布</h5>
                        </div>
                        <div class="card-body">
                            
                            <?php
                            $ghOk = !empty($settings['github_token']) && !empty($settings['github_repo_owner']) && !empty($settings['github_repo_name']);
                            $wdOk = !empty($settings['webdav_url']) && !empty($settings['webdav_username']) && !empty($settings['webdav_password']);
                            $tgOk = !empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id']);
                            $configOk = ['local' => true, 'github' => $ghOk, 'webdav' => $wdOk, 'telegram' => $tgOk];
                            $storageLabels = [
                                'local' => ['本地存储', 'fas fa-server', 'warning', '#ffc107'],
                                'github' => ['GitHub存储', 'fab fa-github', 'success', '#198754'],
                                'webdav' => ['WebDAV存储', 'fas fa-cloud', 'info', '#0dcaf0'],
                                'telegram' => ['Telegram存储', 'fab fa-telegram', 'danger', '#dc3545']
                            ];
                            ?>
                            <?php foreach ($storageTypes as $type):
                                $info = $storageLabels[$type];
                                $pctCount = $totalImages > 0 ? ($storageStats[$type]['count'] / $totalImages * 100) : 0;
                                $pctSize = $totalSize > 0 ? ($storageStats[$type]['size'] / $totalSize * 100) : 0;
                            ?>
                            <div class="d-flex align-items-center mb-2">
                                <?php if ($type !== 'local'): ?>
                                    <span class="badge bg-<?php echo $configOk[$type] ? 'success' : 'danger'; ?> rounded-pill me-2"><?php echo $configOk[$type] ? '正常' : '未配'; ?></span>
                                <?php elseif (($settings['default_storage'] ?? 'local') === $type): ?>
                                    <span class="badge bg-primary me-2">默认</span>
                                <?php else: ?>
                                    <span class="d-inline-block rounded-circle me-2" style="width:10px;height:10px;background:<?php echo $info[3]; ?>"></span>
                                <?php endif; ?>
                                <span class="fw-medium text-nowrap">
                                    <i class="<?php echo $info[1]; ?> me-1 text-<?php echo $info[2]; ?>"></i>
                                    <?php echo $info[0]; ?>
                                </span>
                                <span class="text-muted ms-auto text-nowrap" style="font-size:0.85rem;"><?php echo $storageStats[$type]['count']; ?> 张 · <?php echo formatFileSize($storageStats[$type]['size']); ?></span>
                            </div>
                            <?php endforeach; ?>
                            <div class="small text-muted mt-2 pt-2 border-top">总计: <?php echo $totalImages; ?> 张 · <?php echo formatFileSize($totalSize); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 配置区 -->
            <div class="row">
                <div class="col-12">
                <!-- GitHub配置 -->
                <div class="card storage-card storage-github mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fab fa-github me-2"></i>GitHub 配置</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="github_token" class="form-label">GitHub Token</label>
                            <input type="password" class="form-control" id="github_token" name="github_token" 
                                   value="<?php echo htmlspecialchars($settings['github_token']); ?>" 
                                   placeholder="输入GitHub Personal Access Token">
                            <div class="form-text">需要在GitHub生成Personal Access Token，并授予repo权限</div>
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
                                    <div class="form-text">图片在仓库中的存储目录</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WebDAV配置 -->
                <div class="card storage-card storage-webdav mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cloud me-2"></i>WebDAV 配置</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="webdav_url" class="form-label">WebDAV服务器地址</label>
                            <input type="url" class="form-control" id="webdav_url" name="webdav_url" 
                                   value="<?php echo htmlspecialchars($settings['webdav_url']); ?>" 
                                   placeholder="https://dav.example.com/webdav">
                            <div class="form-text">WebDAV服务器的完整URL地址</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="webdav_username" class="form-label">用户名</label>
                                    <input type="text" class="form-control" id="webdav_username" name="webdav_username" 
                                           value="<?php echo htmlspecialchars($settings['webdav_username']); ?>" 
                                           placeholder="WebDAV用户名">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="webdav_password" class="form-label">密码</label>
                                    <input type="password" class="form-control" id="webdav_password" name="webdav_password" 
                                           value="<?php echo htmlspecialchars($settings['webdav_password']); ?>" 
                                           placeholder="WebDAV密码">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="webdav_path" class="form-label">存储路径</label>
                                    <input type="text" class="form-control" id="webdav_path" name="webdav_path" 
                                           value="<?php echo htmlspecialchars($settings['webdav_path']); ?>" 
                                           placeholder="images">
                                    <div class="form-text">图片在WebDAV服务器中的存储目录</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Telegram配置 -->
                <div class="card storage-card storage-telegram mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fab fa-telegram me-2"></i>Telegram 配置</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="telegram_bot_token" class="form-label">Bot Token</label>
                            <input type="password" class="form-control" id="telegram_bot_token" name="telegram_bot_token" 
                                   value="<?php echo htmlspecialchars($settings['telegram_bot_token']); ?>" 
                                   placeholder="输入Telegram Bot Token">
                            <div class="form-text">通过 @BotFather 创建Bot获取Token</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telegram_chat_id" class="form-label">Chat ID</label>
                                    <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" 
                                           value="<?php echo htmlspecialchars($settings['telegram_chat_id']); ?>" 
                                           placeholder="输入目标频道/群组ID">
                                    <div class="form-text">图片上传到的频道或群组ID，频道格式如 <code>@channelname</code>，群组为负数ID</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">获取方式</label>
                                    <div class="form-text">
                                        <ol class="mb-0 ps-3">
                                            <li>在Telegram中搜索 <code>@BotFather</code> 创建一个Bot</li>
                                            <li>将Bot添加到频道/群组并设为管理员</li>
                                            <li>频道ID格式: <code>@your_channel</code>，群组ID为负数</li>
                                            <li>可通过 <code>@userinfobot</code> 获取群组ID</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
                </div>
            </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" name="save_storage" value="1" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>保存储存设置
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
