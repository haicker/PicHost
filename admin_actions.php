<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'clear_all':
        clearAllImages();
        break;
    case 'logout':
        adminLogout();
        echo json_encode(['success' => true, 'message' => '已退出登录']);
        break;
    case 'update_tags':
        updateImageTags();
        break;
    case 'rename_tag':
        renameTag();
        break;
    case 'delete_tag':
        deleteTag();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的操作']);
}

function clearAllImages() {
    $db = getDBConnection();
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->query("SELECT id, local_path, storage_type, filename FROM images");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $image) {
            if ($image['storage_type'] === 'local' && file_exists($image['local_path'])) {
                unlink($image['local_path']);
            }
            $thumbPath = getThumbPath($image['filename']);
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        
        $db->exec("DELETE FROM images");
        $db->commit();

        echo json_encode(['success' => true, 'message' => '所有图片已清空']);

    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '清空失败: ' . $e->getMessage()]);
    }
}

function updateImageTags() {
    $imageId = (int)($_POST['image_id'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');

    if ($imageId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的图片ID']);
        return;
    }

    $tagsArray = array_filter(array_map('trim', explode(',', $tags)));
    $tagsString = implode(',', $tagsArray);

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE images SET tags = ? WHERE id = ?");
        $stmt->execute([$tagsString ?: null, $imageId]);

        if ($stmt->rowCount() > 0 || $stmt->rowCount() === 0) {
            echo json_encode([
                'success' => true,
                'message' => '标签已更新',
                'tags' => $tagsArray
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '图片不存在']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '更新失败: ' . $e->getMessage()]);
    }
}

function renameTag() {
    $oldTag = trim($_POST['old_tag'] ?? '');
    $newTag = trim($_POST['new_tag'] ?? '');

    if ($oldTag === '' || $newTag === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '标签名称不能为空']);
        return;
    }

    if ($oldTag === $newTag) {
        echo json_encode(['success' => true, 'message' => '标签名称未变化']);
        return;
    }

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, tags FROM images WHERE FIND_IN_SET(?, tags)");
        $stmt->execute([$oldTag]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($images)) {
            echo json_encode(['success' => false, 'message' => '未找到包含该标签的图片']);
            return;
        }

        $updateStmt = $db->prepare("UPDATE images SET tags = ? WHERE id = ?");
        $affected = 0;

        foreach ($images as $image) {
            $tagsArray = array_map('trim', explode(',', $image['tags']));
            $tagsArray = array_filter($tagsArray, function($t) use ($oldTag) {
                return $t !== $oldTag;
            });
            $tagsArray[] = $newTag;
            $tagsArray = array_unique($tagsArray);
            sort($tagsArray);
            $newTagsString = implode(',', $tagsArray);
            $updateStmt->execute([$newTagsString, $image['id']]);
            $affected++;
        }

        echo json_encode(['success' => true, 'message' => "已重命名 {$affected} 张图片的标签"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '重命名失败: ' . $e->getMessage()]);
    }
}

function deleteTag() {
    $tag = trim($_POST['tag'] ?? '');

    if ($tag === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '标签名称不能为空']);
        return;
    }

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, tags FROM images WHERE FIND_IN_SET(?, tags)");
        $stmt->execute([$tag]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($images)) {
            echo json_encode(['success' => false, 'message' => '未找到包含该标签的图片']);
            return;
        }

        $updateStmt = $db->prepare("UPDATE images SET tags = ? WHERE id = ?");
        $affected = 0;

        foreach ($images as $image) {
            $tagsArray = array_map('trim', explode(',', $image['tags']));
            $tagsArray = array_filter($tagsArray, function($t) use ($tag) {
                return $t !== $tag;
            });
            $newTagsString = !empty($tagsArray) ? implode(',', array_values($tagsArray)) : null;
            $updateStmt->execute([$newTagsString, $image['id']]);
            $affected++;
        }

        echo json_encode(['success' => true, 'message' => "已从 {$affected} 张图片中删除标签"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
    }
}
?>