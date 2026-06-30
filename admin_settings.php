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

function detectBaseUrl() {
    $protocol = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $protocol = 'https';
    elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) $protocol = 'https';
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') $protocol = 'https';
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') $protocol = 'https';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $protocol . '://' . $host . $scriptDir;
}

$settings = getSettings();
$settings['base_url'] = $settings['base_url'] ?? '';
$settings['require_login'] = $settings['require_login'] ?? false;
$settings['bg_image'] = $settings['bg_image'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = getSettings();

    if (isset($_POST['save_settings'])) {
        $newSettings['base_url'] = trim($_POST['base_url'] ?? '');
        $newSettings['require_login'] = isset($_POST['require_login']) && $_POST['require_login'] === '1';
        $newSettings['bg_image'] = $settings['bg_image'] ?? '';
    }

    // 处理背景图片上传
    if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] === UPLOAD_ERR_OK) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['bg_image']['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($mime, $allowed)) {
            $ext = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
            $dest = 'assets/img/bg_custom.' . $ext;
            if (!is_dir('assets/img')) mkdir('assets/img', 0755, true);
            if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $dest)) {
                $newSettings['bg_image'] = 'bg_custom.' . $ext;
            }
        } else {
            $message = '错误：背景图片格式不支持，仅支持 JPG/PNG/GIF/WebP';
        }
    }

    // 删除背景图片
    if (isset($_POST['remove_bg'])) {
        if (!empty($settings['bg_image']) && file_exists('assets/img/' . $settings['bg_image'])) {
            unlink('assets/img/' . $settings['bg_image']);
        }
        $newSettings['bg_image'] = '';
    }

    // 验证基础URL格式
    if (!empty($newSettings['base_url']) && !filter_var($newSettings['base_url'], FILTER_VALIDATE_URL)) {
        $message = '错误：基础URL格式不正确';
    } else {
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }

        foreach (getSensitiveFields() as $field) {
            if (isset($newSettings[$field])) {
                $newSettings[$field] = encryptSetting($newSettings[$field]);
            }
        }

        if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT))) {
            if (empty($message)) {
                $message = '设置保存成功！';
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
}

// 重新生成所有缩略图
if (isset($_POST['regenerate_thumbs'])) {
    $db = getDBConnection();
    $stmt = $db->query("SELECT id, local_path, storage_type, filename FROM images ORDER BY id");
    $count = 0;
    $errors = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $source = null;
        if ($row['storage_type'] === 'local' && file_exists($row['local_path'])) {
            $source = $row['local_path'];
        }
        if ($source && generateThumbnail($source, $row['filename'])) {
            $count++;
        } elseif ($source) {
            $errors++;
        }
    }
    $message = "缩略图生成完成：成功 {$count} 张" . ($errors ? "，失败 {$errors} 张" : '');
}

$currentPage = 'settings';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - PicHost</title>
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
                    <a class="sidebar-nav-link" href="admin_storage.php">
                        <i class="fas fa-hdd"></i>
                        <span>储存管理</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link active" href="admin_settings.php">
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
                <div class="alert alert-<?php echo strpos($message, '错误') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- 域名配置 -->
                <div class="card" style="border-left: 4px solid #ffc107;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-globe me-2"></i>域名配置</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="base_url" class="form-label">基础URL</label>
                            <input type="url" class="form-control" id="base_url" name="base_url" 
                                   value="<?php echo htmlspecialchars($settings['base_url']); ?>" 
                                   placeholder="<?php echo detectBaseUrl(); ?>">
                            <div class="form-text">
                                用于生成图片的完整访问链接。留空则自动使用当前域名：<code><?php echo detectBaseUrl(); ?></code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 上传权限配置 -->
                <div class="card" style="border-left: 4px solid #dc3545;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>上传权限配置</h5>
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

                <!-- 背景图配置 -->
                <div class="card" style="border-left: 4px solid #8b5cf6;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-image me-2"></i>背景图设置</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">当前背景图</label>
                            <div class="mb-2">
                                <?php if (!empty($settings['bg_image']) && file_exists('assets/img/' . $settings['bg_image'])): ?>
                                    <img src="assets/img/<?php echo $settings['bg_image']; ?>" class="img-fluid rounded border" style="max-height:120px;object-fit:cover;width:100%;">
                                    <div class="mt-2">
                                        <label class="form-check-label">
                                            <input type="checkbox" name="remove_bg" value="1"> 删除自定义背景图，恢复默认
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small py-3 text-center border rounded bg-light">使用默认背景图</div>
                                    <img src="assets/img/bg.jpg" class="img-fluid rounded border mt-2" style="max-height:80px;object-fit:cover;width:100%;">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="bg_image_file" class="form-label">上传新背景图</label>
                            <input type="file" class="form-control form-control-sm" id="bg_image_file" name="bg_image" accept="image/*">
                            <div class="form-text">推荐尺寸 1920×1080，支持 JPG/PNG/GIF/WebP</div>
                        </div>
                    </div>
                </div>

                <!-- 当前配置信息 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>当前配置信息</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php
                                    $items = [];

                                    $items[] = ['label' => '基础URL', 'value' => empty($settings['base_url']) ? '未配置' : $settings['base_url'], 'status' => empty($settings['base_url']) ? 'danger' : 'success'];
                                    $items[] = ['label' => '登录要求', 'value' => $settings['require_login'] ? '已启用（需登录上传）' : '未启用（允许匿名上传）', 'status' => $settings['require_login'] ? 'warning' : 'success'];
                                    $items[] = ['label' => '背景图', 'value' => !empty($settings['bg_image']) ? '自定义' : '默认', 'status' => !empty($settings['bg_image']) ? 'success' : 'secondary'];
                                    $items[] = ['label' => '配置文件', 'value' => file_exists($settingsFile) ? '已创建' : '未创建', 'status' => file_exists($settingsFile) ? 'success' : 'danger'];
                                    ?>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="text-muted ps-3" style="width:100px;font-size:0.85rem;"><?php echo $item['label']; ?></td>
                                        <td class="fw-medium" style="font-size:0.85rem;"><?php echo $item['value']; ?></td>
                                        <td class="text-end pe-3" style="width:60px;">
                                            <span class="badge bg-<?php echo $item['status']; ?> rounded-pill" style="font-size:0.65rem;">
                                                <?php echo $item['status'] === 'success' ? '正常' : ($item['status'] === 'warning' ? '注意' : ($item['status'] === 'danger' ? '未配' : '-')); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" name="regenerate_thumbs" value="1" class="btn btn-outline-secondary btn-sm" onclick="return confirm('确定重新生成所有已有图片的缩略图吗？')">
                            <i class="fas fa-sync-alt me-1"></i> 重新生成所有缩略图
                        </button>
                    </div>
                    <button type="submit" name="save_settings" value="1" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>保存设置
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
