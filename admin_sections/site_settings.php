<section class="admin-settings">
    <h2 class="page-title">Site Settings</h2>

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
                        <input type="checkbox" name="email_verification_enabled" <?= !empty($emailVerificationEnabled) ? 'checked' : '' ?>>
                        Require email verification for new accounts
                    </label>
                </div>
                <p class="settings-hint">Outbound mail is not configured yet. When this is on, new signups stay signed out until they open the confirmation link shown after registration, or an admin confirms them in User Management. Existing accounts stay confirmed.</p>
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
                    <input type="text" name="storage_base_path" id="storage_base_path" value="<?= sanitize_data($storageBasePath ?? '') ?>" placeholder="Leave empty to place storage outside the web root">
                </div>
                <p class="settings-hint">Leave this empty on shared hosting. The next page load creates <code>datadock-storage</code> next to <code>public_html</code> (or <code>www</code> / <code>htdocs</code>) and moves <code>uploads/</code> and <code>thumbnails/</code> there. If the host will not allow that, files stay in the site folder and the web server is told to deny direct access. Set a path only when you want a specific directory.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Organization &amp; deduplication (v2.0)</h4>
            <div class="settings-card-body">
                <input type="hidden" name="deduplicate_storage" value="0">
                <input type="hidden" name="folders_enabled" value="0">
                <input type="hidden" name="tags_enabled" value="0">
                <input type="hidden" name="pastes_enabled" value="0">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="deduplicate_storage" value="1" <?= !empty($deduplicateStorage) ? 'checked' : '' ?>>
                        Deduplicate by SHA-256 (reuse one on-disk copy per partition when the same file is uploaded again)
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="folders_enabled" value="1" <?= !empty($foldersEnabledSetting) ? 'checked' : '' ?>>
                        Enable folders on the dashboard
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="tags_enabled" value="1" <?= !empty($tagsEnabledSetting) ? 'checked' : '' ?>>
                        Enable tags (edit file, filter on dashboard)
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="pastes_enabled" value="1" <?= !empty($pastesEnabledSetting) ? 'checked' : '' ?>>
                        Enable plaintext pastes (unlisted text sharing)
                    </label>
                </div>
                <p class="settings-hint">Manage storage roots and per-user assignment under <a href="admin.php?section=storage">Storage partitions</a>.</p>
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
                <div class="settings-row">
                    <label for="trusted_proxies">Trusted proxies</label>
                    <input type="text" name="trusted_proxies" id="trusted_proxies" value="<?= sanitize_data($trustedProxies ?? '') ?>" placeholder="Leave empty on shared hosting">
                </div>
                <p class="settings-hint">IP allowlists, login lockouts, and upload limits use the address that connected to PHP. Forwarded headers are ignored unless that address is listed here (comma-separated IPs or CIDR blocks, for example <code>203.0.113.10, 10.0.0.0/8</code>).</p>
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
                <div class="settings-row">
                    <label for="filetype_policy_mode">File type policy</label>
                    <select name="filetype_policy_mode" id="filetype_policy_mode">
                        <option value="off"<?= ($filetypeMode ?? 'off') === 'off' ? ' selected' : '' ?>>Off (block only dangerous types)</option>
                        <option value="allow"<?= ($filetypeMode ?? '') === 'allow' ? ' selected' : '' ?>>Allow only the extensions below</option>
                        <option value="deny"<?= ($filetypeMode ?? '') === 'deny' ? ' selected' : '' ?>>Block the extensions below</option>
                    </select>
                </div>
                <div class="settings-row">
                    <label for="filetype_policy_extensions">Extensions</label>
                    <textarea name="filetype_policy_extensions" id="filetype_policy_extensions" rows="3" placeholder="apk, pdf, png"><?= sanitize_data($filetypeExtensions ?? '') ?></textarea>
                </div>
                <p class="settings-hint" id="filetype-policy-warning" hidden></p>
                <p class="settings-hint">Separate extensions with commas or spaces. Executable and server-script types such as php, exe, and js stay blocked even if you allow them.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Operational alerts</h4>
            <div class="settings-card-body">
                <p class="settings-hint">Show warnings on the admin overview when disk usage or per-user quotas approach limits.</p>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="ops_storage_partition_percent_enabled" <?= !empty($opsStoragePartitionPercentEnabled) ? 'checked' : '' ?>>
                        Alert when a storage partition’s disk is nearly full
                    </label>
                </div>
                <div class="settings-row">
                    <label for="ops_storage_partition_percent_threshold">Disk usage threshold (%)</label>
                    <input type="number" name="ops_storage_partition_percent_threshold" id="ops_storage_partition_percent_threshold" class="input-compact" value="<?= (int) $opsStoragePartitionPercentThreshold ?>" min="1" max="100">
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="ops_user_quota_percent_enabled" <?= !empty($opsUserQuotaPercentEnabled) ? 'checked' : '' ?>>
                        Alert when a user (or guest) nears file count or storage quota
                    </label>
                </div>
                <div class="settings-row">
                    <label for="ops_user_quota_percent_threshold">Quota usage threshold (%)</label>
                    <input type="number" name="ops_user_quota_percent_threshold" id="ops_user_quota_percent_threshold" class="input-compact" value="<?= (int) $opsUserQuotaPercentThreshold ?>" min="1" max="100">
                </div>
                <p class="settings-hint">Requires per-user limits or guest limits to be enabled for quota alerts. Partition alerts use free space on the filesystem that contains each partition root.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Hotlink monitoring</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="hotlink_logging_enabled" <?= $hotlinkLoggingEnabled ? 'checked' : '' ?>>
                        Log off-site referers (downloads, thumbnails, avatars)
                    </label>
                </div>
                <p class="settings-hint">When enabled, requests that include a <code>Referer</code> from another hostname are stored in <a href="admin.php?section=hotlinks">Admin → Hotlink log</a>. Empty or same-site referers are ignored.</p>
                <div class="settings-row">
                    <label for="hotlink_trusted_hosts">Extra trusted hostnames</label>
                    <input type="text" name="hotlink_trusted_hosts" id="hotlink_trusted_hosts" value="<?= sanitize_data($hotlinkTrustedHosts) ?>" placeholder="e.g. cdn.example.com, app.example.com">
                </div>
                <p class="settings-hint">Comma- or space-separated. The current site host is always trusted. Use this if assets are legitimately loaded from a CDN or second domain.</p>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="hotlink_block_enabled" <?= !empty($hotlinkBlockEnabled) ? 'checked' : '' ?>>
                        Block hotlinks from other hosts
                    </label>
                </div>
                <p class="settings-hint">When enabled, downloads, ZIP files, thumbnails, and avatars are denied if the referer is another host and is not in the trusted list. Direct visits with no referer still work.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Password rules</h4>
            <div class="settings-card-body">
                <div class="settings-row">
                    <label for="password_min_length">Minimum password length</label>
                    <input type="number" name="password_min_length" id="password_min_length" class="input-compact" value="<?= (int) ($passwordMinLength ?? 6) ?>" min="6" max="128">
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="password_complexity" <?= !empty($passwordComplexity) ? 'checked' : '' ?>>
                        Require an uppercase letter, a lowercase letter, and a number
                    </label>
                </div>
                <p class="settings-hint">Applies to registration, password changes, and password resets. Existing passwords are not forced to change.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Admin IP allowlist</h4>
            <div class="settings-card-body">
                <div class="settings-row">
                    <label for="admin_ip_allowlist">Allowed IPs and CIDR blocks</label>
                    <textarea name="admin_ip_allowlist" id="admin_ip_allowlist" rows="3" placeholder="Leave empty to allow every address"><?= sanitize_data($adminIpAllowlist ?? '') ?></textarea>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="admin_ip_allowlist_confirm" value="1">
                        Save even if my current IP is not in this list
                    </label>
                </div>
                <p class="settings-hint">One address or IPv4 CIDR per line, or separated by commas. Empty means the admin panel is not restricted. The same check applies to destructive admin actions.</p>
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
                    <label for="session_timeout_minutes">Session idle timeout (minutes)</label>
                    <input type="number" name="session_timeout_minutes" id="session_timeout_minutes" value="<?= sanitize_data($sessionTimeoutMinutes) ?>" min="0">
                </div>
                <p class="settings-hint">How long a signed-in user may be inactive before the next request signs them out. 0 = no idle limit (session still ends on logout). Does not replace server or PHP garbage collection.</p>
                <div class="settings-row">
                    <label for="security_headers_mode">HTTP security headers (PHP)</label>
                    <select name="security_headers_mode" id="security_headers_mode">
                        <option value="off"<?= $securityHeadersMode === 'off' ? ' selected' : '' ?>>Off (default)</option>
                        <option value="recommended"<?= $securityHeadersMode === 'recommended' ? ' selected' : '' ?>>Recommended (nosniff, referrer, frame sameorigin)</option>
                        <option value="strict"<?= $securityHeadersMode === 'strict' ? ' selected' : '' ?>>Strict (+ COOP, CSP for this app; see README)</option>
                    </select>
                </div>
                <p class="settings-hint">Sent on normal HTML pages. For TLS/HSTS and duplicates, configure your reverse proxy; see <code>examples/nginx-security-headers.conf</code> and <code>examples/apache-security-headers.conf</code>. Env override: <code>DATADOCK_SECURITY_HEADERS</code>.</p>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="remember_device_enabled" <?= $rememberDeviceEnabled ? 'checked' : '' ?>>
                        Offer “remember this device” on login (longer session cookie only)
                    </label>
                </div>
                <div class="settings-row settings-row-split">
                    <label for="remember_device_cookie_days">Remember cookie max (days)</label>
                    <input type="number" name="remember_device_cookie_days" id="remember_device_cookie_days" value="<?= (int) $rememberDeviceCookieDays ?>" min="1" max="365">
                </div>
                <p class="settings-hint">When checked at login, the session cookie can persist across browser restarts up to this many days; idle timeout above still applies. Not a separate auth token.</p>
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
                        <input type="checkbox" name="read_only_mode" <?= !empty($readOnlyMode) ? 'checked' : '' ?>>
                        Read-only / archive mode (no uploads, no new accounts; downloads only)
                    </label>
                </div>
                <p class="settings-hint">Use for frozen archives. Overrides guest uploads and registration for all non-admin actions. Env: <code>DATADOCK_READ_ONLY=1</code>.</p>
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
                        <?php foreach (datadock_theme_catalog() as $themeSlug => $themeMeta): ?>
                            <option value="<?= sanitize_data($themeSlug) ?>"<?= ($theme ?? 'light') === $themeSlug ? ' selected' : '' ?>><?= sanitize_data($themeMeta['label']) ?><?= !empty($themeMeta['custom']) ? ' (custom)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="settings-hint">Add more themes under <a href="admin.php?section=themes">Themes</a>. Guests always use this theme. Signed-in users can choose their own on their profile.</p>
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

        <div class="settings-card">
            <h4 class="settings-card-title">Privacy notice</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="privacy_enabled" id="privacy_enabled" <?= !empty($privacyEnabled) ? 'checked' : '' ?>>
                        Publish a privacy page
                    </label>
                </div>
                <div class="settings-row">
                    <label for="privacy_text">Privacy notice</label>
                    <textarea name="privacy_text" id="privacy_text" rows="4" placeholder="Shown on privacy.php and linked from the footer"><?= sanitize_data($privacyText ?? '') ?></textarea>
                </div>
                <p class="settings-hint">This is the public page for this site. It is separate from the repository PRIVACY.md file.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Public grid, download quota, and upload scan</h4>
            <div class="settings-card-body">
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="public_grid_enabled" <?= !empty($publicGridEnabled) ? 'checked' : '' ?>>
                        Show the public file index as a thumbnail grid
                    </label>
                </div>
                <div class="settings-row settings-row-checkbox">
                    <label>
                        <input type="checkbox" name="download_quota_enabled" <?= !empty($downloadQuotaEnabled) ? 'checked' : '' ?>>
                        Limit download bytes in a time window
                    </label>
                </div>
                <div class="settings-row">
                    <label for="download_quota_window">Window (minutes)</label>
                    <input type="number" name="download_quota_window" id="download_quota_window" min="1" max="10080" value="<?= (int) ($downloadQuotaWindow ?? 60) ?>">
                </div>
                <div class="settings-row">
                    <label for="download_quota_user">Max bytes per account</label>
                    <input type="number" name="download_quota_user" id="download_quota_user" min="0" step="any" value="<?= sanitize_data((string) ($downloadQuotaUserDisplay[0] ?? 0)) ?>">
                    <select name="download_quota_user_unit">
                        <?php foreach (['b' => 'B', 'k' => 'KB', 'm' => 'MB', 'g' => 'GB'] as $unit => $label): ?>
                            <option value="<?= $unit ?>"<?= (($downloadQuotaUserDisplay[1] ?? 'm') === $unit) ? ' selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="settings-row">
                    <label for="download_quota_file">Max bytes per file</label>
                    <input type="number" name="download_quota_file" id="download_quota_file" min="0" step="any" value="<?= sanitize_data((string) ($downloadQuotaFileDisplay[0] ?? 0)) ?>">
                    <select name="download_quota_file_unit">
                        <?php foreach (['b' => 'B', 'k' => 'KB', 'm' => 'MB', 'g' => 'GB'] as $unit => $label): ?>
                            <option value="<?= $unit ?>"<?= (($downloadQuotaFileDisplay[1] ?? 'm') === $unit) ? ' selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="settings-hint">Zero means that side of the budget is not enforced. Guests are counted by IP. A failed check refuses the download.</p>
                <div class="settings-row">
                    <label for="upload_scan_mode">Upload scan</label>
                    <select name="upload_scan_mode" id="upload_scan_mode">
                        <?php foreach (['off' => 'Off', 'command' => 'Local command', 'clamd' => 'clamd socket'] as $mode => $label): ?>
                            <option value="<?= $mode ?>"<?= (($uploadScanMode ?? 'off') === $mode) ? ' selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="settings-row">
                    <label for="upload_scan_command">Command</label>
                    <input type="text" name="upload_scan_command" id="upload_scan_command" value="<?= sanitize_data($uploadScanCommand ?? '') ?>" placeholder="/usr/bin/clamscan --no-summary">
                </div>
                <div class="settings-row">
                    <label for="upload_scan_socket">clamd socket</label>
                    <input type="text" name="upload_scan_socket" id="upload_scan_socket" value="<?= sanitize_data($uploadScanSocket ?? '') ?>" placeholder="/var/run/clamav/clamd.ctl or 127.0.0.1:3310">
                </div>
                <p class="settings-hint">Off by default. No scanner or signatures are included. A failed scan, a missing command, or an unreachable socket leaves the upload pending.</p>
            </div>
        </div>

        <div class="settings-card">
            <h4 class="settings-card-title">Identity</h4>
            <div class="settings-card-body">
                <div class="settings-row">
                    <label for="totp_policy">Authenticator policy</label>
                    <select name="totp_policy" id="totp_policy">
                        <option value="off"<?= (($totpPolicy ?? 'off') === 'off') ? ' selected' : '' ?>>Off</option>
                        <option value="optional"<?= (($totpPolicy ?? '') === 'optional') ? ' selected' : '' ?>>Optional</option>
                        <option value="required_admin"<?= (($totpPolicy ?? '') === 'required_admin') ? ' selected' : '' ?>>Required for admins</option>
                    </select>
                </div>
                <p class="settings-hint">Users enroll from Profile. Required admins can still open Profile and log out until they enroll. Local passwords stay available.</p>
                <div class="settings-row settings-row-checkbox">
                    <label><input type="checkbox" name="oidc_enabled" <?= !empty($oidcCfg['enabled']) ? 'checked' : '' ?>> Enable OpenID Connect</label>
                </div>
                <div class="settings-row"><label for="oidc_issuer">Issuer URL</label><input type="url" name="oidc_issuer" id="oidc_issuer" value="<?= sanitize_data($oidcCfg['issuer'] ?? '') ?>" placeholder="https://auth.example.com/application/o/datadock"></div>
                <div class="settings-row"><label for="oidc_client_id">Client ID</label><input type="text" name="oidc_client_id" id="oidc_client_id" value="<?= sanitize_data($oidcCfg['client_id'] ?? '') ?>"></div>
                <div class="settings-row"><label for="oidc_client_secret">Client secret</label><input type="password" name="oidc_client_secret" id="oidc_client_secret" value="<?= sanitize_data($oidcCfg['client_secret'] ?? '') ?>"></div>
                <div class="settings-row"><label for="oidc_role_claim">Role claim</label><input type="text" name="oidc_role_claim" id="oidc_role_claim" value="<?= sanitize_data($oidcCfg['role_claim'] ?? 'groups') ?>"></div>
                <div class="settings-row"><label for="oidc_admin_groups">Admin groups</label><input type="text" name="oidc_admin_groups" id="oidc_admin_groups" value="<?= sanitize_data($oidcCfg['admin_groups'] ?? '') ?>" placeholder="comma-separated"></div>
                <div class="settings-row settings-row-checkbox">
                    <label><input type="checkbox" name="ldap_enabled" <?= !empty($ldapCfg['enabled']) ? 'checked' : '' ?>> Enable LDAP</label>
                </div>
                <p class="settings-hint">Used when the PHP LDAP extension is installed. A local password is tried first, so an admin can still sign in if the directory is down.</p>
                <div class="settings-row"><label for="ldap_host">Host</label><input type="text" name="ldap_host" id="ldap_host" value="<?= sanitize_data($ldapCfg['host'] ?? '') ?>"></div>
                <div class="settings-row"><label for="ldap_port">Port</label><input type="number" name="ldap_port" id="ldap_port" value="<?= (int) ($ldapCfg['port'] ?? 389) ?>"></div>
                <div class="settings-row">
                    <label for="ldap_encryption">Encryption</label>
                    <select name="ldap_encryption" id="ldap_encryption">
                        <?php foreach (['none' => 'None', 'starttls' => 'STARTTLS', 'ldaps' => 'LDAPS'] as $mode => $label): ?>
                            <option value="<?= $mode ?>"<?= (($ldapCfg['encryption'] ?? 'none') === $mode) ? ' selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="settings-row"><label for="ldap_user_dn">User DN template</label><input type="text" name="ldap_user_dn" id="ldap_user_dn" value="<?= sanitize_data($ldapCfg['user_dn_template'] ?? '') ?>" placeholder="uid=%s,ou=users,dc=example,dc=com"></div>
                <div class="settings-row"><label for="ldap_base_dn">Base DN</label><input type="text" name="ldap_base_dn" id="ldap_base_dn" value="<?= sanitize_data($ldapCfg['base_dn'] ?? '') ?>"></div>
                <div class="settings-row"><label for="ldap_bind_dn">Service bind DN</label><input type="text" name="ldap_bind_dn" id="ldap_bind_dn" value="<?= sanitize_data($ldapCfg['bind_dn'] ?? '') ?>"></div>
                <div class="settings-row"><label for="ldap_bind_password">Service bind password</label><input type="password" name="ldap_bind_password" id="ldap_bind_password" value="<?= sanitize_data($ldapCfg['bind_password'] ?? '') ?>"></div>
                <div class="settings-row"><label for="ldap_user_filter">User filter</label><input type="text" name="ldap_user_filter" id="ldap_user_filter" value="<?= sanitize_data($ldapCfg['user_filter'] ?? '(uid=%s)') ?>"></div>
                <div class="settings-row"><label for="ldap_admin_group">Admin group</label><input type="text" name="ldap_admin_group" id="ldap_admin_group" value="<?= sanitize_data($ldapCfg['admin_group'] ?? '') ?>"></div>
                <div class="settings-row settings-row-checkbox">
                    <label><input type="checkbox" name="forward_auth_enabled" <?= !empty($forwardCfg['enabled']) ? 'checked' : '' ?>> Trust identity headers from a proxy</label>
                </div>
                <div class="settings-row"><label for="forward_trusted_proxies">Trusted proxy IPs</label><textarea name="forward_trusted_proxies" id="forward_trusted_proxies" rows="2"><?= sanitize_data($forwardCfg['trusted_proxies'] ?? '') ?></textarea></div>
                <div class="settings-row"><label for="forward_user_header">User header</label><input type="text" name="forward_user_header" id="forward_user_header" value="<?= sanitize_data($forwardCfg['user_header'] ?? 'Remote-User') ?>"></div>
                <div class="settings-row"><label for="forward_group_header">Group header</label><input type="text" name="forward_group_header" id="forward_group_header" value="<?= sanitize_data($forwardCfg['group_header'] ?? 'Remote-Groups') ?>"></div>
                <div class="settings-row"><label for="forward_admin_groups">Admin groups</label><input type="text" name="forward_admin_groups" id="forward_admin_groups" value="<?= sanitize_data($forwardCfg['admin_groups'] ?? '') ?>"></div>
                <p class="settings-hint">Headers are read only when the connection comes from a listed proxy. If the proxy is down and sends nothing, the local login form still works. Use “Use a local account” on the login page when the proxy is injecting a user you do not want.</p>
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

    const caution = ['html','htm','xhtml','svg','xml','swf','apk','ipa','aab','jar','deb','rpm','dmg','iso','pkg','app','wasm','ps1','command'];
    const alwaysBlocked = ['php','phtml','phar','exe','js','mjs','sh','bat','py','pl','cgi','asp','aspx','jsp','vbs','dll','msi','hta'];
    const policyBox = document.getElementById('filetype_policy_extensions');
    const policyMode = document.getElementById('filetype_policy_mode');
    const policyWarn = document.getElementById('filetype-policy-warning');
    const refreshPolicyWarn = () => {
        if (!policyBox || !policyWarn || !policyMode || policyMode.value === 'off') {
            if (policyWarn) policyWarn.hidden = true;
            return;
        }
        const listed = policyBox.value.toLowerCase().split(/[^a-z0-9]+/).filter(Boolean);
        const risky = caution.filter(ext => listed.includes(ext));
        const blocked = alwaysBlocked.filter(ext => listed.includes(ext));
        const parts = [];
        if (risky.length) parts.push('Potentially unsafe: ' + risky.join(', ') + '. These can contain active or executable content.');
        if (blocked.length) parts.push('Still blocked by the server: ' + blocked.join(', ') + '.');
        policyWarn.hidden = parts.length === 0;
        policyWarn.textContent = parts.join(' ');
    };
    if (policyBox && policyMode) {
        policyBox.addEventListener('input', refreshPolicyWarn);
        policyMode.addEventListener('change', refreshPolicyWarn);
        refreshPolicyWarn();
    }
})();
</script>
