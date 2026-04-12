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
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrapper">
        <header class="site-header" style="margin-bottom: 2rem;">
            <div class="site-title">
                <a href="index.php"><?= htmlspecialchars($siteName) ?></a>
            </div>
        </header>

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
                                    echo "<div class='error'>• " . htmlspecialchars($err) . "</div>";
                                }
                                echo "<div class='error'>Installation failed due to admin user errors. Please fix and try again.</div>";
                            } else {
                                require_once __DIR__ . '/config/db.php';

                                // Insert admin user into the database with role 'admin'
                                $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
                                $stmt->execute([$admin_username, $admin_email, $admin_hash]);

                                echo "<span class='success'>✅ Installation complete.<br>
                                    ✅ Database tables created.<br>
                                    ✅ <code>config/db.php</code> and <code>config/settings.php</code> generated.<br>
                                    ✅ <code>uploads/</code> and <code>thumbnails/</code> folders ready.<br>
                                    ✅ Admin user created.<br>
                                    <strong>Please delete this file (install.php) now for security.</strong>
                                    🔗 <a href='index.php'>Go to your site homepage</a></span>";
                            }
                        } else {
                            echo "<span class='error'>❌ Installation failed: $result</span>";
                        }
                    ?>
                </div>
            <?php else: ?>
                <form method="post" class="form" onsubmit="return validateForm()">
                    <h3>Site Settings</h3>
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" value="DataDock" required>
                    <small>This is the name of your site (e.g., "My File Hub"). Choose any name you prefer.</small>

                    <h3>Database Settings</h3>
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                    <small>The hostname of your MySQL server. Typically "localhost" on shared hosting.</small>

                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" required>
                    <small>Your MySQL username. This is provided by your hosting provider or set up in your control panel.</small>

                    <label for="db_pass">Database Password <span class="toggle-password" onclick="togglePassword()">[show]</span></label>
                    <input type="password" id="db_pass" name="db_pass">
                    <small>The password associated with your MySQL username.</small>

                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="file_upload_site" required>
                    <small>The name of the database to use for this site. It will be created automatically if it doesn't exist.</small>

                    <h3>Admin User Settings</h3>
                    <label for="admin_username">Admin Username</label>
                    <input type="text" id="admin_username" name="admin_username" required>
                    <small>This will be the username for your administrator account.</small>

                    <label for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                    <small>Enter a valid email address for the admin account (used for notifications and recovery).</small>

                    <label for="admin_password">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                    <small>Choose a secure password (minimum 6 characters).</small>

                    <label for="admin_confirm">Confirm Admin Password</label>
                    <input type="password" id="admin_confirm" name="admin_confirm" required>
                    <small>Re-enter the admin password for confirmation.</small>

                    <div id="errorMsg" class="error"></div>

                    <button type="submit">Install</button>
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

                        requiredFields.forEach(id => {
                            const input = document.getElementById(id);
                            if (!input.value.trim()) {
                                valid = false;
                                errorBox.textContent = "Please fill in all required fields.";
                            }
                        });

                        // Check if admin passwords match
                        const adminPassword = document.getElementById('admin_password').value;
                        const adminConfirm = document.getElementById('admin_confirm').value;
                        if (adminPassword !== adminConfirm) {
                            valid = false;
                            errorBox.textContent = "Admin passwords do not match.";
                        }

                        return valid;
                    }

                    function togglePassword() {
                        const passField = document.getElementById('db_pass');
                        const toggle = document.querySelector('.toggle-password');
                        if (passField.type === "password") {
                            passField.type = "text";
                            toggle.textContent = "[hide]";
                        } else {
                            passField.type = "password";
                            toggle.textContent = "[show]";
                        }
                    }
                </script>
            <?php endif; ?>
        </div>
        <footer>
            <p>&copy; <?= date('Y') ?> DataDock. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
