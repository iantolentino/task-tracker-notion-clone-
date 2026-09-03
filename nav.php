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
        .nav-brand {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        .nav-brand img {
            display: block;
            width: 154px;
            height: 38px;
            object-fit: contain;
            object-position: left center;
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
    $activeClass = fn($name) => $active === $name ? ' active' : '';
    $user = current_user();
    ?>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="app-nav" id="appNav">
        <div class="nav-left">
            <a class="nav-brand" href="./"><img src="assets/stratastaff-logo.png" alt="Strata Staff"></a>
            <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="primaryNav" onclick="toggleAppNav(this)">
                <span></span><span></span><span></span>
            </button>
        </div>
        <nav class="nav-links" id="primaryNav" aria-label="Primary navigation">
            <a class="nav-link<?php echo $activeClass('tasks'); ?>" href="./">Tasks</a>
            <a class="nav-link<?php echo $activeClass('tickets'); ?>" href="tickets.php">Tickets</a>
            <a class="nav-link<?php echo $activeClass('settings'); ?>" href="settings.php">Settings</a>
            <?php if (is_super_admin()): ?><a class="nav-link<?php echo $activeClass('db'); ?>" href="db-view.php">Database</a><?php endif; ?>
        </nav>
        <div class="nav-account">
            <div><strong><?php echo htmlspecialchars($user['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? 'admin')), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <a class="nav-logout" href="logout.php">Log out</a>
        </div>
    </aside>
    <script>
        function toggleAppNav(button) {
            const nav = document.getElementById('appNav');
            const open = nav.classList.toggle('nav-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    </script>
    <?php
}
