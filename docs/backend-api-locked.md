# 匿名墙后端 RESTful API 文档

## 概述

本文档描述了匿名墙（Confession Wall）后端的 RESTful API 接口规范。后端使用 PHP 实现，提供完整的 CRUD 操作支持。

**基础信息**
- 基础 URL: `/api`
- 数据格式: JSON
- 字符编码: UTF-8
- 请求方法: GET, POST, PUT, DELETE

---

## 通用响应格式

### 成功响应

```json
{
  "code": 200,
  "message": "success",
  "data": {
    // 具体数据
  }
}
```

### 错误响应

```json
{
  "code": 400,
  "message": "错误描述",
  "data": null
}
```

### HTTP 状态码

| 状态码 | 说明           |
| ------ | -------------- |
| 200    | 成功           |
| 201    | 创建成功       |
| 400    | 请求参数错误   |
| 401    | 未授权         |
| 403    | 禁止访问       |
| 404    | 资源不存在     |
| 500    | 服务器内部错误 |

---

## 认证机制

所有需要认证的接口需要在请求头中包含 Token：

```
Authorization: Bearer {token}
```

Token 通过登录接口获取，有效期为 24 小时。

---

## API 接口列表

### 1. 消息管理

#### 1.1 获取消息列表

**接口说明**: 分页获取所有匿名消息

**请求地址**: `GET /api/messages`

**请求参数**:

| 参数名 | 类型    | 必填 | 说明                                   | 示例     |
| ------ | ------- | ---- | -------------------------------------- | -------- |
| page   | integer | 否   | 页码，默认 1                           | `1`      |
| limit  | integer | 否   | 每页数量，默认 20，最大 100            | `20`     |
| sort   | string  | 否   | 排序方式: `newest`(默认), `hot`, `top` | `newest` |

**响应示例**:

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "messages": [
      {
        "id": 1,
        "content": "今天图书馆遇到一个很帅的小哥哥...",
        "type": "confession",
        "status": "approved",
        "like_count": 15,
        "comment_count": 3,
        "is_liked": false,
        "created_at": "2024-01-15 10:30:00",
        "updated_at": "2024-01-15 10:30:00"
      }
    ],
    "pagination": {
      "total": 150,
      "page": 1,
      "limit": 20,
      "total_pages": 8
    }
  }
}
```

---

#### 1.2 发布消息

**接口说明**: 发布新的匿名消息

**请求地址**: `POST /api/messages`

**认证要求**: 否（可根据配置开启）

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名       | 类型    | 必填 | 说明         | 约束                                                 |
| ------------ | ------- | ---- | ------------ | ---------------------------------------------------- |
| content      | string  | 是   | 消息内容     | 1-500 字符                                           |
| type         | string  | 否   | 消息类型     | `confession`(表白), `secret`(秘密), `question`(提问) |
| is_anonymous | boolean | 否   | 是否完全匿名 | 默认 true                                            |
| reply_to     | integer | 否   | 回复的消息ID | 必须为有效消息ID                                     |

**响应示例**:

```json
{
  "code": 201,
  "message": "发布成功，等待审核",
  "data": {
    "id": 123,
    "content": "今天图书馆遇到一个很帅的小哥哥...",
    "type": "confession",
    "status": "pending",
    "created_at": "2024-01-15 10:30:00"
  }
}
```

---

#### 1.3 获取单条消息

**接口说明**: 根据 ID 获取消息详情

**请求地址**: `GET /api/messages/{id}`

**请求参数**:

| 参数名 | 类型    | 位置 | 说明   |
| ------ | ------- | ---- | ------ |
| id     | integer | URL  | 消息ID |

**响应示例**:

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "id": 1,
    "content": "今天图书馆遇到一个很帅的小哥哥...",
    "type": "confession",
    "status": "approved",
    "like_count": 15,
    "comment_count": 3,
    "is_liked": false,
    "created_at": "2024-01-15 10:30:00",
    "comments": [
      {
        "id": 1,
        "content": "我也看到了！",
        "created_at": "2024-01-15 11:00:00"
      }
    ]
  }
}
```

---

#### 1.4 删除消息

**接口说明**: 删除指定消息（仅管理员或作者）

**请求地址**: `DELETE /api/messages/{id}`

**认证要求**: 是

**请求参数**:

| 参数名 | 类型    | 位置 | 说明   |
| ------ | ------- | ---- | ------ |
| id     | integer | URL  | 消息ID |

**响应示例**:

```json
{
  "code": 200,
  "message": "删除成功",
  "data": null
}
```

---

### 2. 点赞功能

#### 2.1 点赞/取消点赞

**接口说明**: 对消息进行点赞或取消点赞

**请求地址**: `POST /api/messages/{id}/like`

**认证要求**: 是

**请求参数**: 无

**响应示例**:

```json
{
  "code": 200,
  "message": "点赞成功",
  "data": {
    "liked": true,
    "like_count": 16
  }
}
```

---

### 3. 评论功能

#### 3.1 获取消息评论

**接口说明**: 获取指定消息的所有评论

**请求地址**: `GET /api/messages/{id}/comments`

**请求参数**:

| 参数名 | 类型    | 必填 | 说明     | 示例 |
| ------ | ------- | ---- | -------- | ---- |
| page   | integer | 否   | 页码     | `1`  |
| limit  | integer | 否   | 每页数量 | `20` |

**响应示例**:

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "comments": [
      {
        "id": 1,
        "message_id": 123,
        "content": "我也看到了！",
        "parent_id": null,
        "created_at": "2024-01-15 11:00:00"
      }
    ],
    "pagination": {
      "total": 5,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

---

#### 3.2 发表评论

**接口说明**: 对消息发表评论

**请求地址**: `POST /api/messages/{id}/comments`

**认证要求**: 是

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名    | 类型    | 必填 | 说明                 | 约束             |
| --------- | ------- | ---- | -------------------- | ---------------- |
| content   | string  | 是   | 评论内容             | 1-200 字符       |
| parent_id | integer | 否   | 父评论ID（回复评论） | 必须为有效评论ID |

**响应示例**:

```json
{
  "code": 201,
  "message": "评论成功",
  "data": {
    "id": 5,
    "content": "我也看到了！",
    "parent_id": null,
    "created_at": "2024-01-15 11:00:00"
  }
}
```

---

### 4. 用户认证（可选）

#### 4.1 用户登录

**接口说明**: 用户登录获取 Token

**请求地址**: `POST /api/auth/login`

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名   | 类型   | 必填 | 说明   |
| -------- | ------ | ---- | ------ |
| username | string | 是   | 用户名 |
| password | string | 是   | 密码   |

**响应示例**:

```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "username": "admin",
      "role": "admin"
    },
    "expires_in": 86400
  }
}
```

---

#### 4.2 刷新 Token

**接口说明**: 使用刷新令牌获取新的访问令牌

**请求地址**: `POST /api/auth/refresh`

**请求头**:
```
Content-Type: application/json
Authorization: Bearer {refresh_token}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "刷新成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 86400
  }
}
```

---

#### 4.3 用户注册

**接口说明**: 注册新用户账号

**请求地址**: `POST /api/auth/register`

**认证要求**: 否

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名   | 类型   | 必填 | 说明   | 约束                                |
| -------- | ------ | ---- | ------ | ----------------------------------- |
| username | string | 是   | 用户名 | 3-50 字符，仅允许字母、数字、下划线 |
| password | string | 是   | 密码   | 6-64 字符                           |
| email    | string | 否   | 邮箱   | 有效的邮箱格式，可选                |

**响应示例**:

```json
{
  "code": 201,
  "message": "注册成功",
  "data": {
    "user": {
      "id": 5,
      "username": "student_zhang",
      "role": "user",
      "created_at": "2024-01-15 10:30:00"
    }
  }
}
```

**错误码**:
- `2000`: 参数验证失败（用户名/密码不符合规则）
- `2002`: 内容为空
- `3002`: 用户名已存在

---

### 5. 管理员接口（需要管理员权限）

#### 5.1 审核消息

**接口说明**: 审核通过或拒绝消息

**请求地址**: `PUT /api/admin/messages/{id}/status`

**认证要求**: 管理员

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名 | 类型   | 必填 | 说明     | 可选值                                              |
| ------ | ------ | ---- | -------- | --------------------------------------------------- |
| status | string | 是   | 审核状态 | `approved`(通过), `rejected`(拒绝), `pending`(待审) |
| reason | string | 否   | 拒绝原因 | 当 status=rejected 时建议填写                       |

**响应示例**:

```json
{
  "code": 200,
  "message": "审核成功",
  "data": {
    "id": 123,
    "status": "approved"
  }
}
```

---

#### 5.2 删除违规消息

**接口说明**: 强制删除违规消息

**请求地址**: `DELETE /api/admin/messages/{id}`

**认证要求**: 管理员

**响应示例**:

```json
{
  "code": 200,
  "message": "删除成功",
  "data": null
}
```

---

#### 5.3 获取待审核列表

**接口说明**: 获取待审核消息列表

**请求地址**: `GET /api/admin/messages/pending`

**认证要求**: 管理员

**请求参数**:

| 参数名 | 类型    | 必填 | 说明     | 示例 |
| ------ | ------- | ---- | -------- | ---- |
| page   | integer | 否   | 页码     | `1`  |
| limit  | integer | 否   | 每页数量 | `20` |

**响应示例**:

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "messages": [
      {
        "id": 123,
        "content": "待审核内容...",
        "type": "confession",
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "pagination": {
      "total": 15,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

---

## 错误码说明

| 错误码 | 说明             | 解决方案               |
| ------ | ---------------- | ---------------------- |
| 2000   | 参数验证失败     | 检查请求参数格式和类型 |
| 2001   | 内容过长         | 缩短内容长度           |
| 2002   | 内容为空         | 提供有效内容           |
| 3000   | Token 无效或过期 | 重新登录               |
| 3001   | 权限不足         | 使用管理员账号         |
| 3002   | 用户名已存在     | 更换用户名             |
| 4000   | 资源不存在       | 检查 ID 是否正确       |
| 5000   | 服务器内部错误   | 联系管理员             |

---

## 数据模型

### Message（消息）

| 字段名        | 类型     | 说明                               |
| ------------- | -------- | ---------------------------------- |
| id            | integer  | 主键，自增                         |
| content       | text     | 消息内容                           |
| type          | enum     | 类型: confession, secret, question |
| status        | enum     | 状态: pending, approved, rejected  |
| is_anonymous  | boolean  | 是否匿名                           |
| user_id       | integer  | 发布者ID（如果已登录）             |
| like_count    | integer  | 点赞数                             |
| comment_count | integer  | 评论数                             |
| created_at    | datetime | 创建时间                           |
| updated_at    | datetime | 更新时间                           |

### Comment（评论）

| 字段名     | 类型     | 说明                 |
| ---------- | -------- | -------------------- |
| id         | integer  | 主键，自增           |
| message_id | integer  | 关联的消息ID         |
| user_id    | integer  | 评论者ID             |
| content    | text     | 评论内容             |
| parent_id  | integer  | 父评论ID（支持回复） |
| created_at | datetime | 创建时间             |

### User（用户）

| 字段名     | 类型     | 说明                    |
| ---------- | -------- | ----------------------- |
| id         | integer  | 主键，自增              |
| username   | string   | 用户名（唯一）          |
| password   | string   | 密码（bcrypt 加密存储） |
| email      | string   | 邮箱（可选）            |
| role       | enum     | 角色: user, admin       |
| created_at | datetime | 创建时间                |

---

## 注意事项

1. **CORS 配置**: 前端域名需要添加到后端 CORS 白名单
2. **请求频率限制**: 建议限制为 60 次/分钟/IP
3. **内容审核**: 建议所有消息默认进入待审核状态
4. **敏感词过滤**: 后端应实现敏感词过滤机制
5. **SQL 注入防护**: 使用参数化查询或 ORM
6. **XSS 防护**: 对用户输入进行转义处理
7. **日志记录**: 记录关键操作日志

---

## 开发建议

### 后端开发
- 使用 PHP 框架（如 Laravel、ThinkPHP）加速开发
- 实现统一的错误处理中间件
- 使用 JWT 进行身份认证
- 实现数据库迁移脚本
- 编写单元测试

### 数据库设计
```sql
-- 消息表
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `type` varchar(20) DEFAULT 'confession',
  `status` varchar(20) DEFAULT 'pending',
  `is_anonymous` tinyint(1) DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `like_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 评论表
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 点赞表
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_message_user` (`message_id`,`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户表（必需）
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 密码重置令牌表（可选扩展）
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

