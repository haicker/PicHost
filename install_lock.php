<?php
/**
 * 安装锁定文件
 * 安装完成后创建此文件，防止重复安装
 */

function isInstalled() {
    return file_exists(__DIR__ . '/.installed');
}

function markAsInstalled() {
    file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s') . " - 安装完成\n");
}

function checkInstallation() {
    // 如果已经安装完成，重定向到首页
    if (isInstalled() && basename($_SERVER['PHP_SELF']) === 'install.php') {
        header('Location: index.php');
        exit;
    }
    
    // 如果未安装但访问其他页面，重定向到安装页面
    if (!isInstalled() && basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
}

// 自动检查安装状态
checkInstallation();
?>