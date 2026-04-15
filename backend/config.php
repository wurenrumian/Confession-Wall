<?php
/**
 * 配置文件
 */

return [
    // 数据库配置 (MySQL)
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'confession_wall',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    
    // JWT 配置
    'jwt' => [
        'secret' => 'your-secret-key-change-in-production',
        'expiration' => 86400,        // Token 有效期: 24小时
        'refresh_expiration' => 604800, // Refresh Token 有效期: 7天
    ],
    
    // 应用配置
    'app' => [
        'name' => 'RUC Confession Wall',
        'debug' => true,
        'timezone' => 'Asia/Shanghai',
    ],
    
    // 内容审核配置
    'moderation' => [
        'require_approval' => false,  // 是否需要审核（false = 默认放行）
        'max_content_length' => 500,
        'max_comment_length' => 200,
    ],
    
    // 敏感词过滤 (预留)
    'filter' => [
        'enabled' => false,
        'words' => [],
    ],
];
