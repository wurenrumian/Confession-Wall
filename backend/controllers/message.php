<?php
/**
 * 消息控制器
 */

function getMessages($params, $body) {
    global $pdo, $db;

    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $sort = $_GET['sort'] ?? 'newest';
    $offset = ($page - 1) * $limit;

    $orderBy = 'm.created_at DESC';
    if ($sort === 'hot') {
        $orderBy = 'm.like_count DESC, m.created_at DESC';
    } elseif ($sort === 'top') {
        $orderBy = 'm.like_count + m.comment_count DESC, m.created_at DESC';
    }

    $user = getCurrentUser();
    $userId = $user ? intval($user['id']) : 0;

    $sql = "SELECT m.*,
            EXISTS(SELECT 1 FROM likes WHERE message_id = m.id AND user_id = ?) AS is_liked
            FROM messages m
            WHERE m.status = 'approved'
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $limit, $offset]);
    $messages = $stmt->fetchAll();

    foreach ($messages as &$message) {
        $message['is_liked'] = (bool)$message['is_liked'];
        unset($message['user_id']);
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE status = 'approved'");
    $total = $stmt->fetchColumn();

    $db->success([
        'messages' => $messages,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

function searchMessages($params, $body) {
    global $pdo, $db;

    $query = trim($_GET['q'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    if ($query === '') {
        $db->error(400, '搜索关键词不能为空');
    }

    $user = getCurrentUser();
    $userId = $user ? intval($user['id']) : 0;

    $sql = "SELECT m.*,
            EXISTS(SELECT 1 FROM likes WHERE message_id = m.id AND user_id = ?) AS is_liked
            FROM messages m
            WHERE m.status = 'approved' AND m.content LIKE ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, '%' . $query . '%', $limit, $offset]);
    $messages = $stmt->fetchAll();

    foreach ($messages as &$message) {
        $message['is_liked'] = (bool)$message['is_liked'];
        unset($message['user_id']);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE status = 'approved' AND content LIKE ?");
    $stmt->execute(['%' . $query . '%']);
    $total = $stmt->fetchColumn();

    $db->success([
        'messages' => $messages,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

function getMessage($params, $body) {
    global $pdo, $db;

    $id = intval($params['id']);
    $user = getCurrentUser();
    $userId = $user ? intval($user['id']) : 0;

    $stmt = $pdo->prepare("
        SELECT m.*,
        EXISTS(SELECT 1 FROM likes WHERE message_id = m.id AND user_id = ?) AS is_liked
        FROM messages m
        WHERE m.id = ?
    ");
    $stmt->execute([$userId, $id]);
    $message = $stmt->fetch();

    if (!$message) {
        $db->error(404, '消息不存在');
    }

    $stmt = $pdo->prepare('SELECT * FROM comments WHERE message_id = ? ORDER BY created_at ASC');
    $stmt->execute([$id]);
    $comments = $stmt->fetchAll();

    $message['is_liked'] = (bool)$message['is_liked'];
    $message['comments'] = $comments;
    unset($message['user_id']);

    $db->success($message);
}

function createMessage($params, $body) {
    global $pdo, $db, $config;

    $content = trim($body['content'] ?? '');
    $type = $body['type'] ?? 'confession';
    $isAnonymous = $body['is_anonymous'] ?? true;

    if ($content === '') {
        $db->error(400, '内容不能为空');
    }

    if (mb_strlen($content) > $config['moderation']['max_content_length']) {
        $db->error(400, '内容过长');
    }

    if (!in_array($type, ['confession', 'secret', 'question'], true)) {
        $type = 'confession';
    }

    $user = getCurrentUser();
    $userId = $user ? $user['id'] : null;
    $status = $config['moderation']['require_approval'] ? 'pending' : 'approved';

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('
            INSERT INTO messages (content, type, status, is_anonymous, user_id)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$content, $type, $status, $isAnonymous ? 1 : 0, $userId]);
        $messageId = $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $db->error(500, '发布失败，请稍后重试');
    }

    $db->created([
        'id' => intval($messageId),
        'content' => $content,
        'type' => $type,
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s')
    ], $status === 'pending' ? '发布成功，等待审核' : '发布成功');
}

function deleteMessage($params, $body) {
    global $pdo, $db;

    $id = intval($params['id']);
    $user = requireAuth();

    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $message = $stmt->fetch();

    if (!$message) {
        $db->error(404, '消息不存在');
    }

    if (intval($message['user_id']) !== intval($user['id']) && $user['role'] !== 'admin') {
        $db->error(403, '无权删除这条消息');
    }

    $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
    $stmt->execute([$id]);

    $db->success(null, '删除成功');
}

function likeMessage($params, $body) {
    global $pdo, $db;

    $id = intval($params['id']);
    $user = requireAuth();

    $stmt = $pdo->prepare('SELECT id, user_id FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    if (!$message) {
        $db->error(404, '消息不存在');
    }

    $stmt = $pdo->prepare('SELECT id FROM likes WHERE message_id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $like = $stmt->fetch();

    $pdo->beginTransaction();

    try {
        if ($like) {
            $stmt = $pdo->prepare('DELETE FROM likes WHERE id = ?');
            $stmt->execute([$like['id']]);

            $stmt = $pdo->prepare('UPDATE messages SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?');
            $stmt->execute([$id]);
            $liked = false;
        } else {
            $stmt = $pdo->prepare('INSERT INTO likes (message_id, user_id) VALUES (?, ?)');
            $stmt->execute([$id, $user['id']]);

            $stmt = $pdo->prepare('UPDATE messages SET like_count = like_count + 1 WHERE id = ?');
            $stmt->execute([$id]);
            $liked = true;

            if ($message['user_id'] && intval($message['user_id']) !== intval($user['id'])) {
                $stmt = $pdo->prepare('
                    INSERT INTO notifications (user_id, type, title, content, related_id)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $message['user_id'],
                    'like',
                    '有人点赞了你的帖子',
                    '用户“' . $user['username'] . '”点赞了你的帖子',
                    $id
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $db->error(500, '点赞失败，请稍后重试');
    }

    $stmt = $pdo->prepare('SELECT like_count FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $likeCount = intval($stmt->fetchColumn());

    $db->success([
        'liked' => $liked,
        'like_count' => $likeCount
    ], $liked ? '点赞成功' : '已取消点赞');
}

function reportMessage($params, $body) {
    global $pdo, $db;

    $id = intval($params['id']);
    $reason = trim($body['reason'] ?? '');
    $user = requireAuth();

    if ($reason === '') {
        $db->error(400, '举报原因不能为空');
    }

    if (mb_strlen($reason) > 200) {
        $db->error(400, '举报原因过长');
    }

    $stmt = $pdo->prepare('SELECT id FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        $db->error(404, '消息不存在');
    }

    $stmt = $pdo->prepare('INSERT INTO reports (message_id, user_id, reason) VALUES (?, ?, ?)');
    $stmt->execute([$id, $user['id'], $reason]);

    $db->success(null, '举报成功，我们会尽快处理');
}

function getUserMessages($params, $body) {
    global $pdo, $db;

    $userId = intval($params['userId']);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $currentUser = getCurrentUser();
    $currentUserId = $currentUser ? intval($currentUser['id']) : 0;

    $sql = "SELECT m.*,
            EXISTS(SELECT 1 FROM likes WHERE message_id = m.id AND user_id = ?) AS is_liked
            FROM messages m
            WHERE m.user_id = ? AND m.status = 'approved'
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentUserId, $userId, $limit, $offset]);
    $messages = $stmt->fetchAll();

    foreach ($messages as &$message) {
        $message['is_liked'] = (bool)$message['is_liked'];
        unset($message['user_id']);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$userId]);
    $total = $stmt->fetchColumn();

    $db->success([
        'messages' => $messages,
        'pagination' => paginate($total, $page, $limit)
    ]);
}
