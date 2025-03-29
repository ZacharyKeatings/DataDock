<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['input'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($input) || empty($password)) {
        $errors[] = "Both fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input, $input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit;
        } else {
            $errors[] = "Invalid credentials.";
        }
    }
}

$pageTitle = "Login";
require_once __DIR__ . '/includes/header.php';
?>

<h2>Login</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $e) echo "<div>• $e</div>"; ?>
    </div>
<?php endif; ?>

<form method="post">
    <label for="input">Username or Email</label>
    <input type="text" name="input" id="input" value="<?= htmlspecialchars($input) ?>" required>

    <label for="password">Password</label>
    <input type="password" name="password" id="password" required>

    <button type="submit">Login</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
