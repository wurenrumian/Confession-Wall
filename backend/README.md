# 匿名墙后端 (Confession Wall Backend)

基于 PHP + SQLite 的校园匿名墙后端 API 服务。

## 功能特性

- 用户认证 (JWT)
- 消息发布与审核
- 评论与点赞
- 私信功能
- 通知系统
- 举报管理
- 用户设置

## 目录结构

```
backend/
├── index.php           # 入口文件
├── config.php          # 配置文件
├── database.php        # 数据库类
├── database.sql        # 数据库初始化 SQL
├── routes.php          # 路由处理
├── controllers/       # 控制器
│   ├── auth.php        # 认证
│   ├── message.php     # 消息
│   ├── comment.php     # 评论
│   ├── admin.php       # 管理员
│   ├── notification.php # 通知
│   ├── conversation.php # 私信
│   └── setting.php     # 设置
└── README.md
```

## 快速开始

### 1. 配置 Web 服务器

**Nginx 配置示例:**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/Confession-Wall/backend;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        log_not_found off;
    }
}
```

### 2. 初始化数据库

首次访问时，数据库会自动创建。如需手动初始化：

```bash
cd backend
sqlite3 database.sqlite < database.sql
```

### 3. 默认账号

| 用户名   | 密码     | 角色     |
| -------- | -------- | -------- |
| admin    | admin123 | 管理员   |
| testuser | test123  | 普通用户 |

> ⚠️ 生产环境请务必修改默认密码！

## API 接口

基础 URL: `/api`

### 认证接口

| 方法 | 路径                  | 说明         |
| ---- | --------------------- | ------------ |
| POST | /auth/login           | 登录         |
| POST | /auth/register        | 注册         |
| POST | /auth/logout          | 退出         |
| POST | /auth/refresh         | 刷新 Token   |
| POST | /auth/forgot-password | 请求密码重置 |
| POST | /auth/reset-password  | 重置密码     |

### 消息接口

| 方法   | 路径                  | 说明          |
| ------ | --------------------- | ------------- |
| GET    | /messages             | 获取消息列表  |
| GET    | /messages/search      | 搜索消息      |
| GET    | /messages/{id}        | 获取单条消息  |
| POST   | /messages             | 发布消息      |
| DELETE | /messages/{id}        | 删除消息      |
| POST   | /messages/{id}/like   | 点赞/取消点赞 |
| POST   | /messages/{id}/report | 举报消息      |

### 评论接口

| 方法 | 路径                    | 说明     |
| ---- | ----------------------- | -------- |
| GET  | /messages/{id}/comments | 获取评论 |
| POST | /messages/{id}/comments | 发表评论 |

### 管理员接口

| 方法   | 路径                        | 说明       |
| ------ | --------------------------- | ---------- |
| GET    | /admin/messages/pending     | 待审核列表 |
| PUT    | /admin/messages/{id}/status | 审核消息   |
| DELETE | /admin/messages/{id}        | 删除消息   |

详细 API 文档请参阅 [`docs/`](docs/) 目录。

## 配置项

编辑 [`config.php`](config.php) 修改配置：

```php
return [
    'database' => [
        'path' => __DIR__ . '/database.sqlite',
    ],
    'jwt' => [
        'secret' => 'your-secret-key',
        'expiration' => 86400,       // 24小时
        'refresh_expiration' => 604800, // 7天
    ],
    'moderation' => [
        'require_approval' => true,  // 是否需要审核
        'max_content_length' => 500,
        'max_comment_length' => 200,
    ],
];
```

## 响应格式

```json
{
    "code": 200,
    "message": "success",
    "data": { ... }
}
```

错误响应:

```json
{
    "code": 400,
    "message": "错误信息",
    "data": null
}
```

## 错误码

| 错误码 | 说明             |
| ------ | ---------------- |
| 2000   | 参数验证失败     |
| 3000   | Token 无效或过期 |
| 3001   | 权限不足         |
| 3002   | 用户名已存在     |
| 4000   | 资源不存在       |
| 5000   | 服务器内部错误   |

## 开发说明

### 添加新接口

1. 在 `routes.php` 添加路由映射
2. 在对应的控制器文件中实现处理函数
3. 在 `database.sql` 中添加需要的表（如有）

### 本地测试

使用 PHP 内置服务器：

```bash
cd backend
php -S localhost:8000
```

访问 `http://localhost:8000/api/messages` 测试。

## License

MIT
