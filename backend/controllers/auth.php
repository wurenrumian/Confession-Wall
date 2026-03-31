<?php
/**
 * 认证控制器
 */

/**
 * 用户登录
 */
function login($params, $body) {
    global $pdo, $db, $config;
    
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $db->error(400, '用户名和密码不能为空');
    }
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        $db->error(401, '用户名或密码错误');
    }
    
    // 生成 Token
    $token = generateJWT($user['id'], $config['jwt']['expiration']);
    $refreshToken = generateJWT($user['id'], $config['jwt']['refresh_expiration']);
    
    $db->success([
        'token' => $token,
        'refresh_token' => $refreshToken,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ],
        'expires_in' => $config['jwt']['expiration']
    ], '登录成功');
}

/**
 * 用户注册
 */
function register($params, $body) {
    global $pdo, $db;
    
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $email = trim($body['email'] ?? '');
    
    // 验证
    if (empty($username) || empty($password)) {
        $db->error(400, '用户名和密码不能为空');
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $db->error(400, '用户名仅允许字母、数字、下划线，长度3-50字符');
    }
    
    if (strlen($password) < 6 || strlen($password) > 64) {
        $db->error(400, '密码长度6-64字符');
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $db->error(400, '邮箱格式不正确');
    }
    
    // 检查用户名是否存在
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $db->error(400, '用户名已存在');
    }
    
    // 创建用户
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, email) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hashedPassword, $email ?: null]);
    
    $userId = $pdo->lastInsertId();
    
    $db->created([
        'user' => [
            'id' => $userId,
            'username' => $username,
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], '注册成功');
}

/**
 * 退出登录
 */
function logout($params, $body) {
    global $db;
    // JWT 无状态，客户端删除 token 即可
    $db->success(null, '已退出登录');
}

/**
 * 刷新 Token
 */
function refreshToken($params, $body) {
    global $pdo, $db, $config;
    
    $refreshToken = getBearerToken();
    if (!$refreshToken) {
        $db->error(401, 'Refresh Token 无效');
    }
    
    $payload = verifyJWT($refreshToken, $config['jwt']['secret']);
    if (!$payload) {
        $db->error(401, 'Refresh Token 已过期');
    }
    
    // 生成新的 Token
    $token = generateJWT($payload['user_id'], $config['jwt']['expiration']);
    
    $db->success([
        'token' => $token,
        'expires_in' => $config['jwt']['expiration']
    ], '刷新成功');
}

/**
 * 请求密码重置
 */
function forgotPassword($params, $body) {
    global $pdo, $db;
    
    $email = trim($body['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $db->error(400, '请提供有效的邮箱地址');
    }
    
    // 查找用户
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // 为防止邮箱枚举攻击，无论是否存在都返回成功
    if (!$user) {
        $db->success(null, '如果邮箱存在，重置邮件已发送');
        return;
    }
    
    // 生成重置令牌
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $token, $expiresAt]);
    
    // TODO: 发送邮件 (预留)
    // mail($email, '密码重置', "您的重置链接: /reset-password?token=$token");
    
    $db->success(null, '重置邮件已发送，请查收');
}

/**
 * 重置密码
 */
function resetPassword($params, $body) {
    global $pdo, $db;
    
    $token = trim($body['token'] ?? '');
    $password = $body['password'] ?? '';
    
    if (empty($token) || empty($password)) {
        $db->error(400, 'Token 和新密码不能为空');
    }
    
    if (strlen($password) < 6 || strlen($password) > 64) {
        $db->error(400, '密码长度6-64字符');
    }
    
    // 验证 Token
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > datetime("now")');
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $db->error(400, '重置链接无效或已过期');
    }
    
    // 更新密码
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hashedPassword, $reset['user_id']]);
    
    // 删除已使用的 Token
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    
    $db->success(null, '密码重置成功');
}
