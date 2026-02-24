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
                        <input type="checkbox" name="invite_only_registration" <?= !empty($inviteOnlyRegistration) ? 'checked' : '' ?>>
                        Invite-only registration (requires signup token; generate in User Management)
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="enforce_unique_email" <?= $enforceUniqueEmail ? 'checked' : '' ?>>
                        Enforce Unique Email (strict mode; uncheck for relaxed)
                    </label>
                </div>
                <div class="settings-row settings-row-split">
                    <label for="max_file_size">Max File Size</label>
                    <span class="settings-input-with-unit">
                        <input type="number" name="max_file_size" id="max_file_size" value="<?= sanitize_data($maxFileSizeDisplay[0]) ?>" min="0" step="any" required>
                        <select name="max_file_size_unit" id="max_file_size_unit" aria-label="Unit">
                            <option value="b"<?= ($maxFileSizeDisplay[1] === 'b') ? ' selected' : '' ?>>Bytes</option>
                            <option value="k"<?= ($maxFileSizeDisplay[1] === 'k') ? ' selected' : '' ?>>KB</option>
                            <option value="m"<?= ($maxFileSizeDisplay[1] === 'm') ? ' selected' : '' ?>>MB</option>
                            <option value="g"<?= ($maxFileSizeDisplay[1] === 'g') ? ' selected' : '' ?>>GB</option>
                        </select>
                    </span>
                </div>
                <p class="settings-hint" id="max_file_size_hint">≈ <?= format_filesize($maxFileSize) ?></p>
                <p class="settings-hint settings-hint-server">Current server: <code>upload_max_filesize</code> = <?= format_filesize($serverUploadMaxBytes) ?>, <code>post_max_size</code> = <?= format_filesize($serverPostMaxBytes) ?>. Effective per-file max is the lower of app and server.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Server PHP Limits (optional override)</h4>
            <div class="settings-card-body">
                <p class="settings-hint">Override PHP’s upload limits by writing a <code>.user.ini</code> file in the project root. Takes effect on the next request on many hosts; not supported everywhere. Leave overrides empty to keep current server values.</p>
                <div class="settings-row settings-row-split">
                    <label for="server_upload_max">Override <code>upload_max_filesize</code></label>
                    <span class="settings-input-with-unit">
                        <input type="number" name="server_upload_max" id="server_upload_max" value="<?= $serverOverrideUploadDisplay !== null ? sanitize_data($serverOverrideUploadDisplay[0]) : '' ?>" min="0" step="any">
                        <select name="server_upload_max_unit" id="server_upload_max_unit" aria-label="Unit">
                            <option value="b"<?= ($serverOverrideUploadDisplay !== null && $serverOverrideUploadDisplay[1] === 'b') ? ' selected' : '' ?>>Bytes</option>
                            <option value="k"<?= ($serverOverrideUploadDisplay !== null && $serverOverrideUploadDisplay[1] === 'k') ? ' selected' : '' ?>>KB</option>
                            <option value="m"<?= ($serverOverrideUploadDisplay === null || $serverOverrideUploadDisplay[1] === 'm') ? ' selected' : '' ?>>MB</option>
                            <option value="g"<?= ($serverOverrideUploadDisplay !== null && $serverOverrideUploadDisplay[1] === 'g') ? ' selected' : '' ?>>GB</option>
                        </select>
                    </span>
                </div>
                <p class="settings-hint">Current: <?= format_filesize($serverUploadMaxBytes) ?></p>
                <div class="settings-row settings-row-split">
                    <label for="server_post_max">Override <code>post_max_size</code></label>
                    <span class="settings-input-with-unit">
                        <input type="number" name="server_post_max" id="server_post_max" value="<?= $serverOverridePostDisplay !== null ? sanitize_data($serverOverridePostDisplay[0]) : '' ?>" min="0" step="any">
                        <select name="server_post_max_unit" id="server_post_max_unit" aria-label="Unit">
                            <option value="b"<?= ($serverOverridePostDisplay !== null && $serverOverridePostDisplay[1] === 'b') ? ' selected' : '' ?>>Bytes</option>
                            <option value="k"<?= ($serverOverridePostDisplay !== null && $serverOverridePostDisplay[1] === 'k') ? ' selected' : '' ?>>KB</option>
                            <option value="m"<?= ($serverOverridePostDisplay === null || $serverOverridePostDisplay[1] === 'm') ? ' selected' : '' ?>>MB</option>
                            <option value="g"<?= ($serverOverridePostDisplay !== null && $serverOverridePostDisplay[1] === 'g') ? ' selected' : '' ?>>GB</option>
                        </select>
                    </span>
                </div>
                <p class="settings-hint">Current: <?= format_filesize($serverPostMaxBytes) ?></p>
                <?php if (file_exists(__DIR__ . '/../.user.ini')): ?>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="server_clear_user_ini" value="1">
                        Remove server overrides (delete .user.ini)
                    </label>
                </div>
                <?php endif; ?>
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
                    <label for="user_max_storage">Max Storage</label>
                    <span class="settings-input-with-unit">
                        <input type="number" name="user_max_storage" id="user_max_storage" value="<?= sanitize_data($userMaxStorageDisplay[0]) ?>" min="0" step="any" <?= !$userMaxStorageEnabled ? 'disabled' : '' ?>>
                        <select name="user_max_storage_unit" id="user_max_storage_unit" aria-label="Unit" <?= !$userMaxStorageEnabled ? 'disabled' : '' ?>>
                            <option value="b"<?= ($userMaxStorageDisplay[1] === 'b') ? ' selected' : '' ?>>Bytes</option>
                            <option value="k"<?= ($userMaxStorageDisplay[1] === 'k') ? ' selected' : '' ?>>KB</option>
                            <option value="m"<?= ($userMaxStorageDisplay[1] === 'm') ? ' selected' : '' ?>>MB</option>
                            <option value="g"<?= ($userMaxStorageDisplay[1] === 'g') ? ' selected' : '' ?>>GB</option>
                        </select>
                    </span>
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
                    <label for="guest_max_storage">Max Storage</label>
                    <span class="settings-input-with-unit">
                        <input type="number" name="guest_max_storage" id="guest_max_storage" value="<?= sanitize_data($guestMaxStorageDisplay[0]) ?>" min="0" step="any" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>
                        <select name="guest_max_storage_unit" id="guest_max_storage_unit" aria-label="Unit" <?= !$guestUploadsEnabled ? 'disabled' : '' ?>>
                            <option value="b"<?= ($guestMaxStorageDisplay[1] === 'b') ? ' selected' : '' ?>>Bytes</option>
                            <option value="k"<?= ($guestMaxStorageDisplay[1] === 'k') ? ' selected' : '' ?>>KB</option>
                            <option value="m"<?= ($guestMaxStorageDisplay[1] === 'm') ? ' selected' : '' ?>>MB</option>
                            <option value="g"<?= ($guestMaxStorageDisplay[1] === 'g') ? ' selected' : '' ?>>GB</option>
                        </select>
                    </span>
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
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="adaptive_cooldown_enabled" <?= $adaptiveCooldownEnabled ? 'checked' : '' ?>>
                        Adaptive cooldown (progressive lockout per IP across usernames)
                    </label>
                </div>
                <div class="settings-row">
                    <label for="adaptive_cooldown_ip_window_minutes">Per-IP failure window (minutes)</label>
                    <input type="number" name="adaptive_cooldown_ip_window_minutes" id="adaptive_cooldown_ip_window_minutes" value="<?= sanitize_data($adaptiveCooldownWindow) ?>" min="1">
                </div>
                <p class="settings-hint">More failed logins from the same IP → longer lockout (5 / 15 / 60 min).</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Upload Rate Limiting</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="rate_limit_uploads_enabled" <?= $rateLimitUploadsEnabled ? 'checked' : '' ?>>
                        Enable upload rate limiting (per IP and per user)
                    </label>
                </div>
                <div class="settings-row-grid">
                    <div class="settings-row">
                        <label for="rate_limit_window_minutes">Window (min)</label>
                        <input type="number" name="rate_limit_window_minutes" id="rate_limit_window_minutes" value="<?= sanitize_data($rateLimitWindowMinutes) ?>" min="1">
                    </div>
                    <div class="settings-row">
                        <label for="rate_limit_max_per_ip">Max uploads per IP</label>
                        <input type="number" name="rate_limit_max_per_ip" id="rate_limit_max_per_ip" value="<?= sanitize_data($rateLimitMaxPerIp) ?>" min="0">
                    </div>
                    <div class="settings-row">
                        <label for="rate_limit_max_per_user">Max uploads per user</label>
                        <input type="number" name="rate_limit_max_per_user" id="rate_limit_max_per_user" value="<?= sanitize_data($rateLimitMaxPerUser) ?>" min="0">
                    </div>
                </div>
                <p class="settings-hint">0 = no limit for that dimension. Throttles uploads within the time window.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Upload Security</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="rewrite_file_extension" <?= $rewriteFileExtension ? 'checked' : '' ?>>
                        Store files without original extension (restore name on download)
                    </label>
                </div>
                <p class="settings-hint">Reduces risk of executing uploaded files by extension on the server.</p>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="upload_quarantine_enabled" <?= $uploadQuarantineEnabled ? 'checked' : '' ?>>
                        Upload quarantine (new uploads hidden until admin approval)
                    </label>
                </div>
                <p class="settings-hint">For public instances: new uploads are pending until approved in File Management.</p>
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
                <div class="settings-row">
                    <label for="trash_retention_days">Trash retention (days)</label>
                    <input type="number" name="trash_retention_days" id="trash_retention_days" value="<?= (int)($trashRetentionDays ?? 30) ?>" min="0" max="3650">
                </div>
                <p class="settings-hint">How long deleted files stay in trash before auto-purge. 0 = keep until manually purged.</p>
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
    const unitToMultiplier = { b: 1, k: 1024, m: 1048576, g: 1073741824 };
    const valueAndUnitToBytes = (value, unit) => (parseFloat(value) || 0) * (unitToMultiplier[unit] || 1);
    const updateHintWithUnit = (inputId, unitId, hintId) => {
        const inp = document.getElementById(inputId);
        const unitSel = document.getElementById(unitId);
        const hint = document.getElementById(hintId);
        if (!inp || !unitSel || !hint) return;
        const update = () => {
            const bytes = valueAndUnitToBytes(inp.value, unitSel.value);
            hint.textContent = '≈ ' + formatBytes(bytes);
        };
        inp.addEventListener('input', update);
        unitSel.addEventListener('change', update);
    };
    updateHintWithUnit('max_file_size', 'max_file_size_unit', 'max_file_size_hint');
    updateHintWithUnit('user_max_storage', 'user_max_storage_unit', 'user_max_storage_hint');
    updateHintWithUnit('guest_max_storage', 'guest_max_storage_unit', 'guest_max_storage_hint');

    const toggleInputs = (checkboxId, inputIds) => {
        const cb = document.getElementById(checkboxId);
        const inputs = inputIds.map(id => document.getElementById(id));
        if (!cb) return;
        const toggle = () => inputs.forEach(i => { if (i) i.disabled = !cb.checked; });
        cb.addEventListener('change', toggle);
        toggle();
    };
    toggleInputs('user_max_files_enabled', ['user_max_files']);
    toggleInputs('user_max_storage_enabled', ['user_max_storage', 'user_max_storage_unit']);
    toggleInputs('guest_uploads_enabled', ['guest_max_files', 'guest_max_storage', 'guest_max_storage_unit']);
    toggleInputs('brute_force_enabled', ['max_attempts', 'lockout_minutes', 'lockout_window']);
})();
</script>
