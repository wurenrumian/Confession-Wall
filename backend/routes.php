<?php
/**
 * 路由处理
 */

$handlerToController = [
    'getMessages' => 'message',
    'searchMessages' => 'message',
    'getMessage' => 'message',
    'createMessage' => 'message',
    'deleteMessage' => 'message',
    'likeMessage' => 'message',
    'reportMessage' => 'message',
    'getUserMessages' => 'message',

    'getComments' => 'comment',
    'createComment' => 'comment',

    'login' => 'auth',
    'register' => 'auth',
    'logout' => 'auth',
    'refreshToken' => 'auth',
    'forgotPassword' => 'auth',
    'resetPassword' => 'auth',
    'changePassword' => 'auth',

    'getNotifications' => 'notification',
    'markNotificationRead' => 'notification',
    'clearNotifications' => 'notification',

    'getConversations' => 'conversation',
    'getConversation' => 'conversation',
    'sendMessage' => 'conversation',
    'searchUsers' => 'conversation',

    'getSettings' => 'setting',
    'updateSettings' => 'setting',

    'getPendingMessages' => 'admin',
    'reviewMessage' => 'admin',
    'adminDeleteMessage' => 'admin',
];

function dispatch($path, $method) {
    global $db, $handlerToController;

    $routes = [
        ['GET', '/messages', 'getMessages'],
        ['GET', '/messages/search', 'searchMessages'],
        ['GET', '/messages/{id}', 'getMessage'],
        ['POST', '/messages', 'createMessage'],
        ['DELETE', '/messages/{id}', 'deleteMessage'],
        ['POST', '/messages/{id}/like', 'likeMessage'],
        ['POST', '/messages/{id}/report', 'reportMessage'],

        ['GET', '/messages/{id}/comments', 'getComments'],
        ['POST', '/messages/{id}/comments', 'createComment'],

        ['GET', '/users/search', 'searchUsers'],
        ['GET', '/users/{userId}/messages', 'getUserMessages'],

        ['POST', '/auth/login', 'login'],
        ['POST', '/auth/register', 'register'],
        ['POST', '/auth/logout', 'logout'],
        ['POST', '/auth/refresh', 'refreshToken'],
        ['POST', '/auth/forgot-password', 'forgotPassword'],
        ['POST', '/auth/reset-password', 'resetPassword'],
        ['POST', '/auth/change-password', 'changePassword'],

        ['GET', '/notifications', 'getNotifications'],
        ['PUT', '/notifications/{id}/read', 'markNotificationRead'],
        ['DELETE', '/notifications', 'clearNotifications'],

        ['GET', '/messages/conversations', 'getConversations'],
        ['GET', '/messages/conversations/{userId}', 'getConversation'],
        ['POST', '/messages/conversations/{userId}', 'sendMessage'],

        ['GET', '/settings', 'getSettings'],
        ['PUT', '/settings', 'updateSettings'],

        ['GET', '/admin/messages/pending', 'getPendingMessages'],
        ['PUT', '/admin/messages/{id}/status', 'reviewMessage'],
        ['DELETE', '/admin/messages/{id}', 'adminDeleteMessage'],
    ];

    foreach ($routes as $route) {
        [$routeMethod, $routePath, $handler] = $route;

        if ($routeMethod !== $method) {
            continue;
        }

        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $path, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $controller = $handlerToController[$handler] ?? 'message';
            require_once __DIR__ . '/controllers/' . $controller . '.php';

            call_user_func_array($handler, [$params, $body]);
            return;
        }
    }

    $db->error(404, '接口不存在');
}

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

function requireAuth() {
    global $db;

    $user = getCurrentUser();
    if (!$user) {
        $db->error(401, '请先登录');
    }
    return $user;
}

function requireAdmin() {
    global $db;

    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        $db->error(403, '权限不足');
    }
    return $user;
}

function getBearerToken() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($auth === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
        return $matches[1];
    }
    return null;
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder > 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function verifyJWT($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $signature] = $parts;
    $expectedSig = base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

    if (!hash_equals($expectedSig, $signature)) {
        return null;
    }

    $payloadData = json_decode(base64UrlDecode($payload), true);
    if (!is_array($payloadData)) {
        return null;
    }

    if (isset($payloadData['exp']) && intval($payloadData['exp']) < time()) {
        return null;
    }

    return $payloadData;
}

function generateJWT($userId, $expiration = 86400) {
    global $config;

    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64UrlEncode(json_encode([
        'user_id' => $userId,
        'iat' => time(),
        'exp' => time() + $expiration
    ]));
    $signature = base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $config['jwt']['secret'], true));

    return "{$header}.{$payload}.{$signature}";
}

function paginate($total, $page, $limit) {
    return [
        'total' => intval($total),
        'page' => intval($page),
        'limit' => intval($limit),
        'total_pages' => $limit > 0 ? intval(ceil($total / $limit)) : 0
    ];
}
