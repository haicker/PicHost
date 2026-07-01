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

$action = $_GET['action'] ?? '';
$message = '';

$settings = getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_image'])) {
        $imageId = $_POST['image_id'];
        $message = deleteImage($imageId) ? '图片删除成功' : '图片删除失败';
    }
}

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// 统计信息
$db = getDBConnection();
$stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM images");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalImages = (int)$stats['count'];
$totalSize = (int)$stats['total_size'];

// 标签
$allTags = [];
$tagStmt = $db->query("SELECT DISTINCT tags FROM images WHERE tags IS NOT NULL AND tags != ''");
while ($row = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
    foreach (explode(',', $row['tags']) as $tag) {
        $tag = trim($tag);
        if ($tag !== '' && !in_array($tag, $allTags)) {
            $allTags[] = $tag;
        }
    }
}
sort($allTags);

// 图片数据 - 标签筛选也支持分页
$selectedTag = $_GET['tag'] ?? '';

if ($selectedTag && $selectedTag !== 'all') {
    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM images WHERE tags LIKE ?");
    $countStmt->execute(['%' . $selectedTag . '%']);
    $tagCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    $totalPages = max(1, ceil($tagCount / $perPage));

    $stmt = $db->prepare("SELECT id, filename, original_name, tags, file_size, mime_type, github_url, webdav_url, telegram_url, local_path, upload_time, storage_type FROM images WHERE tags LIKE ? ORDER BY upload_time DESC LIMIT ? OFFSET ?");
    $stmt->execute(['%' . $selectedTag . '%', $perPage, $offset]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseUrl = rtrim(getConfig('base_url'), '/');
    foreach ($images as &$image) {
        if ($image['storage_type'] === 'github' && !empty($image['github_url'])) {
            $image['url'] = $image['github_url'];
        } elseif ($image['storage_type'] === 'webdav' && !empty($image['webdav_url'])) {
            $image['url'] = $baseUrl . '/proxy.php?id=' . $image['id'];
        } elseif ($image['storage_type'] === 'telegram' && !empty($image['telegram_url'])) {
            $image['url'] = $baseUrl . '/proxy.php?id=' . $image['id'];
        } else {
            $localPath = ltrim($image['local_path'], '/');
            $image['url'] = $baseUrl . '/' . $localPath;
        }
        $image['thumb_url'] = getThumbnailUrl($image);
    }
    unset($image);
} else {
    $totalPages = max(1, ceil($totalImages / $perPage));
    $images = getImages($perPage, $offset);
}

$currentPage = 'images';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片管理 - PicHost</title>
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
                    <a class="sidebar-nav-link active" href="admin.php">
                        <i class="fas fa-images"></i>
                        <span>图片管理</span>
                        <?php if ($totalImages > 0): ?>
                        <span class="badge bg-primary rounded-pill nav-badge"><?php echo $totalImages; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link" href="admin_storage.php">
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
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-3 mb-4">
                    <!-- 批量上传 -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>批量上传</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <input type="text" class="form-control form-control-sm mb-2" id="batchTags" placeholder="标签（可选，所有图片共用）">
                                <div class="d-flex gap-2">
                                    <input type="file" class="d-none" id="batchFileInput" accept="image/*" multiple>
                                    <button class="btn btn-outline-primary btn-sm flex-fill" onclick="document.getElementById('batchFileInput').click()">
                                        <i class="fas fa-images me-1"></i> <span id="batchFileLabel">选择图片</span>
                                    </button>
                                    <button class="btn btn-primary btn-sm" id="batchUploadBtn" onclick="startBatchUpload()" disabled>
                                        <i class="fas fa-upload me-1"></i> 上传
                                    </button>
                                </div>
                            </div>
                            <div id="batchProgress" style="display: none;">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span id="batchStatus">准备中...</span>
                                    <span id="batchCount">0 / 0</span>
                                </div>
                                <div class="progress mb-1" style="height: 4px;">
                                    <div class="progress-bar" id="batchProgressBar" style="width: 0%"></div>
                                </div>
                                <div id="batchResults" class="small" style="max-height: 150px; overflow-y: auto;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 标签管理 -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>标签管理</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($allTags)): ?>
                            <div class="d-flex flex-wrap gap-1">
                                <a href="?tag=all" class="btn btn-<?php echo empty($selectedTag) || $selectedTag === 'all' ? 'primary' : 'outline-primary'; ?> btn-sm">
                                    全部
                                </a>
                                <?php foreach ($allTags as $tag): ?>
                                <a href="?tag=<?php echo urlencode($tag); ?>" class="btn btn-<?php echo $selectedTag === $tag ? 'primary' : 'outline-primary'; ?> btn-sm">
                                    <?php echo htmlspecialchars($tag); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-3 text-muted small">
                                <i class="fas fa-inbox mb-1"></i>
                                <p class="mb-0">暂无标签</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-9 mb-4 d-flex flex-column">
                    <!-- 图片列表 -->
                    <div class="card flex-fill d-flex flex-column">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-images me-2"></i>图片管理</h5>
                            <div>
                                <span class="small">
                                    <?php if ($selectedTag && $selectedTag !== 'all'): ?>
                                        筛选: <strong><?php echo htmlspecialchars($selectedTag); ?></strong>
                                        <a href="?tag=all" class="btn btn-sm btn-outline-primary ms-1 py-0">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php else: ?>
                                        共 <strong><?php echo $totalImages; ?></strong> 张
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                <div class="card-body flex-fill overflow-auto">
                    <?php if (empty($images)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox display-1 text-muted"></i>
                            <p class="text-muted mt-3">
                                <?php if ($selectedTag && $selectedTag !== 'all'): ?>
                                    没有找到标签为 "<?php echo htmlspecialchars($selectedTag); ?>" 的图片
                                <?php else: ?>
                                    暂无图片
                                <?php endif; ?>
                            </p>
                            <?php if ($selectedTag && $selectedTag !== 'all'): ?>
                                <a href="?tag=all" class="btn btn-primary mt-3">
                                    <i class="fas fa-arrow-left me-2"></i>返回全部图片
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row" id="imageGridView">
                            <?php foreach ($images as $image): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 image-card">
                                        <img src="<?php echo $image['thumb_url'] ?: $image['url']; ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                        <div class="card-body py-2 px-3">
                                            <h6 class="card-title fw-bold small mb-1"><?php echo htmlspecialchars($image['original_name']); ?></h6>
                                            
                                            <div class="mb-1">
                                                <?php if (!empty($image['tags'])): ?>
                                                    <?php 
                                                    $tagsArray = explode(',', $image['tags']);
                                                    foreach ($tagsArray as $tag): 
                                                        $tag = trim($tag);
                                                        if (!empty($tag)):
                                                    ?>
                                                        <span class="badge bg-secondary me-1" style="font-size:0.7rem;"><?php echo htmlspecialchars($tag); ?></span>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                <?php endif; ?>
                                                <span class="badge bg-<?php echo $image['storage_type'] === 'github' ? 'success' : ($image['storage_type'] === 'webdav' ? 'info' : ($image['storage_type'] === 'telegram' ? 'danger' : 'warning')); ?>" style="font-size:0.65rem;">
                                                    <?php echo $image['storage_type'] === 'github' ? 'GitHub' : ($image['storage_type'] === 'webdav' ? 'WebDAV' : ($image['storage_type'] === 'telegram' ? 'Telegram' : '本地')); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="small text-muted" style="font-size:0.75rem;">
                                                <div><i class="fas fa-weight me-1"></i><?php echo formatFileSize($image['file_size']); ?> · <?php echo $image['mime_type']; ?></div>
                                                <div><i class="fas fa-clock me-1"></i><?php echo date('Y-m-d H:i', strtotime($image['upload_time'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent border-top-0 py-2 px-3">
                                            <div class="btn-group w-100">
                                                <button class="btn btn-outline-primary btn-sm copy-url" 
                                                        data-url="<?php echo $image['url']; ?>">
                                                    <i class="fas fa-clipboard"></i>
                                                </button>
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="window.open('<?php echo $image['url']; ?>', '_blank')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="submit" name="delete_image" class="btn btn-outline-danger btn-sm"
                                                        form="deleteForm_<?php echo $image['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <form id="deleteForm_<?php echo $image['id']; ?>" method="POST" class="d-none" onsubmit="return confirm('确定删除这张图片吗？')">
                                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="imageListView" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="small">
                                        <tr>
                                            <th style="width:60px">预览</th>
                                            <th>文件名</th>
                                            <th>标签</th>
                                            <th>大小</th>
                                            <th>上传时间</th>
                                            <th style="width:140px">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php foreach ($images as $image): ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo $image['thumb_url'] ?: ($image['url'] ?? $image['github_url'] ?? $image['local_path']); ?>" 
                                                         width="50" height="50" style="object-fit: cover; border-radius: 6px;" class="image-preview">
                                                </td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($image['original_name']); ?></td>
                                                <td>
                                                    <?php if (!empty($image['tags'])): ?>
                                                        <?php 
                                                        $tagsArray = explode(',', $image['tags']);
                                                        foreach ($tagsArray as $tag): 
                                                            $tag = trim($tag);
                                                            if (!empty($tag)):
                                                        ?>
                                                            <span class="badge bg-secondary me-1" style="font-size:0.65rem;"><?php echo htmlspecialchars($tag); ?></span>
                                                        <?php 
                                                            endif;
                                                        endforeach; 
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="text-muted small">无标签</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatFileSize($image['file_size']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($image['upload_time'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary copy-url" 
                                                                data-url="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>">
                                                            <i class="fas fa-clipboard"></i>
                                                        </button>
                                                        <a href="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>" 
                                                           class="btn btn-outline-info btn-sm" target="_blank">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('确定删除这张图片吗？')">
                                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                            <button type="submit" name="delete_image" class="btn btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-4 d-flex justify-content-center">
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $selectedTag ? '&tag=' . urlencode($selectedTag) : ''; ?>">上一页</a>
                    </li>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $selectedTag ? '&tag=' . urlencode($selectedTag) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $selectedTag ? '&tag=' . urlencode($selectedTag) : ''; ?>">下一页</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
                </div>
            </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
