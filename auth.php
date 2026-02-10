<?php
require_once 'config.php';

$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';

// handle logout
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit();
}

// redirect if already logged in
if (isLoggedIn()) redirect('index.php');

// handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $formAction = $_POST['form_action'] ?? '';
        
        if ($formAction === 'login') {
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error = 'Please enter your email and password.';
            } else {
                $db = getDBConnection();
                if ($db) {
                    try {
                        $stmt = $db->prepare("SELECT u.user_id, u.email, u.password, u.surname, u.first_name, u.is_active, a.administrator_id, a.role FROM Users u LEFT JOIN Administrator a ON a.user_id = u.user_id WHERE u.email = ?");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();
                        
                        if ($user && password_verify($password, $user['password'])) {
                            if (!$user['is_active']) {
                                $error = 'Your account has been deactivated.';
                            } else {
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $user['user_id'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['name'] = $user['first_name'] . ' ' . $user['surname'];
                                $_SESSION['is_admin'] = (bool)$user['administrator_id'];
                                $db->prepare("UPDATE Users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);
                                redirect('index.php');
                            }
                        } else {
                            $error = 'Invalid email or password.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Login failed. Please try again.';
                    }
                } else {
                    $error = 'Database connection failed.';
                }
            }
        } elseif ($formAction === 'register') {
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $surname = sanitize($_POST['surname'] ?? '');
            $firstName = sanitize($_POST['first_name'] ?? '');
            $middleInitial = sanitize($_POST['middle_initial'] ?? '');
            
            if (empty($email) || empty($password) || empty($surname) || empty($firstName)) {
                $error = 'Please fill in all required fields.';
            } elseif (strlen($surname) > 100 || strlen($firstName) > 100) {
                $error = 'Name fields must be 100 characters or less.';
            } elseif (strlen($middleInitial) > 5) {
                $error = 'Middle initial must be 5 characters or less.';
            } elseif (strlen($email) > 255) {
                $error = 'Email must be 255 characters or less.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                $db = getDBConnection();
                if ($db) {
                    try {
                        $stmt = $db->prepare("SELECT user_id FROM Users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $error = 'An account with this email already exists.';
                        } else {
                            $db->prepare("INSERT INTO Users (email, password, surname, first_name, middle_initial) VALUES (?, ?, ?, ?, ?)")
                               ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $surname, $firstName, $middleInitial]);
                            $success = 'Account created successfully! You can now login.';
                            $action = 'login';
                        }
                    } catch (PDOException $e) {
                        $error = 'Registration failed. Please try again.';
                    }
                } else {
                    $error = 'Database connection failed.';
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'register' ? 'Register' : 'Login' ?> - Soil Moisture System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="imagez/SoilCon_Logo_CLEAR.png">
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-logo"><img src="imagez/6.png" alt="SoilCon Logo"></div>
    <div class="auth-container">
    <?php if ($action === 'register'): ?>
    <div class="auth-header"><h1>Create Account</h1></div>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="form_action" value="register">
        <div class="form-row three-col">
            <div class="form-group">
                <label>First Name <span class="required-asterisk">*</span></label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Surname <span class="required-asterisk">*</span></label>
                <input type="text" name="surname" value="<?= htmlspecialchars($_POST['surname'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>M.I.</label>
                <input type="text" name="middle_initial" maxlength="5" value="<?= htmlspecialchars($_POST['middle_initial'] ?? '') ?>" style="width: 60px;">
            </div>
        </div>
        <div class="form-group">
            <label>Email Address <span class="required-asterisk">*</span></label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Password <span class="required-asterisk">*</span></label>
                <input type="password" name="password" minlength="8" required>
            </div>
            <div class="form-group">
                <label>Confirm Password <span class="required-asterisk">*</span></label>
                <input type="password" name="confirm_password" required>
            </div>
        </div>
        <button type="submit" class="btn-submit">Create Account</button>
    </form>
    <div class="auth-footer">Already have an account? <a href="auth.php?action=login">Login here</a></div>
    <?php else: ?>
    <div class="auth-header">
        <h1>Welcome To SoilCon!</h1>
        <p>A Web Platform to Monitor Soil Moisture Data</p>
        <br>
        <p>Login to your account below :3</p>
    </div>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="form_action" value="login">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn-submit">Login</button>
    </form>
    <div class="auth-footer">Don't have an account? <a href="auth.php?action=register">Register here</a></div>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
