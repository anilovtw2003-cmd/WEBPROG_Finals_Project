<?php
require_once 'config.php';

if (!isLoggedIn()) redirect('auth.php?action=login');
if (!isUserActive()) { session_destroy(); redirect('auth.php?action=login&error=deactivated'); }

// handle admin actions
$adminMessage = '';
$adminError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $db = getDBConnection();
    if ($db && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['admin_action'] ?? '';
        $userId = intval($_POST['user_id'] ?? 0);
        try {
            switch ($action) {
                case 'toggle_admin':
                    if ($userId != getCurrentUserId()) {
                        $stmt = $db->prepare("SELECT administrator_id FROM Administrator WHERE user_id = ?");
                        $stmt->execute([$userId]);
                        if ($stmt->fetch()) {
                            $db->prepare("DELETE FROM Administrator WHERE user_id = ?")->execute([$userId]);
                            $adminMessage = 'Admin privileges removed.';
                        } else {
                            $db->prepare("INSERT INTO Administrator (user_id, role) VALUES (?, 'admin')")->execute([$userId]);
                            $adminMessage = 'User is now an administrator.';
                        }
                    }
                    break;
                case 'toggle_active':
                    $stmt = $db->prepare("SELECT is_active FROM Users WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    if ($user && $userId != getCurrentUserId()) {
                        $db->prepare("UPDATE Users SET is_active = ? WHERE user_id = ?")->execute([$user['is_active'] ? 0 : 1, $userId]);
                        $adminMessage = 'User status updated.';
                    }
                    break;
                case 'delete_user':
                    if ($userId != getCurrentUserId()) {
                        $db->prepare("DELETE FROM Users WHERE user_id = ?")->execute([$userId]);
                        $adminMessage = 'User deleted.';
                    }
                    break;
                case 'clear_history':
                    $days = intval($_POST['days'] ?? 7);
                    $db->prepare("DELETE FROM History WHERE time < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
                    $adminMessage = "Cleared history older than $days days.";
                    break;
            }
        } catch (PDOException $e) { $adminError = 'Operation failed.'; }
    }
}

// fetch admin data
$users = [];
$fullHistory = [];
if (isAdmin()) {
    $db = getDBConnection();
    if ($db) {
        try {
            $users = $db->query("SELECT u.*, a.role, a.administrator_id FROM Users u LEFT JOIN Administrator a ON a.user_id = u.user_id ORDER BY u.date_created DESC")->fetchAll();
            $fullHistory = $db->query("SELECT * FROM History ORDER BY id DESC LIMIT 500")->fetchAll();
        } catch (PDOException $e) {}
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soil Moisture Monitoring System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="imagez/SoilCon_Logo_CLEAR.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <header>
        <div class="header-nav">
            <div class="account-dropdown">
                <button class="account-btn" onclick="toggleAccountMenu()" aria-expanded="false" aria-haspopup="true" aria-label="Account menu">Account</button>
                <div class="account-menu" id="accountMenu" role="menu">
                    <div class="account-info">
                        <span class="account-name"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
                        <span class="account-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></span>
                    </div>
                    <a href="auth.php?action=logout" class="menu-item logout">Logout</a>
                </div>
            </div>
        </div>
        <div class="header-logo"><img src="imagez/SoilCon_Logo_2_Cropped.png" alt="Soil Moisture Monitoring System - ESP32 Real-Time Sensor Data"></div>
        <div class="connection-status" id="connectionStatus">
            <span class="status-dot disconnected"></span>
            <span class="status-text">Checking ESP32...</span>
        </div>
    </header>

    <div class="notification-container" id="notificationContainer" role="alert" aria-live="polite"></div>

    <div class="main-layout">
        <main class="dashboard">
            <div class="card temperature-card">
                <h2>Temperature</h2>
                <div class="card-value">
                    <span id="temperatureValue" class="skeleton-text">--</span>
                    <span class="unit">°C</span>
                </div>
                <div class="card-status" id="temperatureStatus">Waiting for data...</div>
            </div>

            <div class="card humidity-card">
                <h2>Humidity</h2>
                <div class="card-value">
                    <span id="humidityValue" class="skeleton-text">--</span>
                    <span class="unit">%</span>
                </div>
                <div class="card-status" id="humidityStatus">Waiting for data...</div>
            </div>

            <div class="card moisture-card">
                <h2>Soil Moisture</h2>
                <div class="card-value">
                    <span id="moistureValue" class="skeleton-text">--</span>
                    <span class="unit">%</span>
                </div>
                <div class="moisture-status" id="moistureStatus">
                    <span class="status-badge">Waiting for data...</span>
                </div>
                <div class="moisture-bar"><div class="moisture-fill" id="moistureFill"></div></div>
                <div class="moisture-labels"><span>Dry</span><span>Good</span><span>Wet</span></div>
            </div>

            <div class="card history-card">
                <h2>Recent History</h2>
                <div class="history-table-container">
                    <table class="history-table">
                        <thead><tr><th>Time</th><th>Temp</th><th>Humidity</th><th>Moisture</th><th>Soil</th><th>Water</th></tr></thead>
                        <tbody id="historyTableBody">
                            <tr><td colspan="6" class="no-data">No history data yet...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <aside class="water-tube-container">
            <h2>Water Level</h2>
            <div class="water-tube">
                <div class="tube-markings">
                    <span class="mark full">FULL</span>
                    <span class="mark good">GOOD</span>
                    <span class="mark low">LOW</span>
                </div>
                <div class="tube-glass">
                    <div class="tube-water" id="tubeWater"><div class="water-surface"></div></div>
                    <div class="tube-bubbles"><span class="bubble"></span><span class="bubble"></span><span class="bubble"></span></div>
                </div>
            </div>
            <div class="tube-info">
                <div class="water-status" id="waterStateStatus"><span class="status-badge">Waiting...</span></div>
            </div>
        </aside>
    </div>

    <?php if (isAdmin()): ?>
    <section id="admin-section" class="admin-panel">
        <div class="admin-header-bar"><h2>Admin Panel</h2></div>
        <div class="admin-tabs" role="tablist">
            <button class="admin-tab active" role="tab" aria-selected="true" aria-controls="admin-tab-users" onclick="showAdminTab('users', event)">User Management</button>
            <button class="admin-tab" role="tab" aria-selected="false" aria-controls="admin-tab-history" onclick="showAdminTab('history', event)">Full History</button>
        </div>
        <?php if ($adminMessage): ?><div class="admin-alert success"><?= htmlspecialchars($adminMessage) ?></div><?php endif; ?>
        <?php if ($adminError): ?><div class="admin-alert error"><?= htmlspecialchars($adminError) ?></div><?php endif; ?>

        <div id="admin-tab-users" class="admin-tab-content active">
            <div class="admin-content">
                <h3>User Management</h3>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Role</th><th>Last Login</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['user_id'] ?></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['surname']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><span class="badge <?= $user['is_active'] ? 'active' : 'inactive' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td><span class="badge <?= $user['role'] ? 'admin' : '' ?>"><?= $user['role'] ? 'Admin' : 'User' ?></span></td>
                                <td><?= $user['last_login'] ?? 'Never' ?></td>
                                <td class="action-buttons">
                                <?php if ($user['user_id'] != getCurrentUserId()): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Change admin privileges for this user?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="user_id" value="<?= $user['user_id'] ?>"><input type="hidden" name="admin_action" value="toggle_admin">
                                        <button type="submit" class="btn-admin"><?= $user['role'] ? 'Remove Admin' : 'Make Admin' ?></button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Change active status for this user?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="user_id" value="<?= $user['user_id'] ?>"><input type="hidden" name="admin_action" value="toggle_active">
                                        <button type="submit" class="btn-toggle"><?= $user['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                <?php else: ?>
                                    <em style="color:#888;">You</em>
                                <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="admin-tab-history" class="admin-tab-content">
            <div class="admin-content">
                <h3>Full Sensor History (Last 500 Records)</h3>
                <div class="admin-table-container">
                    <table class="admin-table history-full-table">
                        <thead><tr><th>Time</th><th>Temp (°C)</th><th>Humidity (%)</th><th>Moisture (%)</th><th>Soil State</th><th>Water Level</th></tr></thead>
                        <tbody id="adminHistoryTableBody">
                        <?php if (empty($fullHistory)): ?>
                            <tr><td colspan="6" class="no-data">No history data available</td></tr>
                        <?php else: ?>
                            <?php foreach ($fullHistory as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['time']) ?></td>
                                <td><?= number_format($record['temp'], 1) ?></td>
                                <td><?= number_format($record['humidity'], 1) ?></td>
                                <td><?= number_format($record['moisture'], 1) ?></td>
                                <td><span class="status-badge <?= strtolower($record['soil'] ?? 'unknown') ?>"><?= htmlspecialchars($record['soil'] ?? 'N/A') ?></span></td>
                                <td><span class="status-badge <?= strtolower($record['water'] ?? 'unknown') ?>"><?= htmlspecialchars($record['water'] ?? 'N/A') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <footer><p>Last Updated: <span id="lastUpdate">Never</span></p></footer>
</div>
<script src="app.js"></script>
</body>
</html>
