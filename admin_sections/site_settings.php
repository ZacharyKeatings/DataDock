<section class="admin-settings">
    <h3 class="page-title">Site Settings</h3>

    <form method="post" class="settings-form">
        <input type="hidden" name="section" value="site">

        <div class="settings-card">
            <h4 class="settings-card-title">General</h4>
            <div class="settings-card-body">
                <div class="settings-row">
                    <label for="site_name">Site Name</label>
                    <input type="text" name="site_name" id="site_name" value="<?= sanitize_data($siteName) ?>" required>
                </div>
                <div class="settings-row">
                    <label for="admin_contact_email">Admin Contact Email</label>
                    <input type="email" name="admin_contact_email" id="admin_contact_email" value="<?= sanitize_data($adminContactEmail) ?>" placeholder="Optional">
                </div>
                <p class="settings-hint">Shown in footer; leave empty to hide.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">User Permissions</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
                        Enable User Registration
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="enforce_unique_email" <?= $enforceUniqueEmail ? 'checked' : '' ?>>
                        Enforce Unique Email (strict mode; uncheck for relaxed)
                    </label>
                </div>
                <div class="settings-row">
                    <label for="max_file_size">Max File Size <span class="settings-unit">(bytes)</span></label>
                    <input type="number" name="max_file_size" id="max_file_size" value="<?= sanitize_data($maxFileSize) ?>" required>
                </div>
                <p class="settings-hint" id="max_file_size_hint">≈ <?= format_filesize($maxFileSize) ?></p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">User Upload Limits</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" id="user_max_files_enabled" name="user_max_files_enabled" <?= $userMaxFilesEnabled ? 'checked' : '' ?>>
                        Enforce Max Files Per User
                    </label>
                </div>
                <div class="settings-row settings-row-split">
                    <label for="user_max_files">Max Files</label>
                    <input type="number" name="user_max_files" id="user_max_files" value="<?= sanitize_data($userMaxFiles) ?>" min="0" <?= !$userMaxFilesEnabled ? 'disabled' : '' ?>>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" id="user_max_storage_enabled" name="user_max_storage_enabled" <?= $userMaxStorageEnabled ? 'checked' : '' ?>>
                        Enforce Max Storage Per User
                    </label>
                </div>
                <div class="settings-row settings-row-split">
                    <label for="user_max_storage">Max Storage <span class="settings-unit">(bytes)</span></label>
                    <input type="number" name="user_max_storage" id="user_max_storage" value="<?= sanitize_data($userMaxStorage) ?>" min="0" <?= !$userMaxStorageEnabled ? 'disabled' : '' ?>>
                </div>
                <p class="settings-hint" id="user_max_storage_hint">≈ <?= format_filesize($userMaxStorage) ?></p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Guest Uploads</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" id="guest_uploads_enabled" name="guest_uploads_enabled" <?= $guestUploadsEnabled ? 'checked' : '' ?>>
                        Allow Guest Uploads
                    </label>
                </div>
                <div class="settings-row settings-row-split">
                    <label for="guest_max_files">Max Files</label>
                    <input type="number" name="guest_max_files" id="guest_max_files" value="<?= sanitize_data($guestMaxFiles) ?>" min="0" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>
                </div>
                <div class="settings-row settings-row-split">
                    <label for="guest_max_storage">Max Storage <span class="settings-unit">(bytes)</span></label>
                    <input type="number" name="guest_max_storage" id="guest_max_storage" value="<?= sanitize_data($guestMaxStorage) ?>" min="0" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>
                </div>
                <p class="settings-hint" id="guest_max_storage_hint">≈ <?= format_filesize($guestMaxStorage) ?></p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Storage</h4>
            <div class="settings-card-body">
                <div class="settings-row">
                    <label for="storage_base_path">Custom Storage Base Path</label>
                    <input type="text" name="storage_base_path" id="storage_base_path" value="<?= sanitize_data($storageBasePath ?? '') ?>" placeholder="e.g. /var/data/datadock">
                </div>
                <p class="settings-hint">Absolute or relative to project root. Creates uploads/ and thumbnails/ subdirs. Leave empty for default.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Brute Force Protection</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" id="brute_force_enabled" name="brute_force_enabled" <?= $bruteForceEnabled ? 'checked' : '' ?>>
                        Enable Brute Force Protection
                    </label>
                </div>
                <div class="settings-row-grid">
                    <div class="settings-row">
                        <label for="max_attempts">Max Attempts</label>
                        <input type="number" name="max_attempts" id="max_attempts" value="<?= sanitize_data($maxAttempts) ?>" min="1" required>
                    </div>
                    <div class="settings-row">
                        <label for="lockout_minutes">Lockout (min)</label>
                        <input type="number" name="lockout_minutes" id="lockout_minutes" value="<?= sanitize_data($lockoutMinutes) ?>" min="1" required>
                    </div>
                    <div class="settings-row">
                        <label for="lockout_window">Window (min)</label>
                        <input type="number" name="lockout_window" id="lockout_window" value="<?= sanitize_data($lockoutWindow) ?>" min="1" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Upload & Session</h4>
            <div class="settings-card-body">
                <div class="settings-row">
                    <label for="default_file_expiry">Default File Expiry</label>
                    <select name="default_file_expiry" id="default_file_expiry">
                        <?php foreach (['1_minute'=>'1 Minute','30_minutes'=>'30 Minutes','1_hour'=>'1 Hour','6_hours'=>'6 Hours','1_day'=>'1 Day','1_week'=>'1 Week','1_month'=>'1 Month','1_year'=>'1 Year','never'=>'Never'] as $k => $v): ?>
                        <option value="<?= $k ?>"<?= $defaultFileExpiry === $k ? ' selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="thumbnails_enabled" <?= $thumbnailsEnabled ? 'checked' : '' ?>>
                        Enable Thumbnail Generation
                    </label>
                </div>
                <div class="settings-row">
                    <label for="session_timeout_minutes">Session Timeout (minutes)</label>
                    <input type="number" name="session_timeout_minutes" id="session_timeout_minutes" value="<?= sanitize_data($sessionTimeoutMinutes) ?>" min="0">
                </div>
                <p class="settings-hint">0 = until browser close.</p>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="install_warning_enabled" <?= $installWarningEnabled ? 'checked' : '' ?>>
                        Show install.php Security Warning
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="public_browsing_enabled" <?= ($publicBrowsingEnabled ?? false) ? 'checked' : '' ?>>
                        Public File Browsing
                    </label>
                </div>
                <p class="settings-hint">When enabled, files marked "public" can be browsed and downloaded by anyone.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Maintenance & Debug</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="maintenance_mode" <?= ($maintenanceMode ?? false) ? 'checked' : '' ?>>
                        Maintenance Mode (block non-admins)
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="debug_mode" <?= ($debugMode ?? false) ? 'checked' : '' ?>>
                        Debug Mode (show PHP errors)
                    </label>
                </div>
                <div class="settings-row">
                    <label for="log_path">Log File Path</label>
                    <input type="text" name="log_path" id="log_path" value="<?= sanitize_data($logPath ?? '') ?>" placeholder="e.g. logs/app.log">
                </div>
                <p class="settings-hint">Relative to project root; leave empty to disable.</p>
                <div class="settings-row">
                    <label for="log_level">Log Level</label>
                    <select name="log_level" id="log_level">
                        <option value="debug"<?= ($logLevel ?? '') === 'debug' ? ' selected' : '' ?>>Debug</option>
                        <option value="info"<?= ($logLevel ?? '') === 'info' ? ' selected' : '' ?>>Info</option>
                        <option value="warning"<?= ($logLevel ?? 'warning') === 'warning' ? ' selected' : '' ?>>Warning</option>
                        <option value="error"<?= ($logLevel ?? '') === 'error' ? ' selected' : '' ?>>Error</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Branding & Appearance</h4>
            <div class="settings-card-body">
                <div class="settings-row">
                    <label for="logo_url">Custom Logo URL</label>
                    <input type="url" name="logo_url" id="logo_url" value="<?= sanitize_data($logoUrl ?? '') ?>" placeholder="https://example.com/logo.png">
                </div>
                <div class="settings-row">
                    <label for="favicon_url">Custom Favicon URL</label>
                    <input type="url" name="favicon_url" id="favicon_url" value="<?= sanitize_data($faviconUrl ?? '') ?>" placeholder="https://example.com/favicon.ico">
                </div>
                <div class="settings-row">
                    <label for="welcome_message">Welcome Banner</label>
                    <textarea name="welcome_message" id="welcome_message" rows="3" placeholder="Optional message on homepage"><?= sanitize_data($welcomeMessage ?? '') ?></textarea>
                </div>
                <div class="settings-row">
                    <label for="theme">Default Theme</label>
                    <select name="theme" id="theme">
                        <option value="light"<?= ($theme ?? 'light') === 'light' ? ' selected' : '' ?>>Light</option>
                        <option value="dark"<?= ($theme ?? '') === 'dark' ? ' selected' : '' ?>>Dark</option>
                    </select>
                </div>
                <div class="settings-row">
                    <label for="file_icons">Custom File Icons (JSON)</label>
                    <textarea name="file_icons" id="file_icons" rows="3" placeholder='{"pdf":"https://example.com/pdf.svg"}'><?= sanitize_data($fileIconsJson ?? '') ?></textarea>
                </div>
                <p class="settings-hint">Extension or MIME → emoji/URL. Leave empty for defaults.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Terms of Service</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="tos_enabled" id="tos_enabled" <?= ($tosEnabled ?? false) ? 'checked' : '' ?>>
                        Require ToS acceptance before upload
                    </label>
                </div>
                <div class="settings-row">
                    <label for="tos_text">ToS / Acceptable Use text</label>
                    <textarea name="tos_text" id="tos_text" rows="4" placeholder="Enter your Terms of Service..."><?= sanitize_data($tosText ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="settings-form-actions">
            <button type="submit" class="btn btn-primary">Update Settings</button>
        </div>
    </form>
</section>

<script>
(function() {
    const formatBytes = (n) => {
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n/1024).toFixed(2) + ' KB';
        if (n < 1073741824) return (n/1048576).toFixed(2) + ' MB';
        return (n/1073741824).toFixed(2) + ' GB';
    };
    const updateHint = (inputId, hintId) => {
        const inp = document.getElementById(inputId);
        const hint = document.getElementById(hintId);
        if (!inp || !hint) return;
        const update = () => { hint.textContent = '≈ ' + formatBytes(parseInt(inp.value) || 0); };
        inp.addEventListener('input', update);
    };
    updateHint('max_file_size', 'max_file_size_hint');
    updateHint('user_max_storage', 'user_max_storage_hint');
    updateHint('guest_max_storage', 'guest_max_storage_hint');

    const toggleInputs = (checkboxId, inputIds) => {
        const cb = document.getElementById(checkboxId);
        const inputs = inputIds.map(id => document.getElementById(id));
        if (!cb) return;
        const toggle = () => inputs.forEach(i => { if (i) i.disabled = !cb.checked; });
        cb.addEventListener('change', toggle);
        toggle();
    };
    toggleInputs('user_max_files_enabled', ['user_max_files']);
    toggleInputs('user_max_storage_enabled', ['user_max_storage']);
    toggleInputs('guest_uploads_enabled', ['guest_max_files', 'guest_max_storage']);
    toggleInputs('brute_force_enabled', ['max_attempts', 'lockout_minutes', 'lockout_window']);
})();
</script>
