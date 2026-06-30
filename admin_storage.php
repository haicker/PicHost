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
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-images"></i> PicHost
            </a>
            <div class="navbar-nav ms-auto d-none d-lg-flex">
                <a class="nav-link" href="index.php"><i class="fas fa-globe me-1"></i>返回前台</a>
            </div>
        </div>
    </nav>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="admin-wrapper">
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
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
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(mb_substr($_SESSION['admin_username'], 0, 1)); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
                </div>
                <div class="sidebar-actions">
                    <a href="index.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-home me-1"></i>前台
                    </a>
                    <a href="admin.php?action=logout" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>退出
                    </a>
                </div>
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
                            <div class="text-muted small mb-1"><i class="fas fa-balance-scale me-1"></i>平均大小</div>
                            <div class="h4 mb-0 fw-bold text-secondary"><?php echo formatFileSize($avgSize); ?></div>
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

            <!-- 存储分布 + 文件类型分布 -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>存储分布</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $storageLabels = [
                                'local' => ['本地存储', 'fas fa-server', 'warning'],
                                'github' => ['GitHub存储', 'fab fa-github', 'success'],
                                'webdav' => ['WebDAV存储', 'fas fa-cloud', 'info'],
                                'telegram' => ['Telegram存储', 'fab fa-telegram', 'danger']
                            ];
                            foreach ($storageTypes as $type):
                                $info = $storageLabels[$type];
                                $pctCount = $totalImages > 0 ? ($storageStats[$type]['count'] / $totalImages * 100) : 0;
                                $pctSize = $totalSize > 0 ? ($storageStats[$type]['size'] / $totalSize * 100) : 0;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-medium small">
                                        <i class="<?php echo $info[1]; ?> me-1 text-<?php echo $info[2]; ?>"></i>
                                        <?php echo $info[0]; ?>
                                        <?php if (($settings['default_storage'] ?? 'local') === $type): ?>
                                            <span class="badge bg-primary" style="font-size:0.6rem;">默认</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="small text-muted"><?php echo $storageStats[$type]['count']; ?> 张 · <?php echo formatFileSize($storageStats[$type]['size']); ?></span>
                                </div>
                                <div class="progress" style="height:8px;">
                                    <div class="progress-bar bg-<?php echo $info[2]; ?>" style="width:<?php echo round($pctSize, 1); ?>%"><?php echo round($pctSize, 1); ?>%</div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mt-1">
                                    <span>数量占比 <?php echo round($pctCount, 1); ?>%</span>
                                    <span>空间占比 <?php echo round($pctSize, 1); ?>%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if ($localDiskFree !== false && $localDiskTotal !== false): ?>
                            <div class="mt-3 pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-medium small"><i class="fas fa-hdd me-1"></i>本地磁盘</span>
                                    <span class="small text-muted"><?php echo formatFileSize($localDiskTotal - $localDiskFree); ?> / <?php echo formatFileSize($localDiskTotal); ?></span>
                                </div>
                                <div class="progress" style="height:8px;">
                                    <?php $diskPct = $localDiskTotal > 0 ? (($localDiskTotal - $localDiskFree) / $localDiskTotal * 100) : 0; ?>
                                    <div class="progress-bar bg-<?php echo $diskPct > 90 ? 'danger' : ($diskPct > 70 ? 'warning' : 'secondary'); ?>" style="width:<?php echo round($diskPct, 1); ?>%"><?php echo round($diskPct, 1); ?>%</div>
                                </div>
                                <div class="small text-muted mt-1">可用空间: <?php echo formatFileSize($localDiskFree); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-image me-2"></i>文件类型分布</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($mimeStats)): ?>
                                <?php
                                $mimeIcons = [
                                    'image/jpeg' => 'fas fa-file-image',
                                    'image/jpg' => 'fas fa-file-image',
                                    'image/png' => 'fas fa-file-image',
                                    'image/gif' => 'fas fa-film',
                                    'image/webp' => 'fas fa-file-image'
                                ];
                                $mimeColors = [
                                    'image/jpeg' => 'warning',
                                    'image/jpg' => 'warning',
                                    'image/png' => 'primary',
                                    'image/gif' => 'info',
                                    'image/webp' => 'success'
                                ];
                                $mimeNames = [
                                    'image/jpeg' => 'JPEG',
                                    'image/jpg' => 'JPG',
                                    'image/png' => 'PNG',
                                    'image/gif' => 'GIF',
                                    'image/webp' => 'WebP'
                                ];
                                foreach ($mimeStats as $mime):
                                    $mimeType = $mime['mime_type'];
                                    $icon = $mimeIcons[$mimeType] ?? 'fas fa-file';
                                    $color = $mimeColors[$mimeType] ?? 'secondary';
                                    $name = $mimeNames[$mimeType] ?? $mimeType;
                                    $pctCount = $totalImages > 0 ? ($mime['count'] / $totalImages * 100) : 0;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-medium small">
                                            <i class="<?php echo $icon; ?> me-1 text-<?php echo $color; ?>"></i>
                                            <?php echo $name; ?>
                                        </span>
                                        <span class="small text-muted"><?php echo $mime['count']; ?> 张 · <?php echo formatFileSize($mime['total_size']); ?></span>
                                    </div>
                                    <div class="progress" style="height:8px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width:<?php echo round($pctCount, 1); ?>%"><?php echo round($pctCount, 1); ?>%</div>
                                    </div>
                                    <div class="small text-muted mt-1">占比 <?php echo round($pctCount, 1); ?>% · 平均 <?php echo formatFileSize($mime['count'] > 0 ? $mime['total_size'] / $mime['count'] : 0); ?>/张</div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox display-6 mb-2"></i>
                                    <p class="mb-0">暂无数据</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 上传趋势 + 最近上传 -->
            <div class="row mb-4">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>近30天上传趋势</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($dailyStats)): ?>
                                <div class="mb-3">
                                    <?php
                                    $maxCount = max(array_column($dailyStats, 'count'));
                                    $maxCount = max($maxCount, 1);
                                    foreach ($dailyStats as $day):
                                        $pct = ($day['count'] / $maxCount) * 100;
                                        $date = $day['date'];
                                    ?>
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="small text-muted" style="width:80px;flex-shrink:0;"><?php echo substr($date, 5); ?></span>
                                        <div class="progress flex-grow-1" style="height:18px;">
                                            <div class="progress-bar bg-primary" style="width:<?php echo $pct; ?>%;min-width:20px;">
                                                <span style="font-size:0.7rem;line-height:18px;"><?php echo $day['count']; ?></span>
                                            </div>
                                        </div>
                                        <span class="small text-muted ms-2" style="width:60px;flex-shrink:0;"><?php echo formatFileSize($day['total_size']); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="small text-muted border-top pt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    近30天共上传 <strong class="text-primary"><?php echo array_sum(array_column($dailyStats, 'count')); ?></strong> 张，
                                    合计 <strong class="text-info"><?php echo formatFileSize(array_sum(array_column($dailyStats, 'total_size'))); ?></strong>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-chart-line display-6 mb-2"></i>
                                    <p class="mb-0">近30天暂无上传记录</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>最近上传</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($recentImages)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>文件名</th>
                                                <th style="width:60px">大小</th>
                                                <th style="width:60px">存储</th>
                                                <th style="width:90px">时间</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <?php foreach ($recentImages as $img): ?>
                                            <tr>
                                                <td class="fw-medium text-truncate" style="max-width:150px;" title="<?php echo htmlspecialchars($img['original_name']); ?>">
                                                    <?php echo htmlspecialchars($img['original_name']); ?>
                                                </td>
                                                <td><?php echo formatFileSize($img['file_size']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $img['storage_type'] === 'github' ? 'success' : ($img['storage_type'] === 'webdav' ? 'info' : ($img['storage_type'] === 'telegram' ? 'danger' : 'warning')); ?>" style="font-size:0.6rem;">
                                                        <?php echo $img['storage_type'] === 'github' ? 'GH' : ($img['storage_type'] === 'webdav' ? 'WD' : ($img['storage_type'] === 'telegram' ? 'TG' : '本地')); ?>
                                                    </span>
                                                </td>
                                                <td class="text-muted"><?php echo date('m-d H:i', strtotime($img['upload_time'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox display-6 mb-2"></i>
                                    <p class="mb-0">暂无上传记录</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($todayHourly)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-sun me-2"></i>今日上传分布</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-end gap-1" style="height:80px;">
                                <?php
                                $maxHour = max(array_column($todayHourly, 'count'));
                                $maxHour = max($maxHour, 1);
                                foreach ($todayHourly as $hr):
                                    $barPct = ($hr['count'] / $maxHour) * 100;
                                ?>
                                <div class="d-flex flex-column align-items-center flex-fill" title="<?php echo $hr['hour']; ?>时: <?php echo $hr['count']; ?>张">
                                    <div class="w-100 rounded" style="height:<?php echo max($barPct, 5); ?>%;background:linear-gradient(135deg, #0ea5e9, #3b82f6);min-height:4px;"></div>
                                    <span class="small text-muted" style="font-size:0.65rem;"><?php echo $hr['hour']; ?>时</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 配置区 -->
            <form method="POST" enctype="multipart/form-data">
                <!-- 默认存储类型 -->
                <div class="card storage-card storage-type mb-4">
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
                                选择图片的默认存储位置。如果选择的存储方式配置不完整，系统将自动使用本地存储。
                            </div>
                        </div>
                    </div>
                </div>

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

                <!-- 当前配置信息 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>配置状态</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php
                                    $ghOk = !empty($settings['github_token']) && !empty($settings['github_repo_owner']) && !empty($settings['github_repo_name']);
                                    $wdOk = !empty($settings['webdav_url']) && !empty($settings['webdav_username']) && !empty($settings['webdav_password']);
                                    $tgOk = !empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id']);
                                    $defaultStorage = $settings['default_storage'] ?? 'local';
                                    $storageMap = ['local' => '本地存储', 'github' => 'GitHub存储', 'webdav' => 'WebDAV存储', 'telegram' => 'Telegram存储'];
                                    $storageLabel = $storageMap[$defaultStorage] ?? '本地存储';
                                    $storageStatus = 'primary';
                                    if ($defaultStorage === 'webdav' && !$wdOk) { $storageLabel = 'WebDAV存储（配置不完整）'; $storageStatus = 'warning'; }
                                    if ($defaultStorage === 'github' && !$ghOk) { $storageLabel = 'GitHub存储（配置不完整）'; $storageStatus = 'warning'; }
                                    if ($defaultStorage === 'telegram' && !$tgOk) { $storageLabel = 'Telegram存储（配置不完整）'; $storageStatus = 'warning'; }
                                    ?>
                                    <tr>
                                        <td class="text-muted ps-3" style="width:120px;font-size:0.85rem;">GitHub</td>
                                        <td class="fw-medium" style="font-size:0.85rem;">
                                            <?php echo $ghOk ? htmlspecialchars($settings['github_repo_owner']) . '/' . htmlspecialchars($settings['github_repo_name']) : '未配置'; ?>
                                        </td>
                                        <td class="text-end pe-3" style="width:60px;">
                                            <span class="badge bg-<?php echo $ghOk ? 'success' : 'danger'; ?> rounded-pill" style="font-size:0.65rem;">
                                                <?php echo $ghOk ? '正常' : '未配'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3" style="font-size:0.85rem;">WebDAV</td>
                                        <td class="fw-medium" style="font-size:0.85rem;">
                                            <?php echo $wdOk ? htmlspecialchars($settings['webdav_url']) : '未配置'; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <span class="badge bg-<?php echo $wdOk ? 'success' : 'danger'; ?> rounded-pill" style="font-size:0.65rem;">
                                                <?php echo $wdOk ? '正常' : '未配'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3" style="font-size:0.85rem;">Telegram</td>
                                        <td class="fw-medium" style="font-size:0.85rem;">
                                            <?php echo $tgOk ? htmlspecialchars($settings['telegram_chat_id']) : '未配置'; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <span class="badge bg-<?php echo $tgOk ? 'success' : 'danger'; ?> rounded-pill" style="font-size:0.65rem;">
                                                <?php echo $tgOk ? '正常' : '未配'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted ps-3" style="font-size:0.85rem;">默认存储</td>
                                        <td class="fw-medium" style="font-size:0.85rem;"><?php echo $storageLabel; ?></td>
                                        <td class="text-end pe-3">
                                            <span class="badge bg-<?php echo $storageStatus; ?> rounded-pill" style="font-size:0.65rem;">
                                                <?php echo $storageStatus === 'primary' ? '正常' : '注意'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
