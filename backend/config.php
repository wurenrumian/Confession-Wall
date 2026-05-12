<?php
/**
 * 配置文件
 */

return [
    // 数据库配置 (MySQL)
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: 3306,
        'dbname' => getenv('DB_NAME') ?: 'confession_wall',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    
    // JWT 配置
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production',
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
