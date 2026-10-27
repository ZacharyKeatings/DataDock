<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
$readOnly = datadock_read_only_enabled($settings);
require_once __DIR__ . '/includes/pastes.php';
require_once __DIR__ . '/includes/dashboard_actions.php';
require_once __DIR__ . '/includes/user_trust.php';
require_once __DIR__ . '/includes/notices.php';
require_once __DIR__ . '/includes/access_control.php';

datadock_process_dashboard_post($pdo);
datadock_maybe_record_storage_snapshot($pdo, (int) $_SESSION['user_id']);

$pageTitle = "Your Files";
require_once __DIR__ . '/includes/header.php';

// Relative URLs (same directory as this script); avoids broken SCRIPT_NAME / rewrite edge cases.
$dashUrl = 'dashboard.php';
$uploadUrl = 'upload.php';

$userId = $_SESSION['user_id'];
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);
$foldersEnabled = !isset($settings['folders_enabled']) || !empty($settings['folders_enabled']);
$tagsEnabled = !isset($settings['tags_enabled']) || !empty($settings['tags_enabled']);

$currentFolderId = 0;
if ($foldersEnabled && isset($_GET['folder']) && is_numeric($_GET['folder'])) {
    $currentFolderId = (int) $_GET['folder'];
    if ($currentFolderId < 0) {
        $currentFolderId = 0;
    }
    if ($currentFolderId > 0) {
        $chk = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
        $chk->execute([$currentFolderId, $userId]);
        if (!$chk->fetch()) {
            $currentFolderId = 0;
        }
    }
}

// Filters (search, date, type, visibility, expiry, folder, tag)
$expiryParam = isset($_GET['expiry']) && in_array($_GET['expiry'], ['has', 'none'], true) ? $_GET['expiry'] : null;
$listOptions = [
    'user_id' => $userId,
    'exclude_trashed' => true,
    'expiry_filter' => $expiryParam === 'has' ? 'valid_has' : ($expiryParam === 'none' ? 'valid_none' : 'valid'),
    'search' => isset($_GET['q']) ? trim($_GET['q']) : '',
    'date_from' => isset($_GET['date_from']) ? trim($_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? trim($_GET['date_to']) : '',
    'filetype' => isset($_GET['type']) ? trim($_GET['type']) : '',
    'visibility' => isset($_GET['visibility']) && in_array($_GET['visibility'], ['public', 'private'], true) ? $_GET['visibility'] : 'all',
];
if ($foldersEnabled) {
    $listOptions['folder_id'] = $currentFolderId;
}
if ($tagsEnabled && !empty($_GET['tag']) && is_numeric($_GET['tag'])) {
    $listOptions['tag_id'] = (int) $_GET['tag'];
    $tagChk = $pdo->prepare('SELECT id FROM tags WHERE id = ? AND user_id = ?');
    $tagChk->execute([(int) $_GET['tag'], $userId]);
    if (!$tagChk->fetch()) {
        unset($listOptions['tag_id']);
    }
}
[$where, $params] = build_files_list_where($listOptions);
$stmt = $pdo->prepare("SELECT * FROM files f WHERE $where ORDER BY f.upload_date DESC");
$stmt->execute($params);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch files shared with user (exclude quarantined and trashed)
$stmt = $pdo->prepare("
    SELECT f.*, u.username as shared_by_username
    FROM files f
    JOIN file_shares fs ON f.id = fs.file_id
    JOIN users u ON fs.shared_by_user_id = u.id
    WHERE fs.shared_with_user_id = ?
    AND f.deleted_at IS NULL
    AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)
    AND (f.expiry_date IS NULL OR f.expiry_date > UTC_TIMESTAMP())
    ORDER BY f.upload_date DESC
");
$stmt->execute([$userId]);
$sharedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct file types for filter dropdown (user's non-trashed files)
$stmt = $pdo->prepare("SELECT DISTINCT filetype FROM files WHERE user_id = ? AND deleted_at IS NULL AND filetype IS NOT NULL AND filetype != '' ORDER BY filetype");
$stmt->execute([$userId]);
$distinctTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$subfolders = [];
$folderTrail = [];
$allFoldersFlat = [];
if ($foldersEnabled) {
    $stmt = $pdo->prepare('SELECT id, name FROM folders WHERE user_id = ? AND parent_id = ? ORDER BY name ASC');
    $stmt->execute([$userId, $currentFolderId]);
    $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fid = $currentFolderId;
    while ($fid > 0) {
        $stmt = $pdo->prepare('SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$fid, $userId]);
        $fr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fr) {
            break;
        }
        array_unshift($folderTrail, $fr);
        $fid = (int) $fr['parent_id'];
    }

    $stmt = $pdo->prepare('SELECT id, name, parent_id FROM folders WHERE user_id = ? ORDER BY parent_id, name');
    $stmt->execute([$userId]);
    $allFoldersFlat = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$userTags = [];
if ($tagsEnabled) {
    $stmt = $pdo->prepare('SELECT id, name FROM tags WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$userId]);
    $userTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$shareFoldersList = [];
try {
    $sfs = $pdo->prepare('SELECT id, title, token, expires_at, created_at FROM share_folders WHERE user_id = ? ORDER BY created_at DESC LIMIT 15');
    $sfs->execute([$userId]);
    $shareFoldersList = $sfs->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $shareFoldersList = [];
}

$expiringSoonFiles = [];
$expiringSoonCount = 0;
try {
    $expCount = $pdo->prepare('
        SELECT COUNT(*) FROM files
        WHERE user_id = ? AND deleted_at IS NULL
          AND expiry_date IS NOT NULL
          AND expiry_date > UTC_TIMESTAMP()
          AND expiry_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)
    ');
    $expCount->execute([$userId]);
    $expiringSoonCount = (int) $expCount->fetchColumn();
    $expStmt = $pdo->prepare('
        SELECT id, original_name, filename, expiry_date
        FROM files
        WHERE user_id = ? AND deleted_at IS NULL
          AND expiry_date IS NOT NULL
          AND expiry_date > UTC_TIMESTAMP()
          AND expiry_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)
        ORDER BY expiry_date ASC
        LIMIT 8
    ');
    $expStmt->execute([$userId]);
    $expiringSoonFiles = $expStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $expiringSoonFiles = [];
    $expiringSoonCount = 0;
}

$liveTokenLinks = [];
$liveSignedLinks = [];
try {
    $tok = $pdo->prepare('
        SELECT dt.id, dt.token, dt.expires_at, dt.max_uses, dt.use_count, f.original_name, f.filename
        FROM download_tokens dt
        JOIN files f ON f.id = dt.file_id
        WHERE f.user_id = ? AND f.deleted_at IS NULL
          AND (dt.expires_at IS NULL OR dt.expires_at > UTC_TIMESTAMP())
          AND (dt.max_uses = 0 OR dt.use_count < dt.max_uses)
        ORDER BY dt.id DESC
        LIMIT 20
    ');
    $tok->execute([$userId]);
    $liveTokenLinks = $tok->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $liveTokenLinks = [];
}
try {
    $sigStmt = $pdo->prepare('
        SELECT s.id, s.file_id, s.expires_at, f.original_name, f.filename
        FROM signed_links s
        JOIN files f ON f.id = s.file_id
        WHERE s.user_id = ? AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP() AND f.deleted_at IS NULL
        ORDER BY s.id DESC
        LIMIT 20
    ');
    $sigStmt->execute([$userId]);
    $liveSignedLinks = $sigStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $liveSignedLinks = [];
}

$folderById = [];
foreach ($allFoldersFlat as $folderRow) {
    $folderById[(int) $folderRow['id']] = $folderRow;
}
$folderOptions = [];
foreach ($allFoldersFlat as $folderRow) {
    $parts = [];
    $cur = $folderRow;
    $guard = 0;
    while ($cur && $guard < 20) {
        array_unshift($parts, (string) ($cur['name'] ?? ''));
        $parentId = (int) ($cur['parent_id'] ?? 0);
        $cur = ($parentId > 0 && isset($folderById[$parentId])) ? $folderById[$parentId] : null;
        $guard++;
    }
    $folderOptions[] = ['id' => (int) $folderRow['id'], 'label' => implode(' / ', $parts)];
}

$dashQueryBase = [];
if (!empty($_GET['q'])) {
    $dashQueryBase['q'] = $_GET['q'];
}
if (!empty($_GET['date_from'])) {
    $dashQueryBase['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $dashQueryBase['date_to'] = $_GET['date_to'];
}
if (!empty($_GET['type'])) {
    $dashQueryBase['type'] = $_GET['type'];
}
if (!empty($_GET['visibility'])) {
    $dashQueryBase['visibility'] = $_GET['visibility'];
}
if (!empty($_GET['expiry'])) {
    $dashQueryBase['expiry'] = $_GET['expiry'];
}
if ($tagsEnabled && !empty($_GET['tag'])) {
    $dashQueryBase['tag'] = $_GET['tag'];
}

$storageUsedBytes = datadock_user_total_storage_bytes($pdo, (int) $userId);
$storageCountStmt = $pdo->prepare('SELECT COUNT(*) FROM files WHERE user_id = ? AND deleted_at IS NULL');
$storageCountStmt->execute([$userId]);
$storageFileCount = (int) $storageCountStmt->fetchColumn();
$userLimits = is_array($settings['user_limits'] ?? null) ? $settings['user_limits'] : [];
$storageQuotaBytes = (int) ($userLimits['max_storage'] ?? 0);
$storageQuotaEnabled = !empty($userLimits['max_storage_enabled']) && $storageQuotaBytes > 0;
$storagePercent = $storageQuotaEnabled ? min(100, (int) round(($storageUsedBytes / $storageQuotaBytes) * 100)) : 0;
$storageMeterClass = 'storage-meter-fill';
if ($storagePercent >= 100) {
    $storageMeterClass .= ' is-full';
} elseif ($storagePercent >= 85) {
    $storageMeterClass .= ' is-high';
}
$activeFilterCount = 0;
foreach (['date_from', 'date_to', 'type', 'visibility', 'expiry', 'tag'] as $filterKey) {
    if (!empty($_GET[$filterKey])) {
        $activeFilterCount++;
    }
}
datadock_refresh_account_notices($pdo, (int) $userId, $settings, $expiringSoonCount, $storagePercent, $storageQuotaEnabled);
?>


<?php
$uploadHere = $uploadUrl;
if ($foldersEnabled && $currentFolderId > 0) {
    $uploadHere .= '?folder=' . (int) $currentFolderId;
}
?>
<div class="page-section">
    <div class="dash-heading">
        <h2 class="page-title">Your files</h2>
        <?php if (empty($readOnly)): ?>
        <a href="<?= sanitize_data($uploadHere) ?>" class="btn btn-primary"><?= ($foldersEnabled && $currentFolderId > 0) ? 'Upload to this folder' : 'Upload files' ?></a>
        <?php if ($foldersEnabled && $currentFolderId > 0): ?>
        <a href="create_folder_link.php?folder=<?= (int) $currentFolderId ?>" class="btn btn-quiet">Live folder link</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="storage-meter">
        <?= (int) $storageFileCount ?> <?= $storageFileCount === 1 ? 'file' : 'files' ?>
        · <?= format_filesize($storageUsedBytes) ?>
        <?php if ($storageQuotaEnabled): ?>
            of <?= format_filesize($storageQuotaBytes) ?>
            <div class="storage-meter-track" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $storagePercent ?>" aria-label="Storage used">
                <div class="<?= sanitize_data($storageMeterClass) ?>" style="width: <?= (int) $storagePercent ?>%"></div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($readOnly)): ?>
    <div class="flash warning persistent" role="status"><strong>Read-only mode:</strong> Uploads and file changes are disabled; downloads still work.</div>
    <?php endif; ?>

    <?php if ($expiringSoonCount > 0): ?>
    <div class="stats-alert">
        <?= icon_svg('warning') ?> <strong><?= (int) $expiringSoonCount ?></strong>
        file<?= $expiringSoonCount === 1 ? '' : 's' ?> expire within 7 days.
        <?php
        $expNames = [];
        foreach ($expiringSoonFiles as $expFile) {
            $expNames[] = (string) ($expFile['original_name'] ?? $expFile['filename'] ?? 'file');
        }
        ?>
        <span class="expiring-names"><?= sanitize_data(implode(', ', $expNames)) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($foldersEnabled): ?>
    <nav class="folder-breadcrumb" aria-label="Folder path">
        <?php
        $dashRoot = $dashUrl . (!empty($dashQueryBase) ? '?' . http_build_query($dashQueryBase) : '');
        ?>
        <a href="<?= sanitize_data($dashRoot) ?>">All files</a>
        <?php foreach ($folderTrail as $ti => $tr): ?>
            <span aria-hidden="true"> / </span>
            <?php
            $q = $dashQueryBase;
            $q['folder'] = (int) $tr['id'];
            $href = $dashUrl . '?' . http_build_query($q);
            ?>
            <?php if ($ti < count($folderTrail) - 1): ?>
                <a href="<?= sanitize_data($href) ?>"><?= sanitize_data($tr['name']) ?></a>
            <?php else: ?>
                <strong><?= sanitize_data($tr['name']) ?></strong>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php if (!empty($subfolders)): ?>
    <ul class="folder-list">
        <?php
        foreach ($subfolders as $sf) {
            $q = $dashQueryBase;
            $q['folder'] = (int) $sf['id'];
            $href = $dashUrl . '?' . http_build_query($q);
            echo '<li><a href="' . sanitize_data($href) . '" class="btn btn-quiet btn-small">' . icon_svg('folder') . ' ' . sanitize_data($sf['name']) . '</a></li>';
        }
        ?>
    </ul>
    <?php endif; ?>
    <?php if (empty($readOnly)): ?>
    <form method="post" action="" class="folder-create-form">
        <input type="hidden" name="datadock_folder_create" value="1">
        <input type="hidden" name="redirect_folder" value="<?= (int) $currentFolderId ?>">
        <input type="hidden" name="parent_id" value="<?= (int) $currentFolderId ?>">
        <div class="filter-group">
            <label for="new-folder-name" class="filter-label">New folder</label>
            <input type="text" name="name" id="new-folder-name" maxlength="255" placeholder="Name" required>
        </div>
        <button type="submit" class="btn btn-quiet btn-small">Create folder</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>

    <form method="get" action="<?= sanitize_data($dashUrl) ?>" class="dash-toolbar">
        <?php if ($foldersEnabled && $currentFolderId > 0): ?>
        <input type="hidden" name="folder" value="<?= (int) $currentFolderId ?>">
        <?php endif; ?>
        <div class="dash-toolbar-main">
            <label for="filter-q" class="visually-hidden">Search files</label>
            <input type="search" name="q" id="filter-q" class="dash-search" value="<?= isset($_GET['q']) ? sanitize_data($_GET['q']) : '' ?>" placeholder="Search filename or description">
            <button type="submit" class="btn btn-quiet">Search</button>
        </div>
        <details class="dash-filters"<?= $activeFilterCount > 0 ? ' open' : '' ?>>
            <summary>Filters<?php if ($activeFilterCount > 0): ?> (<?= (int) $activeFilterCount ?>)<?php endif; ?></summary>
            <div class="dash-filters-grid">
                <div class="filter-group">
                    <label for="filter-date_from" class="filter-label">From date</label>
                    <input type="date" name="date_from" id="filter-date_from" value="<?= isset($_GET['date_from']) ? sanitize_data($_GET['date_from']) : '' ?>">
                </div>
        <div class="filter-group">
            <label for="filter-date_to" class="filter-label">To date</label>
            <input type="date" name="date_to" id="filter-date_to" value="<?= isset($_GET['date_to']) ? sanitize_data($_GET['date_to']) : '' ?>">
        </div>
        <div class="filter-group">
            <label for="filter-type" class="filter-label">Type</label>
            <select name="type" id="filter-type">
                <option value="">All types</option>
                <?php foreach ($distinctTypes as $ft): ?>
                    <option value="<?= sanitize_data($ft) ?>"<?= (isset($_GET['type']) && $_GET['type'] === $ft) ? ' selected' : '' ?>><?= sanitize_data(get_friendly_filetype($ft)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($publicBrowsingEnabled): ?>
        <div class="filter-group">
            <label for="filter-visibility" class="filter-label">Visibility</label>
            <select name="visibility" id="filter-visibility">
                <option value="">All</option>
                <option value="public"<?= (isset($_GET['visibility']) && $_GET['visibility'] === 'public') ? ' selected' : '' ?>>Public</option>
                <option value="private"<?= (isset($_GET['visibility']) && $_GET['visibility'] === 'private') ? ' selected' : '' ?>>Private</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
            <label for="filter-expiry" class="filter-label">Expiry</label>
            <select name="expiry" id="filter-expiry">
                <option value="">All</option>
                <option value="has"<?= (isset($_GET['expiry']) && $_GET['expiry'] === 'has') ? ' selected' : '' ?>>Has expiry</option>
                <option value="none"<?= (isset($_GET['expiry']) && $_GET['expiry'] === 'none') ? ' selected' : '' ?>>No expiry</option>
            </select>
        </div>
        <?php if ($tagsEnabled && !empty($userTags)): ?>
        <div class="filter-group">
            <label for="filter-tag" class="filter-label">Tag</label>
            <select name="tag" id="filter-tag">
                <option value="">All tags</option>
                <?php foreach ($userTags as $tg): ?>
                    <option value="<?= (int) $tg['id'] ?>"<?= (isset($_GET['tag']) && (int) $_GET['tag'] === (int) $tg['id']) ? ' selected' : '' ?>><?= sanitize_data($tg['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
                    <button type="submit" class="btn btn-quiet">Apply</button>
                    <?php
                    $hasFilters = $activeFilterCount > 0 || !empty($_GET['q']);
                    $clearHref = $dashUrl;
                    if ($foldersEnabled && $currentFolderId > 0) {
                        $clearHref .= '?folder=' . (int) $currentFolderId;
                    }
                    ?>
                    <?php if ($hasFilters): ?>
                    <a href="<?= sanitize_data($clearHref) ?>" class="btn btn-quiet">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    </form>

    <?php if ($files): ?>
        <div class="bulk-actions-bar">
            <label for="bulkActionSelect" class="bulk-label">Bulk actions:</label>
            <select id="bulkActionSelect" class="bulk-select">
                <option value="">Choose action…</option>
                <option value="zip">Download selected as ZIP</option>
                <?php if (empty($readOnly)): ?>
                <option value="share_bundle">Create share folder link…</option>
                <?php endif; ?>
                <?php if ($publicBrowsingEnabled && empty($readOnly)): ?>
                <option value="public">Make selected public</option>
                <option value="private">Make selected private</option>
                <?php endif; ?>
                <?php if ($foldersEnabled && empty($readOnly)): ?>
                <option value="move">Move to folder…</option>
                <?php endif; ?>
                <?php if (empty($readOnly)): ?>
                <option value="delete">Delete selected</option>
                <?php endif; ?>
            </select>
            <?php if ($foldersEnabled && empty($readOnly)): ?>
            <select id="bulkFolderSelect" class="bulk-folder-select" hidden>
                <option value="0">All files</option>
                <?php foreach ($folderOptions as $folderOption): ?>
                    <option value="<?= (int) $folderOption['id'] ?>"><?= sanitize_data($folderOption['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-small" id="bulkApplyBtn" disabled>Apply</button>
        </div>
        <div class="file-list">
            <div class="file-row-dashboard file-header file-row-dashboard-primary">
                <div class="file-row-toggle-cell" aria-hidden="true"></div>
                <div><input type="checkbox" id="selectAll" title="Select all"></div>
                <div>Filename</div>
                <div>Size</div>
                <div>Actions</div>
            </div>

            <?php foreach ($files as $file):
                $isPending = ($file['quarantine_status'] ?? 'approved') === 'pending';
                $fid = (int) $file['id'];
            ?>
                <div class="file-row-expandable">
                    <div class="file-row-dashboard file-row-dashboard-primary">
                        <div class="file-row-toggle-cell">
                            <button type="button" class="file-row-toggle" id="dash-toggle-<?= $fid ?>" aria-expanded="false" aria-controls="dash-details-<?= $fid ?>" aria-label="Show details for <?= htmlspecialchars($file['original_name'] ?? 'file', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="file-row-toggle-icon" aria-hidden="true">▸</span>
                            </button>
                        </div>
                        <div><input type="checkbox" name="ids[]" value="<?= $file['id'] ?>" class="zip-checkbox"<?= $isPending ? ' disabled title="Available after approval"' : '' ?>></div>
                        <div class="file-name-cell">
                            <?php if ($file['thumbnail_path'] && str_starts_with($file['filetype'], 'image/')): ?>
                                <img src="thumbnail.php?id=<?= $fid ?>" alt="" class="thumbnail-inline">
                            <?php else: ?>
                                <?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?>
                            <?php endif; ?>
                            <span><?= sanitize_data($file['original_name']) ?></span>
                            <?php if ($isPending): ?>
                                <span class="badge badge-pending" title="Hidden from public until an admin approves">Pending approval</span>
                            <?php endif; ?>
                        </div>
                        <div><?= format_filesize($file['filesize']) ?></div>
                        <div class="file-actions">
                            <details class="file-actions-dropdown">
                                <summary class="btn btn-quiet btn-small">Actions <span class="dropdown-arrow" aria-hidden="true">▾</span></summary>
                                <div class="dropdown-menu">
                                    <?php if ($isPending): ?>
                                        <span class="dropdown-item dropdown-item-muted">Download, share &amp; link available after approval</span>
                                    <?php else: ?>
                                        <a href="edit_file.php?id=<?= $file['id'] ?>" class="dropdown-item">Edit</a>
                                        <?php if ($foldersEnabled && !empty($allFoldersFlat)): ?>
                                        <form method="post" action="" class="dropdown-item-form move-folder-form">
                                            <input type="hidden" name="datadock_move_file" value="1">
                                            <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                                            <input type="hidden" name="redirect_folder" value="<?= (int) $currentFolderId ?>">
                                            <label for="move-<?= (int) $file['id'] ?>">Move to folder</label>
                                            <select name="folder_id" id="move-<?= (int) $file['id'] ?>" onchange="this.form.submit()">
                                                <option value="">— Root —</option>
                                                <?php
                                                $curF = isset($file['folder_id']) ? (int) $file['folder_id'] : 0;
                                                foreach ($allFoldersFlat as $mf) {
                                                    $mid = (int) $mf['id'];
                                                    $sel = ($mid === $curF) ? ' selected' : '';
                                                    echo '<option value="' . $mid . '"' . $sel . '>' . sanitize_data($mf['name']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($publicBrowsingEnabled): ?>
                                        <form method="post" action="toggle_public.php" class="dropdown-item-form">
                                            <input type="hidden" name="id" value="<?= $file['id'] ?>">
                                            <button type="submit" class="dropdown-item" title="<?= $file['is_public'] ? 'Make private' : 'Make public' ?>">
                                                <span class="btn-icon"><?= icon_svg($file['is_public'] ? 'lock-open' : 'lock') ?></span> <?= $file['is_public'] ? 'Public' : 'Private' ?>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <a href="preview.php?id=<?= $file['id'] ?>" class="dropdown-item">Preview</a>
                                        <a href="replace_file.php?id=<?= $file['id'] ?>" class="dropdown-item">Replace contents</a>
                                        <a href="share.php?id=<?= $file['id'] ?>" class="dropdown-item">Share</a>
                                        <a href="download.php?id=<?= $file['id'] ?>" class="dropdown-item">Download</a>
                                        <a href="create_onetime.php?id=<?= $file['id'] ?>" class="dropdown-item">Share link (token)</a>
                                        <a href="create_signed.php?id=<?= $file['id'] ?>" class="dropdown-item">Signed link (HMAC)</a>
                                    <?php endif; ?>
                                    <form method="post" action="delete.php" class="dropdown-item-form" onsubmit="return confirm('Delete this file?')">
                                        <?= datadock_csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                                        <button type="submit" class="dropdown-item dropdown-item-danger">Delete</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </div>
                    <div class="file-row-details" id="dash-details-<?= $fid ?>" role="region" aria-labelledby="dash-toggle-<?= $fid ?>" hidden>
                        <div class="file-row-details-inner">
                            <dl class="file-details-grid">
                                <dt>Type</dt>
                                <dd title="<?= sanitize_data($file['filetype']) ?>"><?= sanitize_data(get_friendly_filetype($file['filetype'])) ?></dd>
                                <dt>Downloads</dt>
                                <dd><?= (int) ($file['download_count'] ?? 0) ?></dd>
                                <dt>Uploaded</dt>
                                <dd><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></dd>
                                <dt>Expires</dt>
                                <dd><?= $file['expiry_date']
                                    ? '<span class="utc-datetime" data-utc="' . htmlspecialchars($file['expiry_date']) . '"></span>'
                                    : 'Never' ?></dd>
                            </dl>
                            <div class="file-row-checksums">
                                <?php if (!empty($file['checksum_md5']) || !empty($file['checksum_sha256'])): ?>
                                    <?php if (!empty($file['checksum_md5'])): ?>
                                    <div class="checksum-item">
                                        <span class="checksum-line">
                                            <button type="button" class="hash-label" data-hash="<?= sanitize_data($file['checksum_md5']) ?>" data-algo="MD5">MD5</button>
                                            <button type="button" class="btn-copy btn-copy-checksum" data-copy="<?= sanitize_data($file['checksum_md5']) ?>" title="Copy MD5"><?= icon_svg('copy') ?></button>
                                        </span>
                                        <code class="hash-value" aria-live="polite"></code>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($file['checksum_sha256'])): ?>
                                    <div class="checksum-item">
                                        <span class="checksum-line">
                                            <button type="button" class="hash-label" data-hash="<?= sanitize_data($file['checksum_sha256']) ?>" data-algo="SHA256">SHA256</button>
                                            <button type="button" class="btn-copy btn-copy-checksum" data-copy="<?= sanitize_data($file['checksum_sha256']) ?>" title="Copy SHA256"><?= icon_svg('copy') ?></button>
                                        </span>
                                        <code class="hash-value" aria-live="polite"></code>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="checksum-none">No checksums stored</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p><?= ($foldersEnabled && $currentFolderId > 0) ? 'This folder is empty.' : "You haven't uploaded any files yet." ?></p>
            <?php if (empty($readOnly)): ?>
            <a href="<?= sanitize_data($uploadHere) ?>" class="btn btn-primary"><?= ($foldersEnabled && $currentFolderId > 0) ? 'Upload to this folder' : 'Upload files' ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php datadock_echo_owned_pastes($pdo, !empty($readOnly), 'dashboard.php', datadock_pastes_enabled($settings)); ?>

    <?php if (!empty($liveTokenLinks) || !empty($liveSignedLinks)): ?>
    <div class="share-links-panel">
        <h3>Outstanding links</h3>
        <ul>
            <?php
            $linkBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $linkDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $linkDir = ($linkDir === '/' || $linkDir === '\\') ? '' : rtrim($linkDir, '/');
            foreach ($liveTokenLinks as $tokenLink) {
                $tokenUrl = $linkBase . $linkDir . '/download.php?token=' . rawurlencode((string) ($tokenLink['token'] ?? ''));
                $tokenName = (string) ($tokenLink['original_name'] ?? $tokenLink['filename'] ?? 'File');
                $usesLeft = ((int) ($tokenLink['max_uses'] ?? 0)) > 0
                    ? ((int) $tokenLink['max_uses'] - (int) $tokenLink['use_count']) . ' uses left'
                    : 'unlimited uses';
                $tokenExp = $tokenLink['expires_at'] ?? null;
                $tokenExpLabel = ($tokenExp === null || $tokenExp === '') ? 'no expiry' : 'expires ' . $tokenExp . ' UTC';
                echo '<li>' . sanitize_data($tokenName) . ' — share link, ' . sanitize_data($usesLeft) . ', ' . sanitize_data($tokenExpLabel);
                if (empty($readOnly)) {
                    echo ' <form method="post" action="revoke_link.php" class="inline-form compact" onsubmit="return confirm(\'Revoke this link?\');">';
                    echo '<input type="hidden" name="type" value="token"><input type="hidden" name="id" value="' . (int) $tokenLink['id'] . '">';
                    echo '<button type="submit" class="btn btn-danger btn-small">Revoke</button></form>';
                }
                echo '<div class="share-link-row"><input type="text" class="share-link-input" readonly value="' . sanitize_data($tokenUrl) . '" onclick="this.select();">';
                echo '<button type="button" class="btn-copy" data-copy="' . sanitize_data($tokenUrl) . '" title="Copy link">' . icon_svg('copy') . '</button></div></li>';
            }
            foreach ($liveSignedLinks as $signedLink) {
                $signedName = (string) ($signedLink['original_name'] ?? $signedLink['filename'] ?? 'File');
                $signedExp = (string) ($signedLink['expires_at'] ?? '');
                $signedUnix = $signedExp !== '' ? strtotime($signedExp . ' UTC') : false;
                $signedUrl = '';
                if ($signedUnix !== false) {
                    $signedSig = datadock_signed_download_build_v2($pdo, (int) $signedLink['id'], (int) $signedLink['file_id'], (int) $signedUnix);
                    $signedUrl = $linkBase . $linkDir . '/download.php?id=' . (int) $signedLink['file_id'] . '&exp=' . (int) $signedUnix . '&sig=' . rawurlencode($signedSig) . '&sid=' . (int) $signedLink['id'];
                }
                echo '<li>' . sanitize_data($signedName) . ' — signed link';
                if ($signedExp !== '') {
                    echo ', expires ' . sanitize_data($signedExp) . ' UTC';
                }
                if (empty($readOnly)) {
                    echo ' <form method="post" action="revoke_link.php" class="inline-form compact" onsubmit="return confirm(\'Revoke this link?\');">';
                    echo '<input type="hidden" name="type" value="signed"><input type="hidden" name="id" value="' . (int) $signedLink['id'] . '">';
                    echo '<button type="submit" class="btn btn-danger btn-small">Revoke</button></form>';
                }
                if ($signedUrl !== '') {
                    echo '<div class="share-link-row"><input type="text" class="share-link-input" readonly value="' . sanitize_data($signedUrl) . '" onclick="this.select();">';
                    echo '<button type="button" class="btn-copy" data-copy="' . sanitize_data($signedUrl) . '" title="Copy link">' . icon_svg('copy') . '</button></div>';
                }
                echo '</li>';
            }
            ?>
        </ul>
        <p class="settings-hint">Revoking a signed link stops that URL only. Other signed links keep working.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($shareFoldersList)): ?>
    <div class="share-links-panel">
        <h3>Active share folder links</h3>
        <ul>
            <?php
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
            foreach ($shareFoldersList as $sf) {
                $t = $sf['token'] ?? '';
                if (strlen($t) !== 64) {
                    continue;
                }
                $url = $baseUrl . $scriptDir . '/share_folder.php?t=' . rawurlencode($t);
                $title = trim((string) ($sf['title'] ?? ''));
                $label = $title !== '' ? $title : ('Share #' . (int) ($sf['id'] ?? 0));
                $exp = $sf['expires_at'] ?? null;
                $expLabel = ($exp === null || $exp === '') ? 'no auto-expiry' : 'expires ' . sanitize_data($exp) . ' UTC';
                echo '<li>' . sanitize_data($label) . ' — ' . $expLabel;
                echo ' <form method="post" action="revoke_share_folder.php" class="inline-form compact" onsubmit="return confirm(\'Revoke this link?\');">';
                echo '<input type="hidden" name="id" value="' . (int) ($sf['id'] ?? 0) . '">';
                echo '<button type="submit" class="btn btn-danger btn-small">Revoke</button></form>';
                echo '<div class="share-link-row">';
                echo '<input type="text" class="share-link-input" readonly value="' . sanitize_data($url) . '" onclick="this.select();">';
                echo '<button type="button" class="btn-copy" data-copy="' . sanitize_data($url) . '" title="Copy link">' . icon_svg('copy') . '</button>';
                echo '</div></li>';
            }
            ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($sharedFiles)): ?>
<div class="page-section">
    <h2 class="page-title">Shared with You</h2>
    <div class="file-list">
        <div class="file-row-dashboard file-header file-row-dashboard-primary file-row-dashboard-shared">
            <div class="file-row-toggle-cell" aria-hidden="true"></div>
            <div>Filename</div>
            <div>Size</div>
            <div>Actions</div>
        </div>
        <?php foreach ($sharedFiles as $file):
            $sfid = (int) $file['id'];
        ?>
            <div class="file-row-expandable">
                <div class="file-row-dashboard file-row-dashboard-primary file-row-dashboard-shared">
                    <div class="file-row-toggle-cell">
                        <button type="button" class="file-row-toggle" id="shared-toggle-<?= $sfid ?>" aria-expanded="false" aria-controls="shared-details-<?= $sfid ?>" aria-label="Show details for <?= htmlspecialchars($file['original_name'] ?? 'file', ENT_QUOTES, 'UTF-8') ?>">
                            <span class="file-row-toggle-icon" aria-hidden="true">▸</span>
                        </button>
                    </div>
                    <div class="file-name-cell">
                        <?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?>
                        <span><?= sanitize_data($file['original_name']) ?></span>
                    </div>
                    <div><?= format_filesize($file['filesize']) ?></div>
                    <div class="file-actions">
                        <details class="file-actions-dropdown">
                            <summary class="btn btn-quiet btn-small">Actions <span class="dropdown-arrow" aria-hidden="true">▾</span></summary>
                            <div class="dropdown-menu">
                                <a href="download.php?id=<?= $file['id'] ?>" class="dropdown-item">Download</a>
                                <a href="report_file.php?id=<?= (int) $file['id'] ?>&amp;return_to=dashboard.php" class="dropdown-item">Report</a>
                            </div>
                        </details>
                    </div>
                </div>
                <div class="file-row-details" id="shared-details-<?= $sfid ?>" role="region" aria-labelledby="shared-toggle-<?= $sfid ?>" hidden>
                    <div class="file-row-details-inner">
                        <dl class="file-details-grid">
                            <dt>Shared by</dt>
                            <dd><?= user_profile_link($file['shared_by_username'] ?? null) ?></dd>
                            <dt>Type</dt>
                            <dd title="<?= sanitize_data($file['filetype']) ?>"><?= sanitize_data(get_friendly_filetype($file['filetype'])) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            const d = document.createElement('span');
            d.className = 'datetime-date';
            d.textContent = local.toLocaleDateString();
            const t = document.createElement('span');
            t.className = 'datetime-time';
            t.textContent = local.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
            el.textContent = '';
            el.appendChild(d);
            el.appendChild(t);
        } else {
            el.textContent = '—';
        }
    });
    var selectAll = document.getElementById('selectAll');
    var checkboxes = document.querySelectorAll('.zip-checkbox');
    var bulkSelect = document.getElementById('bulkActionSelect');
    var bulkApplyBtn = document.getElementById('bulkApplyBtn');
    var bulkFolder = document.getElementById('bulkFolderSelect');
    function updateBulkState() {
        var n = document.querySelectorAll('.zip-checkbox:checked').length;
        if (bulkFolder) {
            bulkFolder.hidden = !bulkSelect || bulkSelect.value !== 'move';
        }
        bulkApplyBtn.disabled = n === 0 || !bulkSelect.value;
    }
    if (selectAll && checkboxes.length) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
            updateBulkState();
        });
    }
    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateBulkState);
    });
    if (bulkSelect) bulkSelect.addEventListener('change', updateBulkState);
    if (bulkApplyBtn) {
        bulkApplyBtn.addEventListener('click', function () {
            var ids = Array.from(document.querySelectorAll('.zip-checkbox:checked')).map(function (c) { return c.value; });
            if (ids.length === 0) return;
            var action = bulkSelect.value;
            if (action === 'zip') {
                window.location.href = 'download_zip.php?' + ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
            } else if (action === 'share_bundle') {
                window.location.href = 'create_share_folder.php?' + ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
            } else if (action === 'public' || action === 'private') {
                var form = document.createElement('form');
                form.method = 'post';
                form.action = 'bulk_toggle_public.php';
                var pubInput = document.createElement('input');
                pubInput.type = 'hidden';
                pubInput.name = 'public';
                pubInput.value = action === 'public' ? '1' : '0';
                form.appendChild(pubInput);
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            } else if (action === 'move') {
                var form = document.createElement('form');
                form.method = 'post';
                form.action = 'bulk_move.php';
                var folderInput = document.createElement('input');
                folderInput.type = 'hidden';
                folderInput.name = 'folder_id';
                folderInput.value = bulkFolder ? bulkFolder.value : '0';
                form.appendChild(folderInput);
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            } else if (action === 'delete' && confirm('Delete ' + ids.length + ' selected file(s)?')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.action = 'bulk_delete.php';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    document.querySelectorAll('.hash-label').forEach(function (label) {
        var item = label.closest('.checksum-item');
        var code = item ? item.querySelector('.hash-value') : null;
        label.addEventListener('click', function () {
            var hash = label.dataset.hash;
            if (!hash || !code) return;
            if (code.textContent === hash) {
                code.textContent = '';
                code.classList.remove('visible');
            } else {
                code.textContent = hash;
                code.classList.add('visible');
            }
        });
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('.hash-label') || e.target.closest('.hash-value') || e.target.closest('.btn-copy')) return;
        document.querySelectorAll('.hash-value.visible').forEach(function (el) {
            el.textContent = '';
            el.classList.remove('visible');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
