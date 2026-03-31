<?php
/**
 * 消息控制器
 */

/**
 * 获取消息列表
 */
function getMessages($params, $body) {
    global $pdo, $db;
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $sort = $_GET['sort'] ?? 'newest';
    $offset = ($page - 1) * $limit;
    
    // 排序
    $orderBy = 'm.created_at DESC';
    if ($sort === 'hot') {
        $orderBy = 'm.like_count DESC, m.created_at DESC';
    } elseif ($sort === 'top') {
        $orderBy = 'm.like_count + m.comment_count DESC, m.created_at DESC';
    }
    
    // 获取当前用户点赞状态
    $user = getCurrentUser();
    $userId = $user ? $user['id'] : 0;
    
    // 查询消息
    $sql = "SELECT m.*, 
            (SELECT COUNT(*) FROM likes WHERE message_id = m.id AND user_id = $userId) as is_liked
            FROM messages m
            WHERE m.status = 'approved'
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit, $offset]);
    $messages = $stmt->fetchAll();
    
    // 格式化
    foreach ($messages as &$msg) {
        $msg['is_liked'] = (bool)$msg['is_liked'];
        unset($msg['user_id']); // 不暴露发布者
    }
    
    // 获取总数
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE status = 'approved'");
    $total = $stmt->fetchColumn();
    
    $db->success([
        'messages' => $messages,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

/**
 * 搜索消息
 */
function searchMessages($params, $body) {
    global $pdo, $db;
    
    $query = trim($_GET['q'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    if (empty($query)) {
        $db->error(400, '搜索关键词不能为空');
    }
    
    $user = getCurrentUser();
    $userId = $user ? $user['id'] : 0;
    
    // 搜索消息
    $sql = "SELECT m.*,
            (SELECT COUNT(*) FROM likes WHERE message_id = m.id AND user_id = $userId) as is_liked
            FROM messages m
            WHERE m.status = 'approved' AND m.content LIKE ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $query . '%', $limit, $offset]);
    $messages = $stmt->fetchAll();
    
    foreach ($messages as &$msg) {
        $msg['is_liked'] = (bool)$msg['is_liked'];
        unset($msg['user_id']);
    }
    
    // 获取总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE status = 'approved' AND content LIKE ?");
    $stmt->execute(['%' . $query . '%']);
    $total = $stmt->fetchColumn();
    
    $db->success([
        'messages' => $messages,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

/**
 * 获取单条消息
 */
function getMessage($params, $body) {
    global $pdo, $db;
    
    $id = intval($params['id']);
    
    $user = getCurrentUser();
    $userId = $user ? $user['id'] : 0;
    
    $stmt = $pdo->prepare("SELECT m.*,
        (SELECT COUNT(*) FROM likes WHERE message_id = m.id AND user_id = $userId) as is_liked
        FROM messages m WHERE m.id = ?");
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        $db->error(404, '消息不存在');
    }
    
    // 获取评论
    $stmt = $pdo->prepare('SELECT * FROM comments WHERE message_id = ? ORDER BY created_at ASC');
    $stmt->execute([$id]);
    $comments = $stmt->fetchAll();
    
    $message['is_liked'] = (bool)$message['is_liked'];
    $message['comments'] = $comments;
    unset($message['user_id']);
    
    $db->success($message);
}

/**
 * 发布消息
 */
function createMessage($params, $body) {
    global $pdo, $db, $config;
    
    $content = trim($body['content'] ?? '');
    $type = $body['type'] ?? 'confession';
    $isAnonymous = $body['is_anonymous'] ?? true;
    
    // 验证
    if (empty($content)) {
        $db->error(400, '内容不能为空');
    }
    
    if (strlen($content) > $config['moderation']['max_content_length']) {
        $db->error(400, '内容过长');
    }
    
    if (!in_array($type, ['confession', 'secret', 'question'])) {
        $type = 'confession';
    }
    
    $user = getCurrentUser();
    $userId = $user ? $user['id'] : null;
    
    // 状态
    $status = $config['moderation']['require_approval'] ? 'pending' : 'approved';
    
    $stmt = $pdo->prepare('INSERT INTO messages (content, type, status, is_anonymous, user_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$content, $type, $status, $isAnonymous ? 1 : 0, $userId]);
    
    $messageId = $pdo->lastInsertId();
    
    $db->created([
        'id' => $messageId,
        'content' => $content,
        'type' => $type,
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s')
    ], $status === 'pending' ? '发布成功，等待审核' : '发布成功');
}

/**
 * 删除消息
 */
function deleteMessage($params, $body) {
    global $pdo, $db;
    
    $id = intval($params['id']);
    $user = requireAuth();
    
    // 检查权限：只能删除自己的消息或管理员
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        $db->error(404, '消息不存在');
    }
    
    if ($message['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
        $db->error(403, '无权删除此消息');
    }
    
    // 删除消息（级联删除评论和点赞）
    $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    
    $db->success(null, '删除成功');
}

/**
 * 点赞/取消点赞
 */
function likeMessage($params, $body) {
    global $pdo, $db;
    
    $id = intval($params['id']);
    $user = requireAuth();
    
    // 检查消息是否存在
    $stmt = $pdo->prepare('SELECT id FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        $db->error(404, '消息不存在');
    }
    
    // 检查是否已点赞
    $stmt = $pdo->prepare('SELECT id FROM likes WHERE message_id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $like = $stmt->fetch();
    
    if ($like) {
        // 取消点赞
        $stmt = $pdo->prepare('DELETE FROM likes WHERE id = ?');
        $stmt->execute([$like['id']]);
        
        $stmt = $pdo->prepare('UPDATE messages SET like_count = like_count - 1 WHERE id = ?');
        $stmt->execute([$id]);
        
        $liked = false;
    } else {
        // 点赞
        $stmt = $pdo->prepare('INSERT INTO likes (message_id, user_id) VALUES (?, ?)');
        $stmt->execute([$id, $user['id']]);
        
        $stmt = $pdo->prepare('UPDATE messages SET like_count = like_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
        
        $liked = true;
        
        // 发送通知（如果消息作者不是当前用户）
        $stmt = $pdo->prepare('SELECT user_id FROM messages WHERE id = ?');
        $stmt->execute([$id]);
        $message = $stmt->fetch();
        
        if ($message && $message['user_id'] && $message['user_id'] !== $user['id']) {
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, content, related_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $message['user_id'],
                'like',
                '有人赞了你的消息',
                '用户"' . $user['username'] . '"赞了你的表白',
                $id
            ]);
        }
    }
    
    // 获取最新点赞数
    $stmt = $pdo->prepare('SELECT like_count FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $likeCount = $stmt->fetchColumn();
    
    $db->success([
        'liked' => $liked,
        'like_count' => $likeCount
    ], $liked ? '点赞成功' : '取消点赞');
}

/**
 * 举报消息
 */
function reportMessage($params, $body) {
    global $pdo, $db;
    
    $id = intval($params['id']);
    $reason = trim($body['reason'] ?? '');
    $user = requireAuth();
    
    if (empty($reason)) {
        $db->error(400, '举报原因不能为空');
    }
    
    if (strlen($reason) > 200) {
        $db->error(400, '举报原因过长');
    }
    
    // 检查消息是否存在
    $stmt = $pdo->prepare('SELECT id FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        $db->error(404, '消息不存在');
    }
    
    // 创建举报记录
    $stmt = $pdo->prepare('INSERT INTO reports (message_id, user_id, reason) VALUES (?, ?, ?)');
    $stmt->execute([$id, $user['id'], $reason]);
    
    $db->success(null, '举报成功，我们会尽快处理');
}

/**
 * 获取用户发布的消息
 */
function getUserMessages($params, $body) {
    global $pdo, $db;
    
    $userId = intval($params['userId']);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $currentUser = getCurrentUser();
    
    // 查询消息
    $sql = "SELECT m.*,
            (SELECT COUNT(*) FROM likes WHERE message_id = m.id AND user_id = ?) as is_liked
            FROM messages m
            WHERE m.user_id = ? AND m.status = 'approved'
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentUser ? $currentUser['id'] : 0, $userId, $limit, $offset]);
    $messages = $stmt->fetchAll();
    
    foreach ($messages as &$msg) {
        $msg['is_liked'] = (bool)$msg['is_liked'];
        unset($msg['user_id']);
    }
    
    // 获取总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$userId]);
    $total = $stmt->fetchColumn();
    
    $db->success([
        'messages' => $messages,
        'pagination' => paginate($total, $page, $limit)
    ]);
}
