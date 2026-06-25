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
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的操作']);
}

function clearAllImages() {
    $db = getDBConnection();
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->query("SELECT * FROM images");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $image) {
            if ($image['storage_type'] === 'local' && file_exists($image['local_path'])) {
                unlink($image['local_path']);
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
?>