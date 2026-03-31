<?php
/**
 * 私信控制器
 */

/**
 * 获取私信会话列表
 */
function getConversations($params, $body) {
    global $pdo, $db;
    
    $user = requireAuth();
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // 获取会话列表（与当前用户相关的最新消息）
    $sql = "SELECT 
                c.id,
                c.sender_id,
                c.receiver_id,
                c.content,
                c.is_read,
                c.created_at,
                CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END as other_user_id
            FROM conversations c
            WHERE c.sender_id = ? OR c.receiver_id = ?
            GROUP BY other_user_id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $user['id'], $user['id'], $limit, $offset]);
    $conversations = $stmt->fetchAll();
    
    // 获取总数
    $sql = "SELECT COUNT(DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END) 
            FROM conversations WHERE sender_id = ? OR receiver_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    $total = $stmt->fetchColumn();
    
    // 格式化会话信息
    foreach ($conversations as &$conv) {
        // 获取对方用户信息
        $otherUserId = $conv['other_user_id'];
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$otherUserId]);
        $otherUser = $stmt->fetch();
        
        // 获取未读数
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
        $stmt->execute([$otherUserId, $user['id']]);
        $unreadCount = $stmt->fetchColumn();
        
        $conv['user'] = $otherUser;
        $conv['last_message'] = $conv['content'];
        $conv['last_message_time'] = $conv['created_at'];
        $conv['unread_count'] = $unreadCount;
        unset($conv['sender_id'], $conv['receiver_id'], $conv['content'], $conv['other_user_id']);
    }
    
    $db->success([
        'conversations' => $conversations,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

/**
 * 获取私信对话
 */
function getConversation($params, $body) {
    global $pdo, $db;
    
    $user = requireAuth();
    $otherUserId = intval($params['userId']);
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // 获取对方用户信息
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$otherUserId]);
    $otherUser = $stmt->fetch();
    if (!$otherUser) {
        $db->error(404, '用户不存在');
    }
    
    // 获取对话消息
    $sql = "SELECT * FROM conversations 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $otherUserId, $otherUserId, $user['id'], $limit, $offset]);
    $messages = $stmt->fetchAll();
    
    // 标记已读
    $stmt = $pdo->prepare('UPDATE conversations SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
    $stmt->execute([$otherUserId, $user['id']]);
    
    // 获取总数
    $sql = "SELECT COUNT(*) FROM conversations 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $otherUserId, $otherUserId, $user['id']]);
    $total = $stmt->fetchColumn();
    
    $db->success([
        'messages' => array_reverse($messages),
        'user' => $otherUser,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

/**
 * 发送私信
 */
function sendMessage($params, $body) {
    global $pdo, $db;
    
    $user = requireAuth();
    $receiverId = intval($params['userId']);
    $content = trim($body['content'] ?? '');
    
    if (empty($content)) {
        $db->error(400, '消息内容不能为空');
    }
    
    if (strlen($content) > 500) {
        $db->error(400, '消息过长');
    }
    
    if ($receiverId === $user['id']) {
        $db->error(400, '不能给自己发私信');
    }
    
    // 检查接收者是否存在
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$receiverId]);
    if (!$stmt->fetch()) {
        $db->error(404, '用户不存在');
    }
    
    // 创建消息
    $stmt = $pdo->prepare('INSERT INTO conversations (sender_id, receiver_id, content) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $receiverId, $content]);
    
    $messageId = $pdo->lastInsertId();
    
    $db->created([
        'id' => $messageId,
        'sender_id' => $user['id'],
        'receiver_id' => $receiverId,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ], '发送成功');
}
