<section class="">
    <h3 class="page-title">Site Settings</h3>

    <form method="post" class="form form-grid">
        <input type="hidden" name="section" value="site">
        <!-- Site Name -->
        <label for="site_name">Site Name</label>
        <input type="text" name="site_name" id="site_name" value="<?= sanitize_data($siteName) ?>" required>

        <label for="admin_contact_email">Admin Contact Email</label>
        <input type="email" name="admin_contact_email" id="admin_contact_email" value="<?= sanitize_data($adminContactEmail) ?>" placeholder="Optional">
        <small>Shown in footer; leave empty to hide.</small>

        <!-- User Permissions -->
        <h4>User Permissions</h4>
        <label>
            <input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
            Enable User Registration
        </label>
        <div></div>

        <label>
            <input type="checkbox" name="enforce_unique_email" <?= $enforceUniqueEmail ? 'checked' : '' ?>>
            Enforce Unique Email (strict: disallow duplicate emails; uncheck for relaxed mode)
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

        <!-- Upload & Session -->
        <h4>Upload & Session</h4>

        <label for="default_file_expiry">Default File Expiry (upload form)</label>
        <select name="default_file_expiry" id="default_file_expiry">
            <option value="1_minute" <?= $defaultFileExpiry === '1_minute' ? 'selected' : '' ?>>1 Minute</option>
            <option value="30_minutes" <?= $defaultFileExpiry === '30_minutes' ? 'selected' : '' ?>>30 Minutes</option>
            <option value="1_hour" <?= $defaultFileExpiry === '1_hour' ? 'selected' : '' ?>>1 Hour</option>
            <option value="6_hours" <?= $defaultFileExpiry === '6_hours' ? 'selected' : '' ?>>6 Hours</option>
            <option value="1_day" <?= $defaultFileExpiry === '1_day' ? 'selected' : '' ?>>1 Day</option>
            <option value="1_week" <?= $defaultFileExpiry === '1_week' ? 'selected' : '' ?>>1 Week</option>
            <option value="1_month" <?= $defaultFileExpiry === '1_month' ? 'selected' : '' ?>>1 Month</option>
            <option value="1_year" <?= $defaultFileExpiry === '1_year' ? 'selected' : '' ?>>1 Year</option>
            <option value="never" <?= $defaultFileExpiry === 'never' ? 'selected' : '' ?>>Never</option>
        </select>

        <label>
            <input type="checkbox" name="thumbnails_enabled" <?= $thumbnailsEnabled ? 'checked' : '' ?>>
            Enable Thumbnail Generation (for image uploads)
        </label>
        <div></div>

        <label for="session_timeout_minutes">Session Timeout (minutes)</label>
        <input type="number" name="session_timeout_minutes" id="session_timeout_minutes" value="<?= sanitize_data($sessionTimeoutMinutes) ?>" min="0">
        <small>0 = until browser close.</small>

        <label>
            <input type="checkbox" name="install_warning_enabled" <?= $installWarningEnabled ? 'checked' : '' ?>>
            Show install.php Security Warning (when install.php still exists)
        </label>
        <div></div>

        <!-- Maintenance & Debug -->
        <h4>Maintenance & Debug</h4>

        <label>
            <input type="checkbox" name="maintenance_mode" <?= ($maintenanceMode ?? false) ? 'checked' : '' ?>>
            Maintenance Mode (block non-admins until disabled)
        </label>
        <div></div>

        <label>
            <input type="checkbox" name="debug_mode" <?= ($debugMode ?? false) ? 'checked' : '' ?>>
            Debug Mode (show PHP errors; disable in production)
        </label>
        <div></div>

        <label for="log_path">Log File Path</label>
        <input type="text" name="log_path" id="log_path" value="<?= sanitize_data($logPath ?? '') ?>" placeholder="e.g. logs/app.log">
        <small>Relative to project root; leave empty to disable file logging.</small>

        <label for="log_level">Log Level</label>
        <select name="log_level" id="log_level">
            <option value="debug" <?= ($logLevel ?? '') === 'debug' ? 'selected' : '' ?>>Debug</option>
            <option value="info" <?= ($logLevel ?? '') === 'info' ? 'selected' : '' ?>>Info</option>
            <option value="warning" <?= ($logLevel ?? 'warning') === 'warning' ? 'selected' : '' ?>>Warning</option>
            <option value="error" <?= ($logLevel ?? '') === 'error' ? 'selected' : '' ?>>Error</option>
        </select>

        <!-- Branding -->
        <h4>Branding & Appearance</h4>

        <label for="logo_url">Custom Logo URL</label>
        <input type="url" name="logo_url" id="logo_url" value="<?= sanitize_data($logoUrl ?? '') ?>" placeholder="https://example.com/logo.png">
        <small>Leave empty to use site name as text.</small>

        <label for="favicon_url">Custom Favicon URL</label>
        <input type="url" name="favicon_url" id="favicon_url" value="<?= sanitize_data($faviconUrl ?? '') ?>" placeholder="https://example.com/favicon.ico">

        <label for="welcome_message">Welcome Banner / Message</label>
        <textarea name="welcome_message" id="welcome_message" rows="3" placeholder="Optional message shown on homepage"><?= sanitize_data($welcomeMessage ?? '') ?></textarea>

        <label for="theme">Default Theme</label>
        <select name="theme" id="theme">
            <option value="light" <?= ($theme ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
            <option value="dark" <?= ($theme ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
        </select>
        <small>Users can toggle theme via the switcher in the header.</small>

        <label for="file_icons">Custom File Icons (JSON)</label>
        <textarea name="file_icons" id="file_icons" rows="4" placeholder='{"pdf":"📄","doc":"📝"}'><?= sanitize_data($fileIconsJson ?? '') ?></textarea>
        <small>Override default icons: extension or MIME → emoji/URL. Leave empty for defaults.</small>

        <!-- Terms of Service -->
        <h4>Terms of Service / Acceptable Use</h4>

        <label>
            <input type="checkbox" name="tos_enabled" id="tos_enabled" <?= ($tosEnabled ?? false) ? 'checked' : '' ?>>
            Require ToS acceptance before upload
        </label>
        <div></div>

        <label for="tos_text">ToS / Acceptable Use text</label>
        <textarea name="tos_text" id="tos_text" rows="4" placeholder="Enter your Terms of Service or Acceptable Use policy..."><?= sanitize_data($tosText ?? '') ?></textarea>
        <small>Shown on upload page; users must check a box to confirm before uploading.</small>

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
