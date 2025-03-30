<?php
// Handle Site Settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'site') {
    $newName = trim($_POST['site_name'] ?? '');
    $registrationEnabled = isset($_POST['registration_enabled']) ? true : false;
    $maxFileSize = isset($_POST['max_file_size']) ? (int) $_POST['max_file_size'] : 0;

    if ($newName === '') {
        $errors[] = "Site name cannot be empty.";
    } elseif ($maxFileSize <= 0) {
        $errors[] = "Max file size must be a positive number.";
    } else {
        $updatedSettings = "<?php\n\$settings = [
    'site_name' => " . var_export($newName, true) . ",
    'registration_enabled' => " . ($registrationEnabled ? 'true' : 'false') . ",
    'max_file_size' => $maxFileSize
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
$maxFileSize = $settings['max_file_size'] ?? 5242880; // default to 5MB
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

    <button type="submit">Update</button>
</form>