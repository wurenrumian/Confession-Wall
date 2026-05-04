<?php
/**
 * 评论控制器
 */

function getComments($params, $body) {
    global $pdo, $db;

    $messageId = intval($params['id']);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare('SELECT id FROM messages WHERE id = ?');
    $stmt->execute([$messageId]);
    if (!$stmt->fetch()) {
        $db->error(404, '消息不存在');
    }

    $stmt = $pdo->prepare('SELECT * FROM comments WHERE message_id = ? ORDER BY created_at ASC LIMIT ? OFFSET ?');
    $stmt->execute([$messageId, $limit, $offset]);
    $comments = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE message_id = ?');
    $stmt->execute([$messageId]);
    $total = $stmt->fetchColumn();

    $db->success([
        'comments' => $comments,
        'pagination' => paginate($total, $page, $limit)
    ]);
}

function createComment($params, $body) {
    global $pdo, $db, $config;

    $messageId = intval($params['id']);
    $content = trim($body['content'] ?? '');
    $parentId = $body['parent_id'] ?? null;
    $user = requireAuth();

    if ($content === '') {
        $db->error(400, '评论内容不能为空');
    }

    if (mb_strlen($content) > $config['moderation']['max_comment_length']) {
        $db->error(400, '评论过长');
    }

    $stmt = $pdo->prepare('SELECT id, user_id FROM messages WHERE id = ?');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    if (!$message) {
        $db->error(404, '消息不存在');
    }

    if ($parentId) {
        $stmt = $pdo->prepare('SELECT id FROM comments WHERE id = ? AND message_id = ?');
        $stmt->execute([$parentId, $messageId]);
        if (!$stmt->fetch()) {
            $db->error(400, '父评论不存在');
        }
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('INSERT INTO comments (message_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$messageId, $user['id'], $content, $parentId]);
        $commentId = $pdo->lastInsertId();

        $stmt = $pdo->prepare('UPDATE messages SET comment_count = comment_count + 1 WHERE id = ?');
        $stmt->execute([$messageId]);

        $notifyUserId = null;
        $notifyType = 'comment';
        $notifyTitle = '有人评论了你的帖子';

        if ($parentId) {
            $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
            $stmt->execute([$parentId]);
            $parentComment = $stmt->fetch();
            if ($parentComment && $parentComment['user_id']) {
                $notifyUserId = $parentComment['user_id'];
                $notifyType = 'reply';
                $notifyTitle = '有人回复了你的评论';
            }
        } elseif ($message['user_id']) {
            $notifyUserId = $message['user_id'];
        }

        if ($notifyUserId && intval($notifyUserId) !== intval($user['id'])) {
            $stmt = $pdo->prepare('
                INSERT INTO notifications (user_id, type, title, content, related_id)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $notifyUserId,
                $notifyType,
                $notifyTitle,
                '用户“' . $user['username'] . '”说：' . mb_substr($content, 0, 50),
                $messageId
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $db->error(500, '评论失败，请稍后重试');
    }

    $db->created([
        'id' => intval($commentId),
        'message_id' => $messageId,
        'content' => $content,
        'parent_id' => $parentId,
        'created_at' => date('Y-m-d H:i:s')
    ], '评论成功');
}
