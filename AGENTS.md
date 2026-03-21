# AGENTS.md - 人大匿名墙 (Campus Confession Wall)

## 项目概述

校园匿名墙前端项目，基于 Twitter 风格和中国人民大学红白配色。
前端使用纯 HTML/CSS/JavaScript (ES6 模块)，无框架依赖，通过 Fetch API 与后端通信。

**基础 URL**: `/api`
**认证方式**: JWT Bearer Token

---

## 目录结构

├── index.html          # 论坛/时间线主页
├── login.html          # 登录页面
├── css/
│   └── style.css       # 全部样式
├── js/
│   ├── api/
│   │   └── client.js   # API 客户端封装（基于 Fetch，支持 Token 刷新）
│   └── app.js          # (预留主应用逻辑文件)
└── assets/
    └── ruc-logo.svg    # RUC 品牌 logo
```

---

## 构建与运行命令

本项目为纯静态前端，无需构建。直接在浏览器中打开 HTML 文件即可。



**生产部署**: 将 `index.html`、`login.html`、`css/`、`js/`、`assets/` 部署到任意静态文件服务器。

---

## API 客户端

### 核心文件: `js/api/client.js`

所有 API 调用通过 `APIClient` 类进行，基于原生 Fetch API。

**主要方法**:
| 方法 | 说明 | 认证 |
|------|------|------|
| `getMessages(params)` | 获取消息列表 | 否 |
| `getMessage(id)` | 获取单条消息 | 否 |
| `createMessage(data)` | 发布消息 | 可选 |
| `deleteMessage(id)` | 删除消息 | 是 |
| `likeMessage(id)` | 点赞/取消点赞 | 是 |
| `getComments(messageId, params)` | 获取评论 | 否 |
| `createComment(messageId, data)` | 发表评论 | 是 |
| `login(credentials)` | 用户登录 | 否 |
| `logout()` | 退出登录 | 是 |
| `refreshToken()` | 刷新 Token | 否 |
| `getPendingMessages(params)` | 待审核列表(管理员) | 管理员 |
| `reviewMessage(id, data)` | 审核消息(管理员) | 管理员 |
| `adminDeleteMessage(id)` | 强制删除(管理员) | 管理员 |

**响应格式**:
```json
{
  "code": 200,
  "message": "success",
  "data": { ... }
}
```

### Token 管理
- `token` 存储在 `localStorage.token`
- `refresh_token` 存储在 `localStorage.refresh_token`
- `user` 对象存储在 `localStorage.user`
- `401` 响应时自动尝试刷新 Token，失败则跳转 `login.html`

### 扩展 API（前端增加，后端未列出）
- `reportMessage(id, reason)` - 举报消息
- `getUserMessages(userId, params)` - 获取用户发布的消息

---

## 代码风格规范

### 通用规范
- 使用 ES6+ 语法（`const`/`let`/`async`/`await`/`import`/`export`）
- 使用 2 空格缩进
- 不使用分号（可选，但推荐保持一致）
- 使用单引号 `'` 表示字符串
- 优先使用 `const`，仅在需要重新赋值时使用 `let`

### HTML
- 使用语义化标签 (`<header>`, `<main>`, `<aside>`, `<nav>`, `<article>`, `<section>`)
- 属性值使用双引号
- 自闭合标签不使用斜杠 (`<br>`, `<input>`)
- 始终设置 `<meta charset="UTF-8">` 和 viewport

### CSS
- 使用 CSS 变量（`--variable-name`）管理主题颜色
- 颜色变量命名: `--ruc-red`, `--ruc-red-dark`, `--white`, `--gray-50~800`, `--black`
- 组件命名: `.feed-*`, `.tweet-*`, `.sidebar-*`, `.modal-*`, `.widget-*`, `.btn-*`
- 使用 BEM 变体命名（组件-子元素__修饰符）
- 避免使用 `!important`
- 优先使用 flexbox 和 grid 布局
- 移动优先响应式设计

### JavaScript
- 使用 ES6 模块 (`type="module"` + `import`/`export`)
- 函数声明使用 `async`/`await`，使用 `try...catch` 处理错误
- 事件处理: 优先使用事件委托（`attachTweetListeners` 模式）
- DOM 操作: 优先使用 `document.querySelector` / `getElementById`
- 避免使用 `var`
- 字符串模板使用反引号 `` ` ``
- Arrow functions 用于简短回调

### API 调用规范
- 所有 API 调用通过 `apiClient`
- 检查 `result.code === 200`（或对应成功码）
- 错误时显示 Toast 提示用户
- 加载/提交状态时禁用按钮防止重复提交

---

## 数据模型

### Message（消息）
```javascript
{
  id: integer,
  content: string,
  type: 'confession' | 'secret' | 'question',
  status: 'pending' | 'approved' | 'rejected',
  like_count: integer,
  comment_count: integer,
  is_liked: boolean,
  created_at: 'YYYY-MM-DD HH:mm:ss',
  updated_at: 'YYYY-MM-DD HH:mm:ss'
}
```

### Comment（评论）
```javascript
{
  id: integer,
  message_id: integer,
  content: string,
  parent_id: integer | null,
  created_at: 'YYYY-MM-DD HH:mm:ss'
}
```

### User（用户）
```javascript
{
  id: integer,
  username: string,
  role: 'user' | 'admin'
}
```

---

## 已实现的 UI 组件

| 组件 | 说明 |
|------|------|
| 侧边栏导航 | 固定左侧，响应式折叠 |
| 时间线 Feed | 分页加载，支持最新/热门/热评排序 |
| 消息卡片 | 点赞/评论/分享操作 |
| 发布框 | 支持表白/秘密/提问三种类型 |
| 评论弹窗 | Modal 展示评论列表和发表评论 |
| Toast 通知 | success/error/warning 三种类型 |
| 用户菜单 | 退出登录 |
| 搜索框 | 右侧边栏（预留功能） |
| 热门话题 | 右侧边栏（静态数据） |
| 社区规则 | 右侧边栏 |
| 加载更多 | 底部加载更多按钮 |
| 回到顶部 | 滚动超过 300px 显示 |
| 登录页 | 左右分栏布局，RUC 品牌展示 |

---

## 错误处理

- 网络错误: `showToast('网络错误，请稍后重试', 'error')`
- API 错误: 使用后端返回的 `result.message`
- 表单验证: 实时验证 + 提交时完整验证
- 未登录操作: 提示登录并跳转 `login.html`
- Token 过期: 自动刷新，失败则跳转登录页
