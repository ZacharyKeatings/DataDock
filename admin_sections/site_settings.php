<?php
// Handle Site Settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'site') {
    $newName = trim($_POST['site_name'] ?? '');
    $registrationEnabled = isset($_POST['registration_enabled']) ? true : false;
    $maxFileSize = isset($_POST['max_file_size']) ? (int) $_POST['max_file_size'] : 0;

    $bruteForceEnabled = isset($_POST['brute_force_enabled']) ? true : false;
    $maxAttempts = (int) ($_POST['max_attempts'] ?? 5);
    $lockoutMinutes = (int) ($_POST['lockout_minutes'] ?? 15);
    $lockoutWindow = (int) ($_POST['lockout_window'] ?? 10);

    if ($newName === '') {
        $errors[] = "Site name cannot be empty.";
    } elseif ($maxFileSize <= 0) {
        $errors[] = "Max file size must be a positive number.";
    } elseif ($bruteForceEnabled && ($maxAttempts < 1 || $lockoutMinutes < 1 || $lockoutWindow < 1)) {
        $errors[] = "Brute force settings must be positive integers.";
    } else {
        $updatedSettings = "<?php\n\$settings = [
    'site_name' => " . var_export($newName, true) . ",
    'registration_enabled' => " . ($registrationEnabled ? 'true' : 'false') . ",
    'max_file_size' => $maxFileSize,
    'brute_force' => [
        'enabled' => " . ($bruteForceEnabled ? 'true' : 'false') . ",
        'max_attempts' => $maxAttempts,
        'lockout_minutes' => $lockoutMinutes,
        'lockout_window' => $lockoutWindow
    ]
];\n?>";

        if (file_put_contents($settingsFile, $updatedSettings)) {
            $success = "Site settings updated successfully.";
            $siteName = $newName;
        } else {
            $errors[] = "Failed to update site settings.";
        }
    }
}

// Read saved settings for display
$registrationEnabled = $settings['registration_enabled'] ?? true;
$maxFileSize = $settings['max_file_size'] ?? 5242880;
$bruteForceEnabled = $settings['brute_force']['enabled'] ?? true;
$maxAttempts = $settings['brute_force']['max_attempts'] ?? 5;
$lockoutMinutes = $settings['brute_force']['lockout_minutes'] ?? 15;
$lockoutWindow = $settings['brute_force']['lockout_window'] ?? 10;
?>

<h3>Site Settings</h3>
<form method="post">
    <label for="site_name">Site Name</label>
    <input type="text" name="site_name" id="site_name" value="<?= sanitize_data($siteName) ?>" required>

    <label>
        <input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
        Enable User Registration
    </label>

    <label for="max_file_size">Max File Size (in bytes)</label>
    <input type="number" name="max_file_size" id="max_file_size" value="<?= sanitize_data($maxFileSize) ?>" required>

    <h4>Brute Force Protection</h4>

    <label>
        <input type="checkbox" id="brute_force_enabled" name="brute_force_enabled" <?= $bruteForceEnabled ? 'checked' : '' ?>>
        Enable Brute Force Protection
    </label>

    <label for="max_attempts">Max Login Attempts</label>
    <input type="number" name="max_attempts" id="max_attempts" value="<?= sanitize_data($maxAttempts) ?>" min="1" required>

    <label for="lockout_minutes">Lockout Duration (minutes)</label>
    <input type="number" name="lockout_minutes" id="lockout_minutes" value="<?= sanitize_data($lockoutMinutes) ?>" min="1" required>

    <label for="lockout_window">Attempt Window (minutes)</label>
    <input type="number" name="lockout_window" id="lockout_window" value="<?= sanitize_data($lockoutWindow) ?>" min="1" required>

    <button type="submit">Update</button>
</form>

<script>
    const toggle = document.getElementById('brute_force_enabled');
    const fields = [
        document.getElementById('max_attempts'),
        document.getElementById('lockout_minutes'),
        document.getElementById('lockout_window')
    ];

    function updateBruteForceInputs() {
        const disabled = !toggle.checked;
        fields.forEach(input => input.disabled = disabled);
    }

    toggle.addEventListener('change', updateBruteForceInputs);
    updateBruteForceInputs(); // Initial load
</script>
