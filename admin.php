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

$action = $_GET['action'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_image'])) {
        $imageId = $_POST['image_id'];
        if (deleteImage($imageId)) {
            $message = '图片删除成功';
        } else {
            $message = '图片删除失败';
        }
    }
}

// 获取所有图片
$images = getImages();

// 获取所有标签
$allTags = [];
foreach ($images as $image) {
    if (!empty($image['tags'])) {
        $tagsArray = explode(',', $image['tags']);
        foreach ($tagsArray as $tag) {
            $tag = trim($tag);
            if (!empty($tag) && !in_array($tag, $allTags)) {
                $allTags[] = $tag;
            }
        }
    }
}
sort($allTags);

// 处理标签筛选
$selectedTag = $_GET['tag'] ?? '';
if ($selectedTag && $selectedTag !== 'all') {
    $filteredImages = [];
    foreach ($images as $image) {
        if (!empty($image['tags'])) {
            $tagsArray = explode(',', $image['tags']);
            $tagsArray = array_map('trim', $tagsArray);
            if (in_array($selectedTag, $tagsArray)) {
                $filteredImages[] = $image;
            }
        }
    }
    $images = $filteredImages;
}

$totalImages = count($images);
$totalSize = 0;
foreach ($images as $image) {
    $totalSize += $image['file_size'];
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
<body>
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
                            <span>存储类型:</span>
                            <span class="badge bg-secondary">本地 + GitHub</span>
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
            </div>

            <div class="col-lg-9 col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-images me-2"></i>图片管理</h5>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" onclick="toggleView('grid')">
                                <i class="fas fa-th-large me-1"></i> 网格
                            </button>
                            <button class="btn btn-outline-primary" onclick="toggleView('list')">
                                <i class="fas fa-list me-1"></i> 列表
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
                                            <img src="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($image['description']); ?>"
                                                 style="height: 200px; object-fit: cover;">
                                            <div class="card-body">
                                                <h6 class="card-title fw-bold"><?php echo htmlspecialchars($image['original_name']); ?></h6>
                                                
                                                <!-- 标签显示 -->
                                                <div class="mb-3">
                                                    <?php if (!empty($image['tags'])): ?>
                                                        <?php 
                                                        $tagsArray = explode(',', $image['tags']);
                                                        foreach ($tagsArray as $tag): 
                                                            $tag = trim($tag);
                                                            if (!empty($tag)):
                                                        ?>
                                                            <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                                                        <?php 
                                                            endif;
                                                        endforeach; 
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="text-muted small">无标签</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="small text-muted">
                                                    <div class="mb-1"><i class="fas fa-weight me-1"></i>大小: <?php echo formatFileSize($image['file_size']); ?></div>
                                                    <div class="mb-1"><i class="fas fa-file-image me-1"></i>类型: <?php echo $image['mime_type']; ?></div>
                                                    <div class="mb-1"><i class="fas fa-clock me-1"></i>时间: <?php echo date('Y-m-d H:i', strtotime($image['upload_time'])); ?></div>
                                                    <div><i class="fas fa-database me-1"></i>存储: <span class="badge bg-<?php echo $image['storage_type'] === 'github' ? 'success' : 'warning'; ?>">
                                                        <?php echo $image['storage_type']; ?>
                                                    </span></div>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <div class="btn-group w-100">
                                                    <button class="btn btn-outline-primary copy-url" 
                                                            data-url="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>">
                                                        <i class="fas fa-clipboard"></i>
                                                    </button>
                                                    <a href="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>" 
                                                       class="btn btn-outline-info" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定删除这张图片吗？')">
                                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                        <button type="submit" name="delete_image" class="btn btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="imageListView" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>预览</th>
                                                <th>文件名</th>
                                                <th>标签</th>
                                                <th>大小</th>
                                                <th>类型</th>
                                                <th>上传时间</th>
                                                <th>存储</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($images as $image): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>" 
                                                             width="60" height="60" style="object-fit: cover; border-radius: 8px;" class="image-preview">
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
                                                                <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                                                            <?php 
                                                                endif;
                                                            endforeach; 
                                                            ?>
                                                        <?php else: ?>
                                                            <span class="text-muted small">无标签</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatFileSize($image['file_size']); ?></td>
                                                    <td><?php echo $image['mime_type']; ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($image['upload_time'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $image['storage_type'] === 'github' ? 'success' : 'warning'; ?>">
                                                            <?php echo $image['storage_type']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button class="btn btn-outline-primary copy-url" 
                                                                    data-url="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>">
                                                                <i class="fas fa-clipboard"></i>
                                                            </button>
                                                            <a href="<?php echo $image['url'] ?? $image['github_url'] ?? $image['local_path']; ?>" 
                                                               class="btn btn-outline-info" target="_blank">
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>