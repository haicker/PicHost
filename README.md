<p align="center">
  <img src="https://img.shields.io/badge/PHP-≥7.4-777BB4?style=flat-square&logo=php" />
  <img src="https://img.shields.io/badge/MySQL-✓-4479A1?style=flat-square&logo=mysql" />
  <img src="https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat-square&logo=bootstrap" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" />
</p>

<h1 align="center">PicHost</h1>

<p align="center">轻量级 PHP 图片托管系统 — 多端存储 · 标签管理 · 拖拽上传</p>

---

## ✨ 功能特性

### 多存储后端

| 存储 | 说明 |
|------|------|
| 本地 | 默认方式，文件存储于 `uploads/` |
| GitHub | 通过 Contents API 上传至指定仓库路径 |
| WebDAV | 通过 WebDAV 协议上传，支持 Basic Auth |
| Telegram | 通过 Bot API 上传至指定频道/群组 |

支持在后台自由切换当前存储方式。

### 图片管理

- **批量上传** — 多文件选择，共享标签，逐张进度显示
- **双视图** — 网格 / 列表视图切换
- **标签系统** — 为每张图片打标签，按标签筛选
  - 点击加号时展示已有标签候选下拉
  - 全局批量重命名标签
  - 全局删除标签
- **一键复制** — 每张图片独立复制链接按钮
- **图片预览** — 点击缩略图弹出大图模态框

### 安全

- GitHub Token、WebDAV 密码使用 **AES-256-CBC** 加密存储
- `config/` 目录受 `.htaccess` 保护，禁止 HTTP 直接访问
- 支持登录管控（仅管理员可上传）
- 图片 MIME 类型严格校验

### 代理访问

`proxy.php` 为 WebDAV / Telegram 存储提供统一访问入口，凭据仅保存在服务端，对外隐藏真实存储 URL。

---

## 📋 环境要求

- PHP ≥ 7.4
- MySQL / MariaDB
- PHP 扩展：`pdo_mysql`、`curl`、`fileinfo`、`json`、`mbstring`、`openssl`

---

## 🚀 安装

1. 将项目文件部署到 Web 服务器目录
2. 浏览器访问 `install.php`
3. 按向导填写数据库信息、管理员账号、基础 URL
4. 安装完成后访问 `index.php` 开始使用

---

## 🖼️ 支持的图片格式

`jpg` · `jpeg` · `png` · `gif` · `webp`

---

## 📄 License

MIT
