# 校园匿名墙 (RUC Confession Wall)

基于 Twitter 风格和中国人民大学红白配色的校园匿名墙应用。

## 项目概述

一个现代化的校园匿名社交平台，支持匿名发布表白、秘密和提问，提供完整的用户认证、内容审核、点赞评论、私信通知等功能。

## 技术栈

- **前端**: HTML5 + CSS3 + JavaScript (ES6+ 原生实现，无框架依赖)
- **后端**: PHP + SQLite
- **架构**: 前后端分离，通过 RESTful API 通信
- **认证**: JWT (JSON Web Token)

## 目录结构

```
Confession-Wall/
├── public/                 # 前端静态文件
│   ├── index.html          # 主页/时间线
│   ├── login.html           # 登录页
│   ├── register.html        # 注册页
│   ├── explore.html         # 发现页
│   ├── notifications.html   # 通知页
│   ├── messages.html        # 私信页
│   ├── profile.html         # 个人主页
│   ├── settings.html        # 设置页
│   ├── css/
│   │   └── style.css        # 全部样式
│   ├── js/
│   │   └── api/
│   │       └── client.js    # API 客户端
│   └── assets/
│       └── ruc-logo.svg     # RUC 品牌标识
├── backend/                 # PHP 后端
│   ├── index.php            # 入口文件
│   ├── config.php           # 配置文件
│   ├── database.php         # 数据库类
│   ├── database.sql         # 数据库初始化
│   ├── routes.php           # 路由处理
│   ├── controllers/         # 控制器
│   │   ├── auth.php         # 认证
│   │   ├── message.php      # 消息
│   │   ├── comment.php      # 评论
│   │   ├── admin.php        # 管理员
│   │   ├── notification.php # 通知
│   │   ├── conversation.php  # 私信
│   │   └── setting.php      # 设置
│   └── test-api.php         # API 测试脚本
├── docs/                    # API 文档
├── AGENTS.md               # Agent 规范
└── README.md
```

## 功能特性

### 用户功能
- [x] 用户注册与登录
- [x] JWT Token 认证
- [x] 密码重置
- [x] 用户设置

### 消息功能
- [x] 发布表白/秘密/提问
- [x] 浏览消息列表 (支持最新/热门/热评排序)
- [x] 搜索消息
- [x] 点赞/取消点赞
- [x] 评论与回复
- [x] 删除自己的消息
- [x] 举报违规消息

### 管理员功能
- [x] 消息审核 (通过/拒绝)
- [x] 强制删除违规消息
- [x] 待审核消息列表

### 社交功能
- [x] 通知系统 (点赞、评论、审核通知)
- [x] 私信功能
- [x] 用户主页

## 快速开始

### 推荐方式：使用 XAMPP + Apache + MySQL（团队本地开发）

这是当前仓库最直接的本地运行方式，适合 Windows + XAMPP 环境。

#### 1. 放置项目

将项目放到 XAMPP 的 `htdocs` 目录下，例如：

```text
D:\XAMPP\htdocs\Confession-Wall
```

#### 2. 启动服务

在 XAMPP Control Panel 中启动：

- `Apache`
- `MySQL`

#### 3. 初始化数据库

访问 `http://localhost/phpmyadmin`

1. 创建数据库 `confession_wall`
2. 排序规则选择 `utf8mb4_unicode_ci`
3. 导入 [`backend/database.sql`](backend/database.sql)

项目默认数据库配置位于 [`backend/config.php`](backend/config.php)：

```php
'database' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'confession_wall',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
],
```

如果你的本地 MySQL 账号密码不同，请先修改该文件。

#### 4. 确认 Apache 支持重写

本仓库已经包含 Apache 所需的 `.htaccess`，用于将 `/api/...` 请求转发到 PHP 后端。

请确认 Apache 已启用：

- `mod_rewrite`
- 目录级别 `AllowOverride`

XAMPP 默认通常已启用 `mod_rewrite`。如果 API 返回 404，请优先检查这两项。

#### 5. 访问项目

浏览器打开：

```text
http://localhost/Confession-Wall/
```

也可以直接访问登录页：

```text
http://localhost/Confession-Wall/public/login.html
```

当前仓库已经兼容子目录部署，前端会自动请求：

```text
http://localhost/Confession-Wall/api
```

### 方式一：使用 PHP 内置服务器（开发环境）

需要启动**两个**终端：

**终端 1 - 启动后端 API (端口 8081)：**
```bash
cd Confession-Wall
php -S localhost:8081 -t backend
```

**终端 2 - 启动前端 (端口 8080)：**
```bash
cd Confession-Wall
php -S localhost:8080 -t public
```

然后在每个 HTML 页面中引入 API 地址配置。在 `<script type="module">` 之前添加：
```html
<script>
  window.API_BASE_URL = 'http://localhost:8081/api';
</script>
<script type="module">
  import apiClient from './js/api/client.js';
  // ...
</script>
```

或者，如果你使用其他端口启动前端，可以设置不同的端口号，只要后端 API 地址正确即可。

访问 `http://localhost:8080` 查看前端页面。

### 方式二：使用 Nginx/Apache（自定义部署）

如果不是使用 XAMPP 默认的 `http://localhost/Confession-Wall/` 目录方式，而是自行配置虚拟主机或 Nginx，可参考以下方式部署。

Apache 下，当前仓库根目录和 `backend/` 目录均已提供 `.htaccess`，通常无需额外修改应用代码。

**Nginx 配置示例：**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/Confession-Wall/public;
    index index.html;

    # 前端页面
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 代理
    location /api {
        rewrite ^/api/(.*)$ /backend/index.php?path=$1 last;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /path/to/Confession-Wall/backend$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 3. 默认账号

| 用户名   | 密码     | 角色     |
| -------- | -------- | -------- |
| admin    | admin123 | 管理员   |
| testuser | test123  | 普通用户 |

> ⚠️ 生产环境请务必修改默认密码！

## API 文档

详细 API 接口文档请参阅 [`docs/`](docs/) 目录：

- [`docs/backend-api-locked.md`](docs/backend-api-locked.md) - 基础 API 文档
- [`docs/backend-api-new.md`](docs/backend-api-new.md) - 扩展 API 文档
- [`docs/api-client.md`](docs/api-client.md) - 前端 API 客户端文档

### 主要接口

| 模块   | 方法   | 路径                            | 说明         |
| ------ | ------ | ------------------------------- | ------------ |
| 消息   | GET    | /api/messages                   | 获取消息列表 |
| 消息   | POST   | /api/messages                   | 发布消息     |
| 消息   | GET    | /api/messages/{id}              | 获取消息详情 |
| 消息   | DELETE | /api/messages/{id}              | 删除消息     |
| 消息   | POST   | /api/messages/{id}/like         | 点赞         |
| 评论   | GET    | /api/messages/{id}/comments     | 获取评论     |
| 评论   | POST   | /api/messages/{id}/comments     | 发表评论     |
| 认证   | POST   | /api/auth/login                 | 登录         |
| 认证   | POST   | /api/auth/register              | 注册         |
| 认证   | POST   | /api/auth/refresh               | 刷新 Token   |
| 管理员 | GET    | /api/admin/messages/pending     | 待审核列表   |
| 管理员 | PUT    | /api/admin/messages/{id}/status | 审核消息     |

### API 响应格式

```json
{
    "code": 200,
    "message": "success",
    "data": { ... }
}
```

## 开发说明

### 添加新接口

1. 在 `routes.php` 添加路由映射
2. 在对应的控制器文件中实现处理函数
3. 如需新表，在 `database.sql` 中添加

### 测试 API

```bash
php backend/test-api.php
```

### 数据库配置

编辑 `backend/config.php` 修改配置：

```php
return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'confession_wall',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'jwt' => [
        'secret' => 'your-secret-key',
        'expiration' => 86400,
        'refresh_expiration' => 604800,
    ],
    'moderation' => [
        'require_approval' => false,
        'max_content_length' => 500,
        'max_comment_length' => 200,
    ],
];
```

## License

MIT
