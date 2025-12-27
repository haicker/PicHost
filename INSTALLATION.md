# 安装部署指南

## 📋 前置要求

### 系统要求

**操作系统**
- Linux (Ubuntu/CentOS/Debian)
- Windows Server 2012+
- macOS 10.14+

**Web服务器**
- Apache 2.4+ (推荐)
- Nginx 1.18+
- IIS 8+ (Windows)

**PHP要求**
- PHP 7.4 或更高版本
- 必需扩展：
  - PDO MySQL
  - GD Library
  - JSON
  - cURL (GitHub存储功能)
  - Fileinfo

**数据库**
- MySQL 5.7+ 或 MariaDB 10.3+
- 至少100MB可用空间

### 权限要求

**文件权限**
```bash
# uploads目录需要写入权限
chmod 755 uploads/
chown www-data:www-data uploads/  # Linux

# config目录需要读取权限
chmod 644 config/
```

**PHP配置**
```ini
; php.ini 配置要求
file_uploads = On
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 20
memory_limit = 128M
```

## 🚀 快速安装

### 方法一：自动安装（推荐）

1. **下载项目文件**
   ```bash
   # 从GitHub下载
   wget https://github.com/your-repo/php-image-host/archive/main.zip
   unzip main.zip
   cd php-image-host-main
   
   # 或者使用Git克隆
   git clone https://github.com/your-repo/php-image-host.git
   cd php-image-host
   ```

2. **设置Web目录**
   ```bash
   # 将项目文件移动到Web目录
   sudo cp -r . /var/www/html/image-host/
   sudo chown -R www-data:www-data /var/www/html/image-host/
   ```

3. **运行安装向导**
   - 打开浏览器访问：`http://your-domain.com/image-host/install.php`
   - 按照界面提示完成安装

### 方法二：手动安装

1. **创建数据库**
   ```sql
   CREATE DATABASE image_hosting;
   CREATE USER 'image_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON image_hosting.* TO 'image_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **导入数据库结构**
   ```sql
   USE image_hosting;
   
   CREATE TABLE images (
       id INT AUTO_INCREMENT PRIMARY KEY,
       filename VARCHAR(255) NOT NULL,
       original_name VARCHAR(255) NOT NULL,
       tags TEXT,
       file_size INT,
       mime_type VARCHAR(100),
       github_url VARCHAR(500),
       local_path VARCHAR(500),
       storage_type ENUM('local', 'github'),
       upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

3. **配置数据库连接**
   ```bash
   # 编辑 config/config.php
   nano config/config.php
   ```
   
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'image_user');
   define('DB_PASS', 'secure_password');
   define('DB_NAME', 'image_hosting');
   ```

4. **创建安装锁文件**
   ```bash
   touch .installed
   chmod 644 .installed
   ```

## ⚙️ 详细配置

### Web服务器配置

#### Apache配置 (.htaccess)

项目根目录已包含 `.htaccess` 文件：

```apache
RewriteEngine On

# 强制HTTPS（可选）
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# 隐藏PHP文件扩展名
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}\.php -f
RewriteRule ^(.*)$ $1.php [L]

# 保护敏感文件
<Files "config/*">
    Order allow,deny
    Deny from all
</Files>

<Files "*.sql">
    Order allow,deny
    Deny from all
</Files>
```

#### Nginx配置

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/image-host;
    index index.php;

    # 图片文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # PHP处理
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 保护敏感目录
    location ~ /(\.ht|config|includes) {
        deny all;
    }
}
```

### 安全配置

#### 修改默认管理员密码

安装后立即修改默认管理员账号：

1. 登录后台管理 (`/admin.php`)
2. 用户名：`admin`，密码：`admin123`
3. 进入系统设置修改密码

#### 文件权限设置

```bash
# 设置正确的文件权限
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# uploads目录需要写入权限
chmod 755 uploads/
chown www-data:www-data uploads/ -R

# 配置文件只读
chmod 600 config/config.php
```

#### 启用HTTPS

**使用Let's Encrypt免费证书**

```bash
# 安装Certbot
sudo apt install certbot python3-certbot-apache

# 获取证书
sudo certbot --apache -d your-domain.com

# 自动续期测试
sudo certbot renew --dry-run
```

### 性能优化配置

#### PHP优化

编辑 `php.ini`：

```ini
; 内存和性能优化
memory_limit = 256M
max_execution_time = 120
max_input_time = 120

; 文件上传优化
upload_max_filesize = 20M
post_max_size = 22M
max_file_uploads = 50

; OPcache启用
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

#### MySQL优化

编辑 `my.cnf`：

```ini
[mysqld]
# 内存配置
innodb_buffer_pool_size = 256M
key_buffer_size = 64M

# 连接配置
max_connections = 100
thread_cache_size = 8

# 查询缓存
query_cache_type = 1
query_cache_size = 64M
```

## 🔧 高级配置

### GitHub存储配置（可选）

1. **创建GitHub个人访问令牌**
   - 访问 GitHub Settings → Developer settings → Personal access tokens
   - 生成新令牌，勾选 `repo` 权限

2. **配置GitHub存储**
   ```php
   // 在 config/config.php 中添加
   define('GITHUB_TOKEN', 'your_personal_access_token');
   define('GITHUB_REPO_OWNER', 'your_username');
   define('GITHUB_REPO_NAME', 'your_repo_name');
   define('GITHUB_REPO_PATH', 'images');
   ```

3. **启用GitHub存储**
   - 在后台管理 → 系统设置中启用GitHub存储

### 自定义域名配置

1. **修改基础URL**
   ```php
   // config/config.php
   define('BASE_URL', 'https://your-custom-domain.com');
   ```

2. **配置CDN（可选）**
   - 使用Cloudflare或其他CDN服务
   - 配置缓存规则和SSL证书

### 邮件通知配置

如需启用邮件通知功能：

```php
// 在 config/config.php 中添加
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@your-domain.com');
```

## 🚨 故障排除

### 常见问题

**1. 上传失败**
```
问题：文件上传失败，提示权限错误
解决：检查uploads目录权限
命令：chmod 755 uploads/ && chown www-data:www-data uploads/
```

**2. 数据库连接错误**
```
问题：无法连接到数据库
解决：检查数据库配置和连接信息
检查：DB_HOST, DB_USER, DB_PASS, DB_NAME是否正确
```

**3. 图片无法显示**
```
问题：上传的图片无法显示
解决：检查BASE_URL配置和文件路径
检查：确保BASE_URL指向正确的域名
```

**4. 后台登录失败**
```
问题：管理员无法登录
解决：重置管理员密码或检查会话配置
方法：删除config/settings.json文件重新配置
```

### 日志查看

**错误日志位置**
```bash
# Apache错误日志
tail -f /var/log/apache2/error.log

# PHP错误日志
tail -f /var/log/php7.4-fpm.log

# 应用日志（如果启用）
tail -f logs/application.log
```

### 性能监控

**系统资源监控**
```bash
# 查看内存使用
free -h

# 查看磁盘空间
df -h

# 查看进程占用
top -p $(pgrep php-fpm)
```

## 🔄 升级指南

### 备份重要数据

1. **备份数据库**
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
   ```

2. **备份上传文件**
   ```bash
   tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/
   ```

3. **备份配置文件**
   ```bash
   cp config/config.php config/config.php.backup
   cp config/settings.json config/settings.json.backup
   ```

### 执行升级

1. **下载新版本**
   ```bash
   # 备份当前版本
   cp -r image-host image-host-backup
   
   # 下载新版本
   wget https://github.com/your-repo/php-image-host/archive/v2.0.zip
   unzip v2.0.zip
   ```

2. **合并配置文件**
   ```bash
   # 保留自定义配置
   cp image-host-backup/config/config.php php-image-host-2.0/config/
   cp image-host-backup/config/settings.json php-image-host-2.0/config/
   ```

3. **替换文件**
   ```bash
   # 替换文件（保留uploads目录）
   rsync -av --exclude='uploads' --exclude='config' php-image-host-2.0/ image-host/
   ```

## 📊 维护计划

### 日常维护

- [ ] 检查系统日志
- [ ] 监控磁盘空间
- [ ] 验证备份完整性
- [ ] 更新系统安全补丁

### 月度维护

- [ ] 清理过期日志
- [ ] 优化数据库表
- [ ] 检查文件权限
- [ ] 更新依赖库

### 年度维护

- [ ] 全面安全审计
- [ ] 性能基准测试
- [ ] 备份策略评估
- [ ] 灾难恢复演练

---

**文档版本**: v1.0  
**最后更新**: 2025-12-27  
**适用版本**: PicHost v1.0+