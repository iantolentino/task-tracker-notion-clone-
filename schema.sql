CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL DEFAULT '',
    assignee VARCHAR(255) NOT NULL DEFAULT '',
    due_date DATE NULL,
    effort VARCHAR(50) NOT NULL DEFAULT '',
    priority VARCHAR(50) NOT NULL DEFAULT '',
    status VARCHAR(100) NOT NULL DEFAULT 'Not started',
    type VARCHAR(255) NOT NULL DEFAULT '',
    `desc` TEXT NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uploads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NULL,
    ticket_id BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NOT NULL DEFAULT '',
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_uploads_task_id (task_id),
    INDEX idx_uploads_ticket_id (ticket_id),
    INDEX idx_uploads_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requester_name VARCHAR(120) NOT NULL,
    requester_email VARCHAR(255) NOT NULL,
    department VARCHAR(120) NOT NULL DEFAULT '',
    assignee VARCHAR(120) NOT NULL DEFAULT 'Akio',
    due_date DATE NULL,
    due_time VARCHAR(5) NOT NULL DEFAULT '',
    ticket_type VARCHAR(120) NOT NULL,
    estimated_cost DECIMAL(12,2) NULL,
    subject VARCHAR(180) NOT NULL,
    category VARCHAR(255) NOT NULL DEFAULT 'Other',
    priority VARCHAR(20) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    effort VARCHAR(50) NOT NULL DEFAULT '',
    business_impact TEXT NULL,
    other_type VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'Open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_tickets_status (status),
    INDEX idx_tickets_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    author_type VARCHAR(20) NOT NULL DEFAULT 'admin',
    author_name VARCHAR(120) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ticket_comments_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_todos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NULL,
    ticket_id BIGINT UNSIGNED NULL,
    text VARCHAR(500) NOT NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_task_todos_task_id (task_id),
    INDEX idx_task_todos_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
