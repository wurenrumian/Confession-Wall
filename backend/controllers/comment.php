<?php
/**
 * 评论控制器
 */

/**
 * 获取消息评论
 */
function getComments($params, $body) {
    global $pdo, $db;
    
    $messageId = intval($params['id']);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // 检查消息是否存在
    $stmt = $pdo->prepare('SELECT id FROM messages WHERE id = ?');
    $stmt->execute([$messageId]);
    if (!$stmt->fetch()) {
        $db->error(404, '消息不存在');
    }
    
    // 获取评论
    $stmt = $pdo->prepare('SELECT * FROM comments WHERE message_id = ? ORDER BY created_at ASC LIMIT ? OFFSET ?');
    $stmt->execute([$messageId, $limit, $offset]);
    $comments = $stmt->fetchAll();
    
    // 获取总数
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE message_id = ?');
    $stmt->execute([$messageId]);
    $total = $stmt->fetchColumn();
    
    $db->success([
        'comments' => $comments,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

/**
 * 发表评论
 */
function createComment($params, $body) {
    global $pdo, $db, $config;
    
    $messageId = intval($params['id']);
    $content = trim($body['content'] ?? '');
    $parentId = $body['parent_id'] ?? null;
    $user = requireAuth();
    
    // 验证内容
    if (empty($content)) {
        $db->error(400, '评论内容不能为空');
    }
    
    if (strlen($content) > $config['moderation']['max_comment_length']) {
        $db->error(400, '评论过长');
    }
    
    // 检查消息是否存在
    $stmt = $pdo->prepare('SELECT id, user_id FROM messages WHERE id = ?');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    if (!$message) {
        $db->error(404, '消息不存在');
    }
    
    // 如果是回复，检查父评论是否存在
    if ($parentId) {
        $stmt = $pdo->prepare('SELECT id FROM comments WHERE id = ? AND message_id = ?');
        $stmt->execute([$parentId, $messageId]);
        if (!$stmt->fetch()) {
            $db->error(400, '父评论不存在');
        }
    }
    
    // 创建评论
    $stmt = $pdo->prepare('INSERT INTO comments (message_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$messageId, $user['id'], $content, $parentId]);
    
    $commentId = $pdo->lastInsertId();
    
    // 更新评论数
    $stmt = $pdo->prepare('UPDATE messages SET comment_count = comment_count + 1 WHERE id = ?');
    $stmt->execute([$messageId]);
    
    // 发送通知
    $notifyUserId = null;
    $notifyType = 'comment';
    $notifyTitle = '有人评论了你的消息';
    
    if ($parentId) {
        // 回复评论
        $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
        $stmt->execute([$parentId]);
        $parentComment = $stmt->fetch();
        if ($parentComment && $parentComment['user_id']) {
            $notifyUserId = $parentComment['user_id'];
            $notifyType = 'reply';
            $notifyTitle = '有人回复了你的评论';
        }
    } elseif ($message['user_id']) {
        // 评论消息
        $notifyUserId = $message['user_id'];
    }
    
    // 避免给自己发通知
    if ($notifyUserId && $notifyUserId !== $user['id']) {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, content, related_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $notifyUserId,
            $notifyType,
            $notifyTitle,
            '用户"' . $user['username'] . '"的评论: ' . mb_substr($content, 0, 50),
            $messageId
        ]);
    }
    
    $db->created([
        'id' => $commentId,
        'message_id' => $messageId,
        'content' => $content,
        'parent_id' => $parentId,
        'created_at' => date('Y-m-d H:i:s')
    ], '评论成功');
}
