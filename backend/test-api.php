<?php
/**
 * API 测试脚本
 * 使用前端定义的 API 调用方式测试后端接口
 */

$BASE_URL = 'http://localhost:8081/api';
$results = [];

function request($method, $endpoint, $data = null, $token = null) {
    global $BASE_URL;
    
    // URL 编码查询参数
    if (strpos($endpoint, '?') !== false) {
        list($path, $query) = explode('?', $endpoint, 2);
        parse_str($query, $params);
        foreach ($params as $key => $value) {
            $params[$key] = urlencode($value);
        }
        $endpoint = $path . '?' . http_build_query($params);
    }
    
    $url = $BASE_URL . $endpoint;
    $headers = ['Content-Type: application/json'];
    
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => json_decode($response, true),
        'error' => $error
    ];
}

echo "=== 匿名墙 API 测试 ===\n\n";

// 1. 测试获取消息列表 (不需要认证)
echo "1. GET /messages (获取消息列表)\n";
$res = request('GET', '/messages');
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['get_messages'] = $res;

// 2. 测试注册
echo "2. POST /auth/register (注册用户)\n";
$res = request('POST', '/auth/register', [
    'username' => 'testuser_' . time(),
    'password' => 'test123456',
    'email' => 'test' . time() . '@ruc.edu.cn'
]);
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['register'] = $res;

// 3. 测试登录
echo "3. POST /auth/login (登录)\n";
$res = request('POST', '/auth/login', [
    'username' => 'admin',
    'password' => 'admin123'
]);
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$token = $res['response']['data']['token'] ?? null;
$results['login'] = $res;

// 4. 测试发布消息 (需要认证)
echo "4. POST /messages (发布消息)\n";
$res = request('POST', '/messages', [
    'content' => '测试消息内容 - ' . date('Y-m-d H:i:s'),
    'type' => 'confession',
    'is_anonymous' => true
], $token);
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['create_message'] = $res;

$messageId = $res['response']['data']['id'] ?? null;

// 5. 测试获取单条消息
if ($messageId) {
    echo "5. GET /messages/{$messageId} (获取单条消息)\n";
    $res = request('GET', "/messages/{$messageId}");
    echo "   HTTP Code: {$res['code']}\n";
    echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $results['get_message'] = $res;
}

// 6. 测试点赞
if ($messageId) {
    echo "6. POST /messages/{$messageId}/like (点赞)\n";
    $res = request('POST', "/messages/{$messageId}/like", null, $token);
    echo "   HTTP Code: {$res['code']}\n";
    echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $results['like'] = $res;
}

// 7. 测试发表评论
if ($messageId) {
    echo "7. POST /messages/{$messageId}/comments (发表评论)\n";
    $res = request('POST', "/messages/{$messageId}/comments", [
        'content' => '这是一条测试评论'
    ], $token);
    echo "   HTTP Code: {$res['code']}\n";
    echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $results['comment'] = $res;
    
    $commentId = $res['response']['data']['id'] ?? null;
}

// 8. 测试获取评论
if ($messageId) {
    echo "8. GET /messages/{$messageId}/comments (获取评论)\n";
    $res = request('GET', "/messages/{$messageId}/comments");
    echo "   HTTP Code: {$res['code']}\n";
    echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $results['get_comments'] = $res;
}

// 9. 测试管理员获取待审核列表
echo "9. GET /admin/messages/pending (获取待审核列表)\n";
$res = request('GET', '/admin/messages/pending', null, $token);
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['pending'] = $res;

// 10. 测试审核消息
if ($messageId) {
    echo "10. PUT /admin/messages/{$messageId}/status (审核消息)\n";
    $res = request('PUT', "/admin/messages/{$messageId}/status", [
        'status' => 'approved'
    ], $token);
    echo "   HTTP Code: {$res['code']}\n";
    echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $results['review'] = $res;
}

// 11. 测试获取通知
echo "11. GET /notifications (获取通知)\n";
$res = request('GET', '/notifications', null, $token);
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['notifications'] = $res;

// 12. 测试获取设置
echo "12. GET /settings (获取设置)\n";
$res = request('GET', '/settings', null, $token);
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['settings'] = $res;

// 13. 测试搜索
echo "13. GET /messages/search?q=测试 (搜索消息)\n";
$res = request('GET', '/messages/search?q=测试');
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['search'] = $res;

// 14. 测试 Token 刷新
echo "14. POST /auth/refresh (刷新 Token)\n";
$res = request('POST', '/auth/refresh', null, $token);
echo "   HTTP Code: {$res['code']}\n";
echo "   Response: " . json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
$results['refresh'] = $res;

// 总结
echo "\n=== 测试总结 ===\n";
$success = 0;
$failed = 0;
foreach ($results as $test => $result) {
    if ($result['code'] >= 200 && $result['code'] < 300) {
        echo "✅ {$test}: HTTP {$result['code']}\n";
        $success++;
    } else {
        echo "❌ {$test}: HTTP {$result['code']}\n";
        if ($result['error']) {
            echo "   Error: {$result['error']}\n";
        }
        $failed++;
    }
}

echo "\n通过: {$success}, 失败: {$failed}\n";
