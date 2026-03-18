# API 调用封装文档

## 概述
基于原生 `Fetch API` 封装的后端接口调用客户端，支持 Token 自动刷新、请求/响应拦截及统一错误处理。

## 核心实现 (`js/api/client.js`)

```javascript
class APIClient {
  constructor() {
    this.baseURL = '/api';
    this.token = localStorage.getItem('token');
    this.refreshToken = localStorage.getItem('refresh_token');
  }

  // 通用请求方法
  async request(endpoint, options = {}) {
    const config = {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options
    };
    if (this.token && !endpoint.startsWith('/auth')) {
      config.headers.Authorization = `Bearer ${this.token}`;
    }
    const response = await fetch(`${this.baseURL}${endpoint}`, config);
    if (response.status === 401) return this.handleUnauthorized(endpoint, options);
    return response.json();
  }

  // 业务接口示例
  async getMessages(params) { /* ... */ }
  async createMessage(data) { /* ... */ }
  async likeMessage(id) { /* ... */ }
  async login(credentials) { /* ... */ }
  async logout() { /* ... */ }
}

const apiClient = new APIClient();
export default apiClient;
```

## 使用示例

### 1. 获取消息列表
```javascript
const result = await apiClient.getMessages({ page: 1, limit: 20 });
console.log(result.data.messages);
```

### 2. 发布消息
```javascript
await apiClient.createMessage({ content: 'Hello World', type: 'confession' });
```

### 3. 用户登录
```javascript
await apiClient.login({ username: 'admin', password: 'password' });
```

## 错误处理
所有请求均返回 Promise，建议使用 `try...catch` 捕获异常。
- `401`: 自动尝试刷新 Token，失败则跳转登录页。
- `400/500`: 抛出包含后端错误信息的 Error 对象。
