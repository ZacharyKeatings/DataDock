<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/install_lib.php';
?>

<?php
$pageTitle = "Install";
$siteName = "DataDock";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('assets/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('assets/themes/light.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
        <div class="page-wrapper">
        <header class="site-header">
            <div class="site-title">
                <a href="index.php"><?= htmlspecialchars($siteName) ?></a>
            </div>
        </header>
        <main class="container">
        <div class="page-section">
            <h2 class="page-title">Install DataDock</h2>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="message">
                    <?php
                        // Gather site and DB settings
                        $host     = trim($_POST['db_host']);
                        $user     = trim($_POST['db_user']);
                        $pass     = trim($_POST['db_pass']);
                        $dbname   = trim($_POST['db_name']);
                        $siteName = trim($_POST['site_name']);

                        // Gather admin user settings
                        $admin_username = trim($_POST['admin_username'] ?? '');
                        $admin_email    = trim($_POST['admin_email'] ?? '');
                        $admin_password = $_POST['admin_password'] ?? '';
                        $admin_confirm  = $_POST['admin_confirm'] ?? '';

                        // Validate admin fields
                        $admin_errors = [];
                        if (empty($admin_username)) {
                            $admin_errors[] = "Admin username is required.";
                        }
                        if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                            $admin_errors[] = "Invalid admin email address.";
                        }
                        if (strlen($admin_password) < 6) {
                            $admin_errors[] = "Admin password must be at least 6 characters.";
                        }
                        if ($admin_password !== $admin_confirm) {
                            $admin_errors[] = "Admin passwords do not match.";
                        }

                        // Create the database and tables
                        $result = install_database($host, $user, $pass, $dbname);
                        if ($result === true) {
                            create_db_config_file($host, $user, $pass, $dbname);
                            write_default_settings_file($siteName);
                            secure_config_folder();

                            // Create directories for uploads and thumbnails
                            $dirs = ['uploads', 'thumbnails'];
                            foreach ($dirs as $dir) {
                                $path = __DIR__ . '/' . $dir;
                                if (!is_dir($path)) {
                                    mkdir($path, 0755, true);
                                } else {
                                    chmod($path, 0755);
                                }
                            }
                            
                            // If there are admin errors, display them and halt installation
                            if (!empty($admin_errors)) {
                                foreach ($admin_errors as $err) {
                                    echo '<div class="flash error persistent"><div>' . htmlspecialchars($err) . '</div></div>';
                                }
                                echo '<div class="flash error persistent"><div>Installation failed because the admin account could not be created. Fix the fields and try again.</div></div>';
                            } else {
                                require_once __DIR__ . '/config/db.php';

                                // Insert admin user into the database with role 'admin'
                                $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
                                $stmt->execute([$admin_username, $admin_email, $admin_hash]);

                                echo '<div class="flash success persistent"><div>Installation complete. Database tables, config files, upload folders, and the admin user are ready.<br><strong>Delete install.php now.</strong> <a href="index.php">Go to your site</a></div></div>';
                            }
                        } else {
                            echo '<div class="flash error persistent"><div>Installation failed: ' . htmlspecialchars((string) $result) . '</div></div>';
                        }
                    ?>
                </div>
            <?php else:
                $dd_db_prefill = install_db_form_prefill();
                ?>
                <form method="post" class="form settings-form" onsubmit="return validateForm()">
                    <div class="settings-card">
                        <h3 class="settings-card-title">Site</h3>
                        <div class="settings-card-body">
                            <div class="settings-row">
                                <label for="site_name">Site name</label>
                                <input type="text" id="site_name" name="site_name" value="DataDock" required>
                            </div>
                            <p class="settings-hint">Shown in the header and the browser title.</p>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h3 class="settings-card-title">Database</h3>
                        <div class="settings-card-body">
                            <?php if (is_readable(__DIR__ . '/config/.db-runtime.php')): ?>
                                <p class="settings-hint">These fields are filled from the Docker database environment. You can change them before installing.</p>
                            <?php endif; ?>
                            <div class="settings-row">
                                <label for="db_host">Host</label>
                                <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($dd_db_prefill['host'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <p class="settings-hint">Usually localhost. In Docker Compose, use the database service name.</p>
                            <div class="settings-row">
                                <label for="db_user">Username</label>
                                <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($dd_db_prefill['user'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="settings-row">
                                <label for="db_pass">Password</label>
                                <span class="settings-input-with-unit">
                                    <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($dd_db_prefill['pass'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="button" class="btn btn-quiet btn-small" id="toggle-db-pass">Show</button>
                                </span>
                            </div>
                            <div class="settings-row">
                                <label for="db_name">Database name</label>
                                <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($dd_db_prefill['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <p class="settings-hint">Created automatically if it does not exist.</p>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h3 class="settings-card-title">Admin account</h3>
                        <div class="settings-card-body">
                            <div class="settings-row">
                                <label for="admin_username">Username</label>
                                <input type="text" id="admin_username" name="admin_username" required>
                            </div>
                            <div class="settings-row">
                                <label for="admin_email">Email</label>
                                <input type="email" id="admin_email" name="admin_email" required>
                            </div>
                            <div class="settings-row">
                                <label for="admin_password">Password</label>
                                <input type="password" id="admin_password" name="admin_password" required minlength="6">
                            </div>
                            <div class="settings-row">
                                <label for="admin_confirm">Confirm password</label>
                                <input type="password" id="admin_confirm" name="admin_confirm" required minlength="6">
                            </div>
                            <p class="form-hint" id="errorMsg" hidden></p>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Install</button>
                            </div>
                        </div>
                    </div>
                </form>
                <script>
                    function validateForm() {
                        const requiredFields = [
                            'site_name', 'db_host', 'db_user', 'db_name', 
                            'admin_username', 'admin_email', 'admin_password', 'admin_confirm'
                        ];
                        let valid = true;
                        let errorBox = document.getElementById('errorMsg');
                        errorBox.textContent = '';
                        errorBox.hidden = true;

                        requiredFields.forEach(id => {
                            const input = document.getElementById(id);
                            if (!input.value.trim()) {
                                valid = false;
                                errorBox.textContent = 'Fill in every required field.';
                                errorBox.hidden = false;
                            }
                        });

                        const adminPassword = document.getElementById('admin_password').value;
                        const adminConfirm = document.getElementById('admin_confirm').value;
                        if (adminPassword !== adminConfirm) {
                            valid = false;
                            errorBox.textContent = 'Admin passwords do not match.';
                            errorBox.hidden = false;
                        }

                        return valid;
                    }

                    document.getElementById('toggle-db-pass').addEventListener('click', function () {
                        const passField = document.getElementById('db_pass');
                        const show = passField.type === 'password';
                        passField.type = show ? 'text' : 'password';
                        this.textContent = show ? 'Hide' : 'Show';
                    });
                </script>
            <?php endif; ?>
        </div>
        </main>
        <footer>
            <p>&copy; <?= date('Y') ?> DataDock. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
