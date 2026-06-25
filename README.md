# PicHost - PHP 图片托管系统

轻量级图片托管系统，支持本地 / GitHub / WebDAV 三种存储方式，拖拽上传，后台管理，一键复制链接。

## 功能

- **多存储** — 本地、GitHub、WebDAV 任意切换，失败自动回退
- **拖拽上传** — 拖放图片即传，支持 JPG/PNG/GIF/WebP
- **标签分类** — 上传时打标签，后台按标签筛选
- **管理后台** — 网格/列表双视图，一键复制链接，批量清空
- **登录管控** — 可选「仅管理员可上传」，防滥用
- **配置加密** — GitHub Token、WebDAV 密码 AES-256-CBC 加密存储
- **安装向导** — 浏览器上手配置，无需手动改文件

## 快速开始

### 环境要求

- PHP ≥ 7.4
- MySQL / MariaDB
- 扩展：`pdo_mysql`、`curl`、`fileinfo`、`json`、`mbstring`、`openssl`

### 安装

1. 将项目文件放到 Web 服务器目录
2. 浏览器访问 `install.php`
3. 按向导填写数据库信息、管理员账号、基础 URL
4. 安装完成，访问 `index.php` 开始使用

### 基础 URL

基础 URL 在安装时自动检测当前域名并填入，也支持手动修改。用于生成图片的完整访问链接。

## 存储配置

### 本地存储

默认方式，图片保存在 `uploads/` 目录。

### GitHub 存储

1. 在 GitHub 生成 Personal Access Token（勾选 `repo` 权限）
2. 在后台 → 系统设置 → GitHub 配置中填入 Token、仓库所有者、仓库名
3. 存储路径默认为 `images/`，图片会上传到仓库的该目录下

### WebDAV 存储

1. 在后台 → 系统设置 → WebDAV 配置中填入服务器地址、用户名、密码
2. 图片通过代理脚本 `proxy.php` 访问，不暴露认证信息

## 目录结构

```
PicHost/
├── config/
│   ├── config.php         # 数据库配置、加密密钥
│   ├── database.php       # 数据库连接与初始化
│   └── settings.json      # 系统设置（加密存储）
├── includes/
│   └── functions.php      # 核心函数
├── assets/
│   ├── css/               # 样式表
│   └── js/                # JavaScript
├── uploads/               # 本地图片存储目录
├── index.php              # 前台首页
├── upload.php             # 上传接口
├── admin.php              # 管理后台
├── admin_login.php        # 管理员登录
├── admin_settings.php     # 系统设置
├── admin_actions.php      # 后台 AJAX 操作
├── install.php            # 安装向导
├── proxy.php              # WebDAV 图片代理
└── .htaccess              # 安全配置
```

## 安全特性

- 敏感配置（GitHub Token、WebDAV 密码）使用 AES-256-CBC 加密后存储
- 加密密钥通过 `CONFIG_KEY` 常量定义，支持环境变量 `PICHOST_KEY`
- `config/` 目录受 `.htaccess` 保护，禁止直接 HTTP 访问
- 支持登录上传管控，防止匿名滥用
- 图片上传仅允许合法图片 MIME 类型

## License

MIT
