-- 匿名墙数据库初始化脚本 (SQLite)
-- 运行方式: sqlite3 database.sqlite < database.sql

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    role TEXT DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 消息表
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content TEXT NOT NULL,
    type TEXT DEFAULT 'confession' CHECK (
        type IN (
            'confession',
            'secret',
            'question'
        )
    ),
    status TEXT DEFAULT 'approved' CHECK (
        status IN (
            'pending',
            'approved',
            'rejected'
        )
    ),
    is_anonymous INTEGER DEFAULT 1,
    user_id INTEGER,
    like_count INTEGER DEFAULT 0,
    comment_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

-- 评论表
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER NOT NULL,
    user_id INTEGER,
    content TEXT NOT NULL,
    parent_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES comments (id) ON DELETE CASCADE
);

-- 点赞表
CREATE TABLE IF NOT EXISTS likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE (message_id, user_id)
);

-- 通知表
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK (
        type IN (
            'like',
            'comment',
            'reply',
            'system'
        )
    ),
    title TEXT NOT NULL,
    content TEXT,
    is_read INTEGER DEFAULT 0,
    related_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- 私信表
CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
);

-- 用户设置表
CREATE TABLE IF NOT EXISTS user_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    notifications_enabled INTEGER DEFAULT 1,
    email_notifications INTEGER DEFAULT 0,
    profile_visible INTEGER DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- 密码重置令牌表
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- 举报表
CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER NOT NULL,
    user_id INTEGER,
    reason TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (
        status IN (
            'pending',
            'reviewed',
            'dismissed'
        )
    ),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

-- 索引
CREATE INDEX IF NOT EXISTS idx_messages_status ON messages (status);

CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages (created_at);

CREATE INDEX IF NOT EXISTS idx_messages_user_id ON messages (user_id);

CREATE INDEX IF NOT EXISTS idx_comments_message_id ON comments (message_id);

CREATE INDEX IF NOT EXISTS idx_comments_parent_id ON comments (parent_id);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications (user_id);

CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications (is_read);

CREATE INDEX IF NOT EXISTS idx_conversations_sender ON conversations (sender_id);

CREATE INDEX IF NOT EXISTS idx_conversations_receiver ON conversations (receiver_id);

CREATE INDEX IF NOT EXISTS idx_likes_message_id ON likes (message_id);

CREATE INDEX IF NOT EXISTS idx_likes_user_id ON likes (user_id);

CREATE INDEX IF NOT EXISTS idx_reports_message_id ON reports (message_id);

-- 插入默认管理员账号 (密码: admin123)
INSERT INTO
    users (
        username,
        password,
        email,
        role
    )
VALUES (
        'admin',
        '$2y$12$mUAi0IjctzQ83RaCixGoOOWwmRojIsHCjI1tMJm/mxpIuS8b7jSNm',
        'admin@ruc.edu.cn',
        'admin'
    );

-- 插入测试用户 (密码: test123)
INSERT INTO
    users (
        username,
        password,
        email,
        role
    )
VALUES (
        'testuser',
        '$2y$12$6wwVSca2WWuBla0yIYjYtePfr01c.q21n1vuiMvnITvLymT9Hum72',
        'test@ruc.edu.cn',
        'user'
    );
