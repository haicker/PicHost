# PicHost

一个基于PHP开发的现代化图片托管服务，提供简单、快速、安全的图片上传和管理功能，支持本地存储和GitHub存储，可以简单部署在虚拟主机上。

## 🌟 项目简介

PicHost是一个轻量级的图片托管解决方案，具有以下特点：

- **现代化界面**：采用Bootstrap 5 + 自定义CSS设计，支持响应式布局
- **多种存储方式**：支持本地存储和GitHub存储
- **标签管理**：智能标签分类和筛选功能
- **安全控制**：可配置的游客上传权限
- **高性能**：优化的图片处理和缓存机制

## ✨ 主要功能

### 前端功能
- 🖼️ 拖拽上传图片
- 📱 响应式设计，支持移动端
- 🎨 现代化UI设计（渐变背景、玻璃拟态效果）
- 🔒 可选的登录上传保护
- 📊 实时上传进度显示

### 后台管理
- 👥 管理员登录系统
- 🏷️ 标签筛选管理
- 📋 图片列表管理
- ⚙️ 系统设置配置
- 🗑️ 批量删除功能

### 技术特性
- 🚀 PHP 7.4+ 支持
- 💾 MySQL数据库存储
- 🔒 安全的文件上传验证
- 📁 多存储后端支持
- 🎯 RESTful API设计

## 🛠️ 技术栈

### 后端技术
- **PHP 7.4+** - 服务器端脚本语言
- **MySQL** - 数据库存储
- **PDO** - 数据库连接
- **JSON** - 配置文件存储

### 前端技术
- **HTML5** - 页面结构
- **CSS3** - 样式设计（渐变、玻璃拟态）
- **Bootstrap 5** - UI框架
- **JavaScript** - 交互功能
- **Font Awesome** - 图标库

### 第三方服务
- **GitHub API** - 图片存储（可选）
- **CDN资源** - Bootstrap、Font Awesome

## 📁 项目结构

```
img/
├── assets/                 # 静态资源
│   ├── css/               # 样式文件
│   │   ├── style.css      # 首页样式
│   │   └── admin.css      # 后台样式
│   ├── js/                # JavaScript文件
│   │   ├── script.js      # 首页脚本
│   │   └── admin.js       # 后台脚本
│   └── img/               # 图片资源
│       └── bg.jpg         # 背景图片
├── config/                # 配置文件
│   ├── config.php         # 基础配置
│   └── database.php       # 数据库配置
├── includes/              # 包含文件
│   └── functions.php      # 功能函数
├── uploads/               # 上传目录（自动创建）
├── index.php              # 首页
├── admin.php              # 后台管理
├── admin_login.php        # 管理员登录
├── admin_settings.php     # 系统设置
├── admin_actions.php      # 管理操作
├── upload.php             # 上传处理
├── install.php            # 安装向导
└── favicon.svg            # 网站图标
```

## 🚀 快速开始

### 环境要求
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Apache/Nginx Web服务器
- 启用文件上传功能

### 安装步骤

1. **下载项目**
   ```bash
   git clone [项目地址]
   cd img
   ```

2. **配置Web服务器**
   - 将项目目录设置为Web根目录
   - 确保uploads目录有写入权限

3. **运行安装向导**
   - 访问 `http://your-domain.com/install.php`
   - 按照提示完成数据库配置

4. **开始使用**
   - 访问首页开始上传图片
   - 使用默认管理员账号登录后台


## ⚙️ 配置说明

### 主要配置项

在 `config/config.php` 中可配置：

```php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'image_hosting');

// 上传限制
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// 管理员账号
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// 网站基础URL
define('BASE_URL', 'http://your-domain.com');
```

### 系统设置

在后台管理中可以配置：
- 是否要求登录才能上传
- GitHub存储配置（可选）
- 其他系统参数

## 🔧 功能详解

### 图片上传
- 支持多种图片格式（JPG、PNG、GIF、WebP）
- 最大文件大小限制可配置
- 支持拖拽上传和传统文件选择
- 自动生成缩略图和预览

### 标签系统
- 自动提取图片标签
- 智能标签分类管理
- 基于标签的快速筛选
- 标签云显示功能

### 存储后端

#### 本地存储
- 图片存储在服务器本地
- 支持自定义存储路径
- 简单的文件管理

#### GitHub存储（可选）
- 将图片上传到GitHub仓库
- 利用GitHub的CDN加速
- 支持私有仓库

### 安全特性
- 文件类型和大小验证
- 防恶意文件上传
- 管理员权限控制
- 会话安全管理

## 📈 性能优化

- 数据库查询优化
- 图片缓存机制
- 前端资源压缩
- CDN资源利用

## 📄 许可证

本项目采用MIT许可证，详见LICENSE文件。
