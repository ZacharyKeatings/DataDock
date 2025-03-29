<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // Validation
    if (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Check if username/email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email already exists.";
        }
    }

    // Register new user
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $passwordHash]);

        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['username'] = $username;
        header("Location: dashboard.php");
        exit;
    }
}

$pageTitle = "Register";
require_once __DIR__ . '/includes/header.php';
?>

<h2>Register</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $e): ?>
            <div>• <?= sanitize_data($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" onsubmit="return validateForm()">
    <label for="username">Username</label>
    <input type="text" name="username" id="username" value="<?= sanitize_data($username) ?>" required>

    <label for="email">Email</label>
    <input type="email" name="email" id="email" value="<?= sanitize_data($email) ?>" required>

    <label for="password">Password</label>
    <input type="password" name="password" id="password" required>

    <label for="confirm">Confirm Password</label>
    <input type="password" name="confirm" id="confirm" required>

    <button type="submit">Register</button>
</form>

<script>
function validateForm() {
    const password = document.getElementById("password");
    const confirm = document.getElementById("confirm");
    if (password.value !== confirm.value) {
        alert("Passwords do not match.");
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
