<?php
/**
 * 设置控制器
 */

/**
 * 获取用户设置
 */
function getSettings($params, $body) {
    global $pdo, $db;
    
    $user = requireAuth();
    
    $stmt = $pdo->prepare('SELECT * FROM user_settings WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // 返回默认设置
        $db->success([
            'notifications_enabled' => true,
            'email_notifications' => false,
            'profile_visible' => true
        ]);
        return;
    }
    
    $db->success([
        'notifications_enabled' => (bool)$settings['notifications_enabled'],
        'email_notifications' => (bool)$settings['email_notifications'],
        'profile_visible' => (bool)$settings['profile_visible']
    ]);
}

/**
 * 更新用户设置
 */
function updateSettings($params, $body) {
    global $pdo, $db;
    
    $user = requireAuth();
    
    $notificationsEnabled = isset($body['notifications_enabled']) ? (bool)$body['notifications_enabled'] : true;
    $emailNotifications = isset($body['email_notifications']) ? (bool)$body['email_notifications'] : false;
    $profileVisible = isset($body['profile_visible']) ? (bool)$body['profile_visible'] : true;
    
    // 检查是否存在设置记录
    $stmt = $pdo->prepare('SELECT id FROM user_settings WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        $stmt = $pdo->prepare('UPDATE user_settings SET notifications_enabled = ?, email_notifications = ?, profile_visible = ? WHERE user_id = ?');
        $stmt->execute([$notificationsEnabled, $emailNotifications, $profileVisible, $user['id']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO user_settings (user_id, notifications_enabled, email_notifications, profile_visible) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['id'], $notificationsEnabled, $emailNotifications, $profileVisible]);
    }
    
    $db->success([
        'notifications_enabled' => $notificationsEnabled,
        'email_notifications' => $emailNotifications,
        'profile_visible' => $profileVisible
    ], '设置已更新');
}
