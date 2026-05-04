<?php
/**
 * 私信控制器
 */

function getConversations($params, $body) {
    global $pdo, $db;

    $user = requireAuth();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $sql = "
        SELECT
            c.id,
            c.sender_id,
            c.receiver_id,
            c.content,
            c.is_read,
            c.created_at,
            CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END AS other_user_id
        FROM conversations c
        INNER JOIN (
            SELECT
                MAX(id) AS latest_id
            FROM conversations
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
        ) latest ON latest.latest_id = c.id
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $limit, $offset]);
    $conversations = $stmt->fetchAll();

    $sql = "
        SELECT COUNT(DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END)
        FROM conversations
        WHERE sender_id = ? OR receiver_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    $total = $stmt->fetchColumn();

    foreach ($conversations as &$conversation) {
        $otherUserId = intval($conversation['other_user_id']);

        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$otherUserId]);
        $otherUser = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
        $stmt->execute([$otherUserId, $user['id']]);
        $unreadCount = intval($stmt->fetchColumn());

        $conversation['user'] = $otherUser;
        $conversation['last_message'] = $conversation['content'];
        $conversation['last_message_time'] = $conversation['created_at'];
        $conversation['unread_count'] = $unreadCount;

        unset($conversation['sender_id'], $conversation['receiver_id'], $conversation['content'], $conversation['other_user_id']);
    }

    $db->success([
        'conversations' => $conversations,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

function getConversation($params, $body) {
    global $pdo, $db;

    $user = requireAuth();
    $otherUserId = intval($params['userId']);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$otherUserId]);
    $otherUser = $stmt->fetch();
    if (!$otherUser) {
        $db->error(404, '用户不存在');
    }

    $sql = "
        SELECT *
        FROM conversations
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at DESC, id DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $otherUserId, $otherUserId, $user['id'], $limit, $offset]);
    $messages = $stmt->fetchAll();

    $stmt = $pdo->prepare('UPDATE conversations SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
    $stmt->execute([$otherUserId, $user['id']]);

    $sql = "
        SELECT COUNT(*)
        FROM conversations
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $otherUserId, $otherUserId, $user['id']]);
    $total = $stmt->fetchColumn();

    $db->success([
        'messages' => array_reverse($messages),
        'user' => $otherUser,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

function sendMessage($params, $body) {
    global $pdo, $db;

    $user = requireAuth();
    $receiverId = intval($params['userId']);
    $content = trim($body['content'] ?? '');

    if ($content === '') {
        $db->error(400, '消息内容不能为空');
    }

    if (mb_strlen($content) > 500) {
        $db->error(400, '消息过长');
    }

    if ($receiverId === intval($user['id'])) {
        $db->error(400, '不能给自己发私信');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$receiverId]);
    if (!$stmt->fetch()) {
        $db->error(404, '用户不存在');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('INSERT INTO conversations (sender_id, receiver_id, content) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $receiverId, $content]);
        $messageId = $pdo->lastInsertId();

        $stmt = $pdo->prepare('
            INSERT INTO notifications (user_id, type, title, content, related_id)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $receiverId,
            'system',
            '你收到一条新私信',
            '用户“' . $user['username'] . '”给你发来一条消息',
            $messageId
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $db->error(500, '发送失败，请稍后重试');
    }

    $db->created([
        'id' => intval($messageId),
        'sender_id' => intval($user['id']),
        'receiver_id' => $receiverId,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ], '发送成功');
}

function searchUsers($params, $body) {
    global $pdo, $db;

    $user = requireAuth();
    $query = trim($_GET['q'] ?? '');

    if ($query === '') {
        $db->success(['users' => []]);
    }

    $stmt = $pdo->prepare('
        SELECT id, username
        FROM users
        WHERE id <> ? AND username LIKE ?
        ORDER BY username ASC
        LIMIT 10
    ');
    $stmt->execute([$user['id'], '%' . $query . '%']);

    $db->success([
        'users' => $stmt->fetchAll()
    ]);
}
