<?php
declare(strict_types=1);

function role_label(string $role): string {
    switch ($role) {
        case 'ADMIN':
            return 'System Administrator';
        case 'MANAGER':
            return 'Manager';
        case 'RENTAL_STAFF':
            return 'Rental Staff';
        default:
            return $role;
    }
}

function render_header(string $title, string $active = ''): void {
    $user = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : array();
    $username = isset($user['username']) ? (string)$user['username'] : '';
    $role = isset($user['role']) ? (string)$user['role'] : '';

    $items = array(
        array('dashboard.php', '', 'Dashboard')
    );

    if ($role === 'MANAGER' || $role === 'RENTAL_STAFF') {
        $items[] = array('operations.php', '', 'Operations');
    }

    if ($role === 'MANAGER') {
        $items[] = array('reports.php', '', 'Reports');
    }

    if ($role === 'ADMIN') {
        $items[] = array('administration.php', '', 'Administration');
    }

echo '<!doctype html>';
    echo '<html><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="assets/style.css">';
    echo '</head><body>';

    echo '<header class="topbar">';
    echo '<div class="brand">';
    
    echo '<div><strong>Car Rental</strong><small>Management System</small></div>';
    echo '</div>';

    echo '<div class="userarea">';
    echo '<span class="role">' . h(role_label($role)) . '</span>';
    echo '<span class="account-name">' . h($username) . '</span>';
    echo '<a class="account-action" href="change_password.php">Change Password</a>';
    echo '<a class="logout-link" href="logout.php">Logout</a>';
    echo '</div>';
    echo '</header>';

    echo '<div class="layout">';
    echo '<aside class="sidebar">';

    foreach ($items as $item) {
        $href = $item[0];
        $icon = $item[1];
        $label = $item[2];
        $class = ($active === $href) ? 'nav active' : 'nav';
        echo '<a class="' . $class . '" href="' . h($href) . '">';
        echo '<span>' . $icon . '</span>' . h($label) . '</a>';
    }

    echo '</aside>';
    echo '<main class="main">';
    echo '<div class="page-title"><h1>' . h($title) . '</h1></div>';
}

function render_footer(): void {
    echo '';
    echo '</main></div></body></html>';
}
