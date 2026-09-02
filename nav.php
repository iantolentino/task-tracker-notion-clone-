<?php

function nav_css(): string
{
    return '
        header.app-nav {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .nav-left, .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nav-title {
            color: #0f172a;
            font-size: 19px;
            font-weight: 700;
            text-decoration: none;
        }
        .nav-sub {
            font-size: 12px;
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .welcome-chip {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .nav-link {
            color: #64748b;
            font-size: 13px;
            text-decoration: none;
            padding: 6px 8px;
            border-radius: 6px;
        }
        .nav-link:hover {
            color: #0f172a;
            background: #f8fafc;
        }
        .nav-link.active {
            color: #1d4ed8;
            background: #eff6ff;
            font-weight: 700;
        }
        .nav-button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: #2563eb;
            color: #fff;
        }
        @media (max-width: 760px) {
            header.app-nav {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }
            .nav-left, .nav-links {
                flex-wrap: wrap;
            }
        }
    ';
}

function render_nav(string $active = ''): void
{
    $user = current_user();
    $username = htmlspecialchars($user['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
    $activeClass = fn($name) => $active === $name ? ' active' : '';
    ?>
    <header class="app-nav">
        <div class="nav-left">
            <span class="welcome-chip">Welcome, <?php echo $username; ?></span>
            <a class="nav-title" href="./">Tasks Tracker</a>
            <span class="nav-sub">Marketing &amp; Multimedia</span>
        </div>
        <nav class="nav-links">
            <a class="nav-link<?php echo $activeClass('tasks'); ?>" href="./">Tasks</a>
            <a class="nav-link<?php echo $activeClass('settings'); ?>" href="settings.php">Settings</a>
            <a class="nav-link" href="logout.php">Logout</a>
            <?php if ($active === 'tasks'): ?>
                <button class="nav-button" onclick="openModal()">+ Add Task</button>
            <?php endif; ?>
        </nav>
    </header>
    <?php
}
