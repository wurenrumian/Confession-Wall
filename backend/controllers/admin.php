<?php
/**
 * 管理员控制器
 */

/**
 * 获取待审核消息列表
 */
function getPendingMessages($params, $body) {
    global $pdo, $db, $config;
    
    requireAdmin();
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = trim($_GET['status'] ?? '');
    
    $validStatuses = ['pending', 'approved', 'rejected'];
    $requireApproval = $config['moderation']['require_approval'] ?? true;
    $defaultStatus = $requireApproval ? 'pending' : '';
    $statusFilter = in_array($status, $validStatuses, true) ? $status : $defaultStatus;

    if ($statusFilter) {
        $stmt = $pdo->prepare('
            SELECT m.*, u.username
            FROM messages m
            LEFT JOIN users u ON u.id = m.user_id
            WHERE m.status = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$statusFilter, $limit, $offset]);
    } else {
        $stmt = $pdo->prepare('
            SELECT m.*, u.username
            FROM messages m
            LEFT JOIN users u ON u.id = m.user_id
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$limit, $offset]);
    }
    $messages = $stmt->fetchAll();
    
    if ($statusFilter) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE status = ?');
        $stmt->execute([$statusFilter]);
        $total = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->query('SELECT COUNT(*) FROM messages');
        $total = $stmt->fetchColumn();
    }

    $statsStmt = $pdo->query('
        SELECT
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) AS rejected
        FROM messages
    ');
    $stats = $statsStmt->fetch() ?: ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    
    $db->success([
        'messages' => $messages,
        'filter' => [
            'status' => $statusFilter ?: 'all'
        ],
        'stats' => [
            'pending' => intval($stats['pending'] ?? 0),
            'approved' => intval($stats['approved'] ?? 0),
            'rejected' => intval($stats['rejected'] ?? 0)
        ],
        'pagination' => paginate($total, $page, $limit)
    ]);
}

/**
 * 审核消息
 */
function reviewMessage($params, $body) {
    global $pdo, $db;
    
    requireAdmin();
    
    $id = intval($params['id']);
    $status = $body['status'] ?? '';
    $reason = $body['reason'] ?? '';
    
    if (!in_array($status, ['approved', 'rejected', 'pending'])) {
        $db->error(400, '无效的审核状态');
    }
    
    // 检查消息是否存在
    $stmt = $pdo->prepare('SELECT id, user_id FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        $db->error(404, '消息不存在');
    }
    
    // 更新状态
    $stmt = $pdo->prepare('UPDATE messages SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    
    // 发送通知给作者
    if ($message['user_id']) {
        $notifyContent = $status === 'approved' ? '您的消息已审核通过' : '您的消息未通过审核';
        if ($status === 'rejected' && !empty($reason)) {
            $notifyContent .= '，原因: ' . $reason;
        }
        
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, content, related_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $message['user_id'],
            'system',
            '消息审核通知',
            $notifyContent,
            $id
        ]);
    }
    
    $db->success([
        'id' => $id,
        'status' => $status
    ], '审核成功');
}

/**
 * 管理员删除消息
 */
function adminDeleteMessage($params, $body) {
    global $pdo, $db;
    
    requireAdmin();
    
    $id = intval($params['id']);
    
    // 检查消息是否存在
    $stmt = $pdo->prepare('SELECT id, user_id FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        $db->error(404, '消息不存在');
    }
    
    // 删除消息
    $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    
    // 发送通知给作者
    if ($message['user_id']) {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, content, related_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $message['user_id'],
            'system',
            '消息被删除',
            '您的消息因违规已被管理员删除',
            $id
        ]);
    }
    
    $db->success(null, '删除成功');
}
