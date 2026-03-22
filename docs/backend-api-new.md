# 匿名墙后端 RESTful API 文档 - 扩展功能

## 概述

本文档描述了匿名墙（Confession Wall）后端的扩展 RESTful API 接口，包括搜索、通知、私信、用户设置和密码重置等功能。

**基础信息**
- 基础 URL: `/api`
- 数据格式: JSON
- 字符编码: UTF-8
- 请求方法: GET, POST, PUT, DELETE

---

## API 接口列表

### 6. 搜索功能

#### 6.1 搜索消息

**接口说明**: 根据关键词搜索消息内容

**请求地址**: `GET /api/messages/search`

**认证要求**: 否

**请求参数**:

| 参数名 | 类型    | 必填 | 说明              | 示例     |
| ------ | ------- | ---- | ----------------- | -------- |
| q      | string  | 是   | 搜索关键词        | `图书馆` |
| page   | integer | 否   | 页码，默认 1      | `1`      |
| limit  | integer | 否   | 每页数量，默认 20 | `20`     |

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
        "created_at": "2024-01-15 10:30:00"
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

### 7. 通知功能

#### 7.1 获取通知列表

**接口说明**: 获取当前用户的通知列表

**请求地址**: `GET /api/notifications`

**认证要求**: 是

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
    "notifications": [
      {
        "id": 1,
        "type": "like",
        "title": "有人赞了你的消息",
        "content": "用户"小明"赞了你的表白消息",
        "is_read": false,
        "created_at": "2024-01-15 10:30:00",
        "related_id": 123
      }
    ],
    "pagination": {
      "total": 10,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

#### 7.2 标记通知已读

**接口说明**: 将指定通知标记为已读

**请求地址**: `PUT /api/notifications/{id}/read`

**认证要求**: 是

**请求参数**: 无

**响应示例**:

```json
{
  "code": 200,
  "message": "已标记为已读",
  "data": null
}
```

#### 7.3 清空所有通知

**接口说明**: 清空当前用户的所有通知

**请求地址**: `DELETE /api/notifications`

**认证要求**: 是

**响应示例**:

```json
{
  "code": 200,
  "message": "清空成功",
  "data": null
}
```

---

### 8. 私信功能

#### 8.1 获取私信列表

**接口说明**: 获取当前用户的私信会话列表

**请求地址**: `GET /api/messages/conversations`

**认证要求**: 是

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
    "conversations": [
      {
        "id": 1,
        "user": {
          "id": 5,
          "username": "小明"
        },
        "last_message": "好的，谢谢！",
        "last_message_time": "2024-01-15 10:30:00",
        "unread_count": 2
      }
    ],
    "pagination": {
      "total": 3,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

#### 8.2 获取私信对话

**接口说明**: 获取与指定用户的私信对话

**请求地址**: `GET /api/messages/conversations/{userId}`

**认证要求**: 是

**请求参数**:

| 参数名 | 类型    | 必填 | 说明     | 示例 |
| ------ | ------- | ---- | -------- | ---- |
| page   | integer | 否   | 页码     | `1`  |
| limit  | integer | 否   | 每页数量 | `50` |

**响应示例**:

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "messages": [
      {
        "id": 1,
        "sender_id": 5,
        "receiver_id": 1,
        "content": "你好，我想问一下...",
        "is_read": true,
        "created_at": "2024-01-15 09:00:00"
      }
    ],
    "user": {
      "id": 5,
      "username": "小明"
    },
    "pagination": {
      "total": 10,
      "page": 1,
      "limit": 50,
      "total_pages": 1
    }
  }
}
```

#### 8.3 发送私信

**接口说明**: 向指定用户发送私信

**请求地址**: `POST /api/messages/conversations/{userId}`

**认证要求**: 是

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名  | 类型   | 必填 | 说明     | 约束       |
| ------- | ------ | ---- | -------- | ---------- |
| content | string | 是   | 消息内容 | 1-500 字符 |

**响应示例**:

```json
{
  "code": 201,
  "message": "发送成功",
  "data": {
    "id": 5,
    "sender_id": 1,
    "receiver_id": 5,
    "content": "好的，谢谢！",
    "created_at": "2024-01-15 10:30:00"
  }
}
```

---

### 9. 用户设置

#### 9.1 获取用户设置

**接口说明**: 获取当前用户的设置信息

**请求地址**: `GET /api/settings`

**认证要求**: 是

**响应示例**:

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "notifications_enabled": true,
    "email_notifications": false,
    "profile_visible": true
  }
}
```

#### 9.2 更新用户设置

**接口说明**: 更新当前用户的设置

**请求地址**: `PUT /api/settings`

**认证要求**: 是

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名                | 类型    | 必填 | 说明                 | 默认值 |
| --------------------- | ------- | ---- | -------------------- | ------ |
| notifications_enabled | boolean | 否   | 是否启用通知         | true   |
| email_notifications   | boolean | 否   | 是否接收邮件通知     | false  |
| profile_visible       | boolean | 否   | 个人资料是否公开可见 | true   |

**响应示例**:

```json
{
  "code": 200,
  "message": "设置已更新",
  "data": {
    "notifications_enabled": true,
    "email_notifications": false,
    "profile_visible": true
  }
}
```

---

### 10. 密码重置

#### 10.1 请求密码重置

**接口说明**: 发送密码重置邮件（或生成重置令牌）

**请求地址**: `POST /api/auth/forgot-password`

**认证要求**: 否

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名 | 类型   | 必填 | 说明 | 约束           |
| ------ | ------ | ---- | ---- | -------------- |
| email  | string | 是   | 邮箱 | 有效的邮箱格式 |

**响应示例**:

```json
{
  "code": 200,
  "message": "重置邮件已发送，请查收",
  "data": null
}
```

#### 10.2 重置密码

**接口说明**: 使用重置令牌设置新密码

**请求地址**: `POST /api/auth/reset-password`

**认证要求**: 否

**请求头**:
```
Content-Type: application/json
```

**请求参数**:

| 参数名   | 类型   | 必填 | 说明     | 约束       |
| -------- | ------ | ---- | -------- | ---------- |
| token    | string | 是   | 重置令牌 | 64位字符串 |
| password | string | 是   | 新密码   | 6-64 字符  |

**响应示例**:

```json
{
  "code": 200,
  "message": "密码重置成功",
  "data": null
}
```

---

## 数据模型扩展

### Notification（通知）

| 字段名     | 类型     | 说明                                   |
| ---------- | -------- | -------------------------------------- |
| id         | integer  | 主键，自增                             |
| user_id    | integer  | 接收用户ID                             |
| type       | enum     | 通知类型: like, comment, reply, system |
| title      | string   | 通知标题                               |
| content    | text     | 通知内容                               |
| is_read    | boolean  | 是否已读                               |
| related_id | integer  | 关联的消息/评论ID                      |
| created_at | datetime | 创建时间                               |

### Conversation（会话）

| 字段名          | 类型     | 说明           |
| --------------- | -------- | -------------- |
| id              | integer  | 主键，自增     |
| user1_id        | integer  | 用户1 ID       |
| user2_id        | integer  | 用户2 ID       |
| last_message_id | integer  | 最后一条消息ID |
| updated_at      | datetime | 最后更新时间   |

### PrivateMessage（私信）

| 字段名          | 类型     | 说明       |
| --------------- | -------- | ---------- |
| id              | integer  | 主键，自增 |
| conversation_id | integer  | 会话ID     |
| sender_id       | integer  | 发送者ID   |
| receiver_id     | integer  | 接收者ID   |
| content         | text     | 消息内容   |
| is_read         | boolean  | 是否已读   |
| created_at      | datetime | 发送时间   |
