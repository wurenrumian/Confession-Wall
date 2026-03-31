<?php
/**
 * 路由处理
 */

// 处理器到控制器的映射
$handlerToController = [
    // 消息相关
    'getMessages' => 'message',
    'searchMessages' => 'message',
    'getMessage' => 'message',
    'createMessage' => 'message',
    'deleteMessage' => 'message',
    'likeMessage' => 'message',
    'reportMessage' => 'message',
    'getUserMessages' => 'message',
    
    // 评论相关
    'getComments' => 'comment',
    'createComment' => 'comment',
    
    // 认证相关
    'login' => 'auth',
    'register' => 'auth',
    'logout' => 'auth',
    'refreshToken' => 'auth',
    'forgotPassword' => 'auth',
    'resetPassword' => 'auth',
    
    // 通知相关
    'getNotifications' => 'notification',
    'markNotificationRead' => 'notification',
    'clearNotifications' => 'notification',
    
    // 私信相关
    'getConversations' => 'conversation',
    'getConversation' => 'conversation',
    'sendMessage' => 'conversation',
    
    // 设置相关
    'getSettings' => 'setting',
    'updateSettings' => 'setting',
    
    // 管理员相关
    'getPendingMessages' => 'admin',
    'reviewMessage' => 'admin',
    'adminDeleteMessage' => 'admin',
];

function dispatch($path, $method) {
    global $db, $pdo, $config, $handlerToController;
    
    // 路由映射
    $routes = [
        // 消息相关
        ['GET', '/messages', 'getMessages'],
        ['GET', '/messages/search', 'searchMessages'],
        ['GET', '/messages/{id}', 'getMessage'],
        ['POST', '/messages', 'createMessage'],
        ['DELETE', '/messages/{id}', 'deleteMessage'],
        ['POST', '/messages/{id}/like', 'likeMessage'],
        ['POST', '/messages/{id}/report', 'reportMessage'],
        
        // 评论相关
        ['GET', '/messages/{id}/comments', 'getComments'],
        ['POST', '/messages/{id}/comments', 'createComment'],
        
        // 用户消息
        ['GET', '/users/{userId}/messages', 'getUserMessages'],
        
        // 认证相关
        ['POST', '/auth/login', 'login'],
        ['POST', '/auth/register', 'register'],
        ['POST', '/auth/logout', 'logout'],
        ['POST', '/auth/refresh', 'refreshToken'],
        ['POST', '/auth/forgot-password', 'forgotPassword'],
        ['POST', '/auth/reset-password', 'resetPassword'],
        
        // 通知相关
        ['GET', '/notifications', 'getNotifications'],
        ['PUT', '/notifications/{id}/read', 'markNotificationRead'],
        ['DELETE', '/notifications', 'clearNotifications'],
        
        // 私信相关
        ['GET', '/messages/conversations', 'getConversations'],
        ['GET', '/messages/conversations/{userId}', 'getConversation'],
        ['POST', '/messages/conversations/{userId}', 'sendMessage'],
        
        // 设置相关
        ['GET', '/settings', 'getSettings'],
        ['PUT', '/settings', 'updateSettings'],
        
        // 管理员相关
        ['GET', '/admin/messages/pending', 'getPendingMessages'],
        ['PUT', '/admin/messages/{id}/status', 'reviewMessage'],
        ['DELETE', '/admin/messages/{id}', 'adminDeleteMessage'],
    ];
    
    foreach ($routes as $route) {
        list($routeMethod, $routePath, $handler) = $route;
        
        if ($routeMethod !== $method) {
            continue;
        }
        
        // 转换路由为正则
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $path, $matches)) {
            // 提取路由参数
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            
            // 获取请求体
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // 加载对应的控制器
            $controller = $handlerToController[$handler] ?? 'message';
            require_once __DIR__ . '/controllers/' . $controller . '.php';
            
            // 调用处理器
            call_user_func_array($handler, [$params, $body]);
            return;
        }
    }
    
    // 404
    $db->error(404, '接口不存在');
}

// ============ 辅助函数 ============

/**
 * 获取当前用户
 */
function getCurrentUser() {
    global $pdo, $config;
    
    $token = getBearerToken();
    if (!$token) {
        return null;
    }
    
    $payload = verifyJWT($token, $config['jwt']['secret']);
    if (!$payload) {
        return null;
    }
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$payload['user_id']]);
    return $stmt->fetch();
}

/**
 * 要求用户登录
 */
function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        global $db;
        $db->error(401, '请先登录');
    }
    return $user;
}

/**
 * 要求管理员权限
 */
function requireAdmin() {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        global $db;
        $db->error(403, '权限不足');
    }
    return $user;
}

/**
 * 获取 Bearer Token
 */
function getBearerToken() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * 验证 JWT
 */
function verifyJWT($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    
    list($header, $payload, $signature) = $parts;
    
    // 验证签名
    $expectedSig = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if ($signature !== strtr($expectedSig, '+/', '-_')) {
        return null;
    }
    
    // 解析 payload
    $payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    
    // 检查过期
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return null;
    }
    
    return $payload;
}

/**
 * 生成 JWT
 */
function generateJWT($userId, $expiration = 86400) {
    global $config;
    
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'iat' => time(),
        'exp' => time() + $expiration
    ]));
    
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $config['jwt']['secret'], true));
    
    return strtr("$header.$payload.$signature", '+/', '-_');
}

/**
 * 生成分页信息
 */
function paginate($total, $page, $limit) {
    return [
        'total' => (int)$total,
        'page' => (int)$page,
        'limit' => (int)$limit,
        'total_pages' => ceil($total / $limit)
    ];
}
