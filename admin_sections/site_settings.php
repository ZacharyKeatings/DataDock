<?php
// Handle Site Settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'site') {
    $newName = trim($_POST['site_name'] ?? '');
    $registrationEnabled = isset($_POST['registration_enabled']);
    $maxFileSize = isset($_POST['max_file_size']) ? (int) $_POST['max_file_size'] : 0;

    $bruteForceEnabled = isset($_POST['brute_force_enabled']);
    $maxAttempts = (int) ($_POST['max_attempts'] ?? 5);
    $lockoutMinutes = (int) ($_POST['lockout_minutes'] ?? 15);
    $lockoutWindow = (int) ($_POST['lockout_window'] ?? 10);

    $guestUploadsEnabled = isset($_POST['guest_uploads_enabled']);
    $guestMaxFiles = isset($_POST['guest_max_files']) ? (int) $_POST['guest_max_files'] : 0;
    $guestMaxStorage = isset($_POST['guest_max_storage']) ? (int) $_POST['guest_max_storage'] : 0;

    if ($newName === '') {
        $errors[] = "Site name cannot be empty.";
    } elseif ($maxFileSize <= 0) {
        $errors[] = "Max file size must be a positive number.";
    } elseif ($bruteForceEnabled && ($maxAttempts < 1 || $lockoutMinutes < 1 || $lockoutWindow < 1)) {
        $errors[] = "Brute force settings must be positive integers.";
    } elseif ($guestUploadsEnabled && ($guestMaxFiles < 1 || $guestMaxStorage < 1)) {
        $errors[] = "Guest upload settings must be positive integers.";
    } else {
        $updatedSettings = "<?php\n\$settings = [\n" .
            "    'site_name' => " . var_export($newName, true) . ",\n" .
            "    'registration_enabled' => " . ($registrationEnabled ? 'true' : 'false') . ",\n" .
            "    'max_file_size' => $maxFileSize,\n" .
            "    'brute_force' => [\n" .
            "        'enabled' => " . ($bruteForceEnabled ? 'true' : 'false') . ",\n" .
            "        'max_attempts' => $maxAttempts,\n" .
            "        'lockout_minutes' => $lockoutMinutes,\n" .
            "        'lockout_window' => $lockoutWindow\n" .
            "    ],\n" .
            "    'guest_uploads' => [\n" .
            "        'enabled' => " . ($guestUploadsEnabled ? 'true' : 'false') . ",\n" .
            "        'max_files' => $guestMaxFiles,\n" .
            "        'max_storage' => $guestMaxStorage\n" .
            "    ]\n" .
            "];\n?>";

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

$guestUploadsEnabled = $settings['guest_uploads']['enabled'] ?? false;
$guestMaxFiles = $settings['guest_uploads']['max_files'] ?? 10;
$guestMaxStorage = $settings['guest_uploads']['max_storage'] ?? 5242880;
?>


<h3>Site Settings</h3>
<form method="post">
    <label for="site_name">Site Name</label>
    <input type="text" name="site_name" id="site_name" value="<?= sanitize_data($siteName) ?>" required>

    <h4>User Permissions</h4>

    <label>
        <input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
        Enable User Registration
    </label>

    <label for="max_file_size">Max File Size (in bytes)</label>
    <input type="number" name="max_file_size" id="max_file_size" value="<?= sanitize_data($maxFileSize) ?>" required>

    <h4>Guest Permission</h4>

    <label>
        <input type="checkbox" id="guest_uploads_enabled" name="guest_uploads_enabled" <?= $guestUploadsEnabled ? 'checked' : '' ?>>
        Allow Guest Uploads
    </label>

    <label for="guest_max_files">Guest Max Files</label>
    <input type="number" name="guest_max_files" id="guest_max_files" value="<?= sanitize_data($guestMaxFiles) ?>" min="0" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>

    <label for="guest_max_storage">Guest Max Storage (in bytes)</label>
    <input type="number" name="guest_max_storage" id="guest_max_storage" value="<?= sanitize_data($guestMaxStorage) ?>" min="0" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>

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
    const brute_force_toggle = document.getElementById('brute_force_enabled');
    const brute_force_fields = [
        document.getElementById('max_attempts'),
        document.getElementById('lockout_minutes'),
        document.getElementById('lockout_window')
    ];

    function updateBruteForceInputs() {
        const disabled = !brute_force_toggle.checked;
        brute_force_fields.forEach(input => input.disabled = disabled);
    }

    brute_force_toggle.addEventListener('change', updateBruteForceInputs);
    updateBruteForceInputs(); // Initial load
</script>

<script>
    const guest_upload_toggle = document.getElementById('guest_uploads_enabled');
    const guest_upload_fields = [
        document.getElementById('guest_max_files'),
        document.getElementById('guest_max_storage'),
    ];

    function updateGuestUploadInputs() {
        const disabled = !guest_upload_toggle.checked;
        guest_upload_fields.forEach(input => input.disabled = disabled);
    }

    guest_upload_toggle.addEventListener('change', updateGuestUploadInputs);
    updateGuestUploadInputs(); // Initial load
</script>
