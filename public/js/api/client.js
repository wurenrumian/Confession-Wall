class APIClient {
  constructor() {
    this.baseURL = this.resolveBaseURL();
    this.token = localStorage.getItem('token');
    this.refreshTokenValue = localStorage.getItem('refresh_token');
  }

  resolveBaseURL() {
    const runtimeBaseURL = window.API_BASE_URL || localStorage.getItem('api_base_url');
    if (runtimeBaseURL) {
      return runtimeBaseURL.replace(/\/$/, '');
    }

    const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    if (isLocalhost && window.location.port === '8080') {
      return 'http://localhost:8081/api';
    }

    const { origin, pathname } = window.location;
    const publicSegment = '/public/';
    const publicIndex = pathname.indexOf(publicSegment);

    if (publicIndex !== -1) {
      const projectBase = pathname.slice(0, publicIndex);
      return `${origin}${projectBase}/api`;
    }

    return `${origin}/api`;
  }

  async parseResponse(response, endpoint) {
    const rawText = await response.text();
    if (!rawText) {
      return {
        code: response.status,
        message: response.ok ? 'success' : `HTTP ${response.status}`,
        data: null
      };
    }

    try {
      return JSON.parse(rawText);
    } catch (parseError) {
      const preview = rawText.slice(0, 200);
      throw new Error(`接口 ${endpoint} 返回了非 JSON 响应（HTTP ${response.status}）: ${preview}`);
    }
  }

  async request(endpoint, options = {}) {
    const config = {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options
    };
    if (this.token && !endpoint.startsWith('/auth')) {
      config.headers.Authorization = `Bearer ${this.token}`;
    }
    try {
      const response = await fetch(`${this.baseURL}${endpoint}`, config);
      const data = await this.parseResponse(response, endpoint);
      if (response.status === 401) {
        return this.handleUnauthorized(endpoint, options);
      }
      return data;
    } catch (error) {
      console.error('[APIClient] 请求失败', { endpoint, error });
      throw error;
    }
  }

  async handleUnauthorized(endpoint, options) {
    if (this.refreshTokenValue) {
      const refreshResponse = await this.request('/auth/refresh', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${this.refreshTokenValue}` }
      });
      if (refreshResponse.code === 200 && refreshResponse.data && refreshResponse.data.token) {
        this.token = refreshResponse.data.token;
        localStorage.setItem('token', this.token);
        if (refreshResponse.data.expires_in) {
          localStorage.setItem('token_expires', Date.now() + refreshResponse.data.expires_in * 1000);
        }
        options.headers = { ...options.headers, Authorization: `Bearer ${this.token}` };
        const retryResponse = await fetch(`${this.baseURL}${endpoint}`, options);
        return this.parseResponse(retryResponse, endpoint);
      }
    }
    localStorage.removeItem('token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
    localStorage.removeItem('token_expires');
    window.location.href = 'login.html';
    return { code: 401, message: 'Unauthorized' };
  }

  setToken(token, refreshToken, expiresIn) {
    this.token = token;
    this.refreshTokenValue = refreshToken;
    localStorage.setItem('token', token);
    localStorage.setItem('refresh_token', refreshToken);
    if (expiresIn) {
      localStorage.setItem('token_expires', Date.now() + expiresIn * 1000);
    }
  }

  clearToken() {
    this.token = null;
    this.refreshTokenValue = null;
    localStorage.removeItem('token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
    localStorage.removeItem('token_expires');
  }

  isLoggedIn() {
    return !!this.token;
  }

  getUser() {
    const userStr = localStorage.getItem('user');
    return userStr ? JSON.parse(userStr) : null;
  }

  setUser(user) {
    localStorage.setItem('user', JSON.stringify(user));
  }

  // Auth endpoints
  async login(credentials) {
    return this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify(credentials)
    });
  }

  async logout() {
    try {
      await this.request('/auth/logout', { method: 'POST' });
    } catch (e) { /* ignore */ }
    this.clearToken();
  }

  async refreshToken() {
    return this.request('/auth/refresh', { method: 'POST' });
  }

  async register(data) {
    return this.request('/auth/register', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async changePassword(data) {
    return this.request('/auth/change-password', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async forgotPassword(email) {
    return this.request('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify({ email })
    });
  }

  async resetPassword(token, password) {
    return this.request('/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify({ token, password })
    });
  }

  async changePassword(data) {
    return this.request('/auth/change-password', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async forgotPassword(email) {
    return this.request('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify({ email })
    });
  }

  async resetPassword(token, password) {
    return this.request('/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify({ token, password })
    });
  }

  // Message endpoints
  async getMessages(params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.set('page', params.page);
    if (params.limit) query.set('limit', params.limit);
    if (params.sort) query.set('sort', params.sort);
    const endpoint = `/messages${query.toString() ? '?' + query.toString() : ''}`;
    return this.request(endpoint);
  }

  async getMessage(id) {
    return this.request(`/messages/${id}`);
  }

  async createMessage(data) {
    return this.request('/messages', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async deleteMessage(id) {
    return this.request(`/messages/${id}`, { method: 'DELETE' });
  }

  // Like endpoints
  async likeMessage(id) {
    return this.request(`/messages/${id}/like`, { method: 'POST' });
  }

  // Comment endpoints
  async getComments(messageId, params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.set('page', params.page);
    if (params.limit) query.set('limit', params.limit);
    const endpoint = `/messages/${messageId}/comments${query.toString() ? '?' + query.toString() : ''}`;
    return this.request(endpoint);
  }

  async createComment(messageId, data) {
    return this.request(`/messages/${messageId}/comments`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // Admin endpoints
  async getPendingMessages(params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.set('page', params.page);
    if (params.limit) query.set('limit', params.limit);
    const endpoint = `/admin/messages/pending${query.toString() ? '?' + query.toString() : ''}`;
    return this.request(endpoint);
  }

  async reviewMessage(id, data) {
    return this.request(`/admin/messages/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async adminDeleteMessage(id) {
    return this.request(`/admin/messages/${id}`, { method: 'DELETE' });
  }

  // Additional API endpoints (not in backend-doc but useful for frontend)
  async reportMessage(id, reason) {
    return this.request(`/messages/${id}/report`, {
      method: 'POST',
      body: JSON.stringify({ reason })
    });
  }

  async getUserMessages(userId, params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.set('page', params.page);
    if (params.limit) query.set('limit', params.limit);
    const endpoint = `/users/${userId}/messages${query.toString() ? '?' + query.toString() : ''}`;
    return this.request(endpoint);
  }

  async searchMessages(query, params = {}) {
    const queryParams = new URLSearchParams({ q: query });
    if (params.page) queryParams.set('page', params.page);
    if (params.limit) queryParams.set('limit', params.limit);
    return this.request(`/messages/search?${queryParams.toString()}`);
  }

  async getNotifications(params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.set('page', params.page);
    if (params.limit) query.set('limit', params.limit);
    const endpoint = `/notifications${query.toString() ? '?' + query.toString() : ''}`;
    return this.request(endpoint);
  }

  async markNotificationRead(id) {
    return this.request(`/notifications/${id}/read`, { method: 'PUT' });
  }

  async clearNotifications() {
    return this.request('/notifications', { method: 'DELETE' });
  }

  async getConversations(params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.set('page', params.page);
    if (params.limit) query.set('limit', params.limit);
    const endpoint = `/messages/conversations${query.toString() ? '?' + query.toString() : ''}`;
    return this.request(endpoint);
  }

  async getConversation(userId, params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.set('page', params.page);
    if (params.limit) query.set('limit', params.limit);
    const endpoint = `/messages/conversations/${userId}${query.toString() ? '?' + query.toString() : ''}`;
    return this.request(endpoint);
  }

  async sendMessage(userId, content) {
    return this.request(`/messages/conversations/${userId}`, {
      method: 'POST',
      body: JSON.stringify({ content })
    });
  }

  async getSettings() {
    return this.request('/settings');
  }

  async updateSettings(data) {
    return this.request('/settings', {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }
}

const apiClient = new APIClient();
export default apiClient;
