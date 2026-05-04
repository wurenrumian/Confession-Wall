<?php
/**
 * 通知控制器
 */

function getNotifications($params, $body) {
    global $pdo, $db;

    $user = requireAuth();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
    $stmt->execute([$user['id'], $limit, $offset]);
    $notifications = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $total = $stmt->fetchColumn();

    foreach ($notifications as &$notification) {
        $notification['is_read'] = (bool)$notification['is_read'];
    }

    $db->success([
        'notifications' => $notifications,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

function markNotificationRead($params, $body) {
    global $pdo, $db;

    $user = requireAuth();
    $id = intval($params['id']);

    $stmt = $pdo->prepare('SELECT id FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    if (!$stmt->fetch()) {
        $db->error(404, '通知不存在');
    }

    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
    $stmt->execute([$id]);

    $db->success(null, '已标记为已读');
}

function clearNotifications($params, $body) {
    global $pdo, $db;

    $user = requireAuth();

    $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = ?');
    $stmt->execute([$user['id']]);

    $db->success(null, '清空成功');
}
