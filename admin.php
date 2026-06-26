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

// 统计信息 — 只用两个简单 SQL
$db = getDBConnection();
$stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM images");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalImages = (int)$stats['count'];
$totalSize = (int)$stats['total_size'];

// 标签 — 直接 SQL 去重
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

// 图片数据
$selectedTag = $_GET['tag'] ?? '';
$totalPages = 1;

if ($selectedTag && $selectedTag !== 'all') {
    $images = getImages();
    $filtered = [];
    foreach ($images as $image) {
        if (!empty($image['tags'])) {
            $tagArr = array_map('trim', explode(',', $image['tags']));
            if (in_array($selectedTag, $tagArr)) {
                $filtered[] = $image;
            }
        }
    }
    $images = $filtered;
} else {
    $totalPages = max(1, ceil($totalImages / $perPage));
    $images = getImages($perPage, $offset);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员后台 - PicHost</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="shortcut icon" href="favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body<?php if (!empty($settings['bg_image']) && file_exists('assets/img/' . $settings['bg_image'])): ?> style="background-image: linear-gradient(135deg, rgba(14, 165, 233, 0.8), rgba(59, 130, 246, 0.8)), url('assets/img/<?php echo $settings['bg_image']; ?>') !important;"<?php endif; ?>>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-images"></i> 图床管理后台
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">返回前台</a>
                <a class="nav-link" href="admin_settings.php">系统设置</a>
                <a class="nav-link" href="admin.php?action=logout">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-3 col-md-4 mb-4">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>统计信息</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>图片总数:</span>
                            <span class="badge bg-primary"><?php echo $totalImages; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>总大小:</span>
                            <span class="badge bg-info"><?php echo formatFileSize($totalSize); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                        <span>当前存储方式:</span>
                        <span class="badge bg-secondary">
                            <?php 
                            $defaultStorage = $settings['default_storage'] ?? 'local';
                            $storageLabels = [
                                'local' => '本地存储',
                                'github' => 'GitHub存储',
                                'webdav' => 'WebDAV存储'
                            ];
                            echo $storageLabels[$defaultStorage] ?? '本地存储';
                            ?>
                        </span>
                    </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt me-2"></i>快速操作</h5>
                    </div>
                    <div class="card-body">
                        <a href="index.php" class="btn btn-outline-primary w-100 mb-3">
                            <i class="fas fa-arrow-left me-2"></i> 返回前台
                        </a>
                        <button class="btn btn-outline-danger w-100" onclick="clearAllImages()">
                            <i class="fas fa-trash me-2"></i> 清空所有图片
                        </button>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-cloud-upload-alt me-2"></i>批量上传</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="batchTags" placeholder="标签（可选，所有图片共用）">
                        </div>
                        <input type="file" class="d-none" id="batchFileInput" accept="image/*" multiple>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm flex-fill" onclick="document.getElementById('batchFileInput').click()">
                                <i class="fas fa-images me-1"></i> <span id="batchFileLabel">选择图片</span>
                            </button>
                            <button class="btn btn-primary btn-sm flex-fill" id="batchUploadBtn" onclick="startBatchUpload()" disabled>
                                <i class="fas fa-upload me-1"></i> 开始上传
                            </button>
                        </div>
                        <div id="batchProgress" style="display: none;" class="mt-2">
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
            </div>

            <div class="col-lg-9 col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-images me-2"></i>图片管理</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="toggleView('grid')">
                                <i class="fas fa-th-large"></i> 网格
                            </button>
                            <button class="btn btn-outline-primary" onclick="toggleView('list')">
                                <i class="fas fa-list"></i> 列表
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- 标签筛选控件 -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                    <div class="d-flex align-items-center mb-2">
                                        <label class="form-label mb-0 me-3 fw-bold">
                                            <i class="fas fa-filter me-2"></i>标签筛选:
                                        </label>
                                        <div class="btn-group flex-wrap">
                                            <a href="?tag=all" class="btn btn-<?php echo empty($selectedTag) || $selectedTag === 'all' ? 'primary' : 'outline-primary'; ?> mb-1">
                                                <i class="fas fa-images me-1"></i>全部图片
                                            </a>
                                            <?php if (!empty($allTags)): ?>
                                                <?php foreach ($allTags as $tag): ?>
                                                    <a href="?tag=<?php echo urlencode($tag); ?>" class="btn btn-<?php echo $selectedTag === $tag ? 'primary' : 'outline-primary'; ?> mb-1">
                                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($tag); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-muted mb-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <?php if ($selectedTag && $selectedTag !== 'all'): ?>
                                            筛选结果: <span class="fw-bold text-primary"><?php echo count($images); ?></span> 张图片
                                        <?php else: ?>
                                            总计: <span class="fw-bold text-primary"><?php echo count($images); ?></span> 张图片
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($selectedTag && $selectedTag !== 'all'): ?>
                                    <div class="mt-2">
                                        <div class="alert alert-info d-inline-flex align-items-center py-2">
                                            <i class="fas fa-filter me-2"></i>
                                            <span>当前筛选: <strong class="text-primary"><?php echo htmlspecialchars($selectedTag); ?></strong></span>
                                            <a href="?tag=all" class="btn btn-sm btn-outline-info ms-3">
                                                <i class="fas fa-times me-1"></i>清除筛选
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

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
                                            <img src="<?php echo $image['url']; ?>" 
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
                                                    <span class="badge bg-<?php echo $image['storage_type'] === 'github' ? 'success' : ($image['storage_type'] === 'webdav' ? 'info' : 'warning'); ?>" style="font-size:0.65rem;">
                                                        <?php echo $image['storage_type'] === 'github' ? 'GitHub' : ($image['storage_type'] === 'webdav' ? 'WebDAV' : '本地'); ?>
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
                                                        <img src="<?php echo $image['url']; ?>" 
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
                    </div>
                </div>

                <?php if ($totalPages > 1 && (!$selectedTag || $selectedTag === 'all')): ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>