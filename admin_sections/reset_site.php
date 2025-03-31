<?php
$resetSuccess = false;
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes') {
    try {
        // 1. Delete all users except admin
        $stmt = $pdo->prepare("DELETE FROM users WHERE role != 'admin'");
        $stmt->execute();

        // 2. Delete all file and login records
        $pdo->exec("DELETE FROM files");
        $pdo->exec("DELETE FROM login_attempts");

        // 3. Purge uploaded files and thumbnails
        $deleteFiles = function ($dir) {
            foreach (glob($dir . '*') as $file) {
                if (is_file($file)) unlink($file);
            }
        };
        $deleteFiles(__DIR__ . '/../uploads/');
        $deleteFiles(__DIR__ . '/../thumbnails/');

        // 4. Reset settings
        write_default_settings_file();

        $resetSuccess = true;
    } catch (Exception $e) {
        $errors[] = "Reset failed: " . $e->getMessage();
    }
}
?>

<h3>Reset Site</h3>
<p><strong>Warning:</strong> This will delete all non-admin users, all uploaded files and thumbnails, all file records, and reset all settings to default values.</p>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php elseif ($resetSuccess): ?>
    <div class="success">✅ Site has been reset to post-install state.</div>
<?php endif; ?>

<form method="post" onsubmit="return confirm('Are you sure? This action is irreversible.');">
    <input type="hidden" name="confirm_reset" value="yes">
    <button type="submit" style="background: red; color: white;">Reset Site</button>
</form>
