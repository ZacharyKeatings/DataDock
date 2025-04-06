<section class="">
    <h3 class="page-title">Site Settings</h3>

    <form method="post" class="form form-grid">
        <input type="hidden" name="section" value="site">
        <!-- Site Name -->
        <label for="site_name">Site Name</label>
        <input type="text" name="site_name" id="site_name" value="<?= sanitize_data($siteName) ?>" required>

        <!-- User Permissions -->
        <h4>User Permissions</h4>
        <label>
            <input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
            Enable User Registration
        </label>
        <div></div>

        <label for="max_file_size">Max File Size (in bytes)</label>
        <input type="number" name="max_file_size" id="max_file_size" value="<?= sanitize_data($maxFileSize) ?>" required>

        <!-- User Upload Limits -->
        <h4>User Upload Limits</h4>

        <label>
            <input type="checkbox" id="user_max_files_enabled" name="user_max_files_enabled" <?= $userMaxFilesEnabled ? 'checked' : '' ?>>
            Enforce Max Files Per User
        </label>
        <input type="number" name="user_max_files" id="user_max_files" value="<?= sanitize_data($userMaxFiles) ?>" min="0" <?= !$userMaxFilesEnabled ? 'disabled' : '' ?>>

        <label>
            <input type="checkbox" id="user_max_storage_enabled" name="user_max_storage_enabled" <?= $userMaxStorageEnabled ? 'checked' : '' ?>>
            Enforce Max Storage Per User (in bytes)
        </label>
        <input type="number" name="user_max_storage" id="user_max_storage" value="<?= sanitize_data($userMaxStorage) ?>" min="0" <?= !$userMaxStorageEnabled ? 'disabled' : '' ?>>

        <!-- Guest Uploads -->
        <h4>Guest Uploads</h4>

        <label>
            <input type="checkbox" id="guest_uploads_enabled" name="guest_uploads_enabled" <?= $guestUploadsEnabled ? 'checked' : '' ?>>
            Allow Guest Uploads
        </label>
        <div></div>

        <label for="guest_max_files">Guest Max Files</label>
        <input type="number" name="guest_max_files" id="guest_max_files" value="<?= sanitize_data($guestMaxFiles) ?>" min="0" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>

        <label for="guest_max_storage">Guest Max Storage (in bytes)</label>
        <input type="number" name="guest_max_storage" id="guest_max_storage" value="<?= sanitize_data($guestMaxStorage) ?>" min="0" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>

        <!-- Brute Force Protection -->
        <h4>Brute Force Protection</h4>

        <label>
            <input type="checkbox" id="brute_force_enabled" name="brute_force_enabled" <?= $bruteForceEnabled ? 'checked' : '' ?>>
            Enable Brute Force Protection
        </label>
        <div></div>

        <label for="max_attempts">Max Login Attempts</label>
        <input type="number" name="max_attempts" id="max_attempts" value="<?= sanitize_data($maxAttempts) ?>" min="1" required>

        <label for="lockout_minutes">Lockout Duration (minutes)</label>
        <input type="number" name="lockout_minutes" id="lockout_minutes" value="<?= sanitize_data($lockoutMinutes) ?>" min="1" required>

        <label for="lockout_window">Attempt Window (minutes)</label>
        <input type="number" name="lockout_window" id="lockout_window" value="<?= sanitize_data($lockoutWindow) ?>" min="1" required>

        <button type="submit" class="btn btn-primary">Update Settings</button>
    </form>
</section>

<!-- JS Toggles -->
<script>
    const toggleInputs = (checkboxId, inputIds) => {
        const checkbox = document.getElementById(checkboxId);
        const inputs = inputIds.map(id => document.getElementById(id));

        function toggle() {
            inputs.forEach(input => input.disabled = !checkbox.checked);
        }

        checkbox.addEventListener('change', toggle);
        toggle();
    };

    toggleInputs('user_max_files_enabled', ['user_max_files']);
    toggleInputs('user_max_storage_enabled', ['user_max_storage']);
    toggleInputs('guest_uploads_enabled', ['guest_max_files', 'guest_max_storage']);
    toggleInputs('brute_force_enabled', ['max_attempts', 'lockout_minutes', 'lockout_window']);
</script>
