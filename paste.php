<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
require_once __DIR__ . '/includes/pastes.php';

$pastesEnabled = datadock_pastes_enabled($settings);
$readOnly = datadock_read_only_enabled($settings);
$guestUploadsEnabled = !empty($settings['guest_uploads']['enabled']);
$currentUserId = $_SESSION['user_id'] ?? null;
$maxBytes = datadock_paste_max_bytes($settings);
$expiryOffsets = datadock_paste_expiry_offsets();
$defaultExpiry = (string) ($settings['default_file_expiry'] ?? 'never');
if (!array_key_exists($defaultExpiry, $expiryOffsets)) {
    $defaultExpiry = 'never';
}
$tosEnabled = !empty($settings['tos_enabled']);
$tosText = trim($settings['tos_text'] ?? '');

$formTitle = '';
$formContent = '';
$formDuration = $defaultExpiry;
$formBurn = false;

function datadock_paste_plain_response(int $status, string $body, ?string $downloadName = null): void {
    datadock_paste_send_privacy_headers();
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    if ($downloadName !== null) {
        $safe = str_replace(['"', "\r", "\n"], '', $downloadName);
        header('Content-Disposition: attachment; filename="' . $safe . '"');
    }
    echo $body;
    exit;
}

function datadock_paste_render_text(array $paste, bool $receipt, bool $readOnly): void {
    $burned = !empty($paste['_burned']);
    $token = (string) ($paste['token'] ?? '');
    $title = trim((string) ($paste['title'] ?? ''));
    $shareUrl = datadock_paste_share_url($token);
    $rawUrl = $shareUrl . '&raw=1';
    $downloadName = datadock_paste_download_name($title !== '' ? $title : null);
    $heading = $title !== '' ? $title : 'Untitled paste';
    $pageTitle = $heading;
    datadock_paste_send_privacy_headers();
    require __DIR__ . '/includes/header.php';
    ?>
<div class="page-section">
    <h2 class="page-title"><?= sanitize_data($heading) ?></h2>
    <?php if ($receipt): ?>
        <p class="page-description">Anyone with the link below can read this paste. It is not listed on the home page.</p>
        <div class="onetimelink-box">
            <label for="pasteShareUrl" class="visually-hidden">Paste link</label>
            <input type="text" id="pasteShareUrl" class="onetimelink-input" readonly value="<?= sanitize_data($shareUrl) ?>" onclick="this.select();">
            <button type="button" class="btn btn-primary btn-small btn-copy" data-copy="<?= sanitize_data($shareUrl) ?>">Copy link</button>
        </div>
        <p class="settings-hint">Raw link (also counts as a view): <input type="text" id="pasteRawUrl" class="paste-url paste-url-inline" readonly value="<?= sanitize_data($rawUrl) ?>" onclick="this.select();"></p>
    <?php elseif ($burned): ?>
        <div class="flash warning persistent" role="status">This was a one-time paste. It has been deleted and will not open again.</div>
    <?php endif; ?>
    <p class="paste-meta">
        <span>Created <span class="utc-datetime" data-utc="<?= sanitize_data((string) ($paste['created_at'] ?? '')) ?>"></span></span>
        <?php if (!$burned): ?>
            <span><?= (int) ($paste['view_count'] ?? 0) ?> views</span>
        <?php endif; ?>
        <span><?= sanitize_data(format_filesize(strlen((string) ($paste['content'] ?? '')))) ?></span>
        <?php if (!empty($paste['expires_at']) && !$burned): ?>
            <span>Expires <span class="utc-datetime" data-utc="<?= sanitize_data((string) $paste['expires_at']) ?>"></span></span>
        <?php elseif (!$burned): ?>
            <span>No expiry</span>
        <?php endif; ?>
        <?php if (!empty($paste['burn_after_read']) && !$burned): ?>
            <span>Deletes after the first view</span>
        <?php endif; ?>
    </p>
    <div class="paste-actions">
        <button type="button" class="btn btn-quiet btn-small" id="pasteCopyText">Copy text</button>
        <button type="button" class="btn btn-quiet btn-small" id="pasteSave" data-filename="<?= sanitize_data($downloadName) ?>">Save .txt</button>
        <?php if (!$burned): ?>
            <a class="btn btn-quiet btn-small" href="paste.php?t=<?= sanitize_data($token) ?>&amp;raw=1">Raw</a>
        <?php endif; ?>
        <label class="paste-wrap-toggle"><input type="checkbox" id="pasteWrap"> Wrap lines</label>
        <?php if (!$burned && datadock_paste_can_delete($paste) && !$readOnly): ?>
            <form method="post" action="delete_paste.php" class="paste-delete-form" onsubmit="return confirm('Delete this paste? The link will stop working.');">
                <?= datadock_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($paste['id'] ?? 0) ?>">
                <input type="hidden" name="return_to" value="<?= ($receipt || empty($_SESSION['user_id'])) ? 'paste.php' : 'dashboard.php' ?>">
                <button type="submit" class="btn btn-danger btn-small">Delete</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($receipt && !empty($paste['burn_after_read'])): ?>
        <p class="settings-hint">Opening the share link deletes this paste. Copy or save it here first if you still need the text.</p>
    <?php elseif (!$receipt && !$burned): ?>
        <p class="settings-hint">Opening this page, the raw link, or the download URL each count as a view.</p>
    <?php endif; ?>
    <pre class="paste-body" id="pasteBody"><?= htmlspecialchars((string) ($paste['content'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
    <p><a href="paste.php" class="btn btn-primary">New paste</a></p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(function (el) {
        var utc = el.dataset.utc;
        if (!utc) return;
        var local = new Date(utc.replace(' ', 'T') + 'Z');
        if (isNaN(local.getTime())) return;
        el.textContent = local.toLocaleString();
    });
    var body = document.getElementById('pasteBody');
    var wrap = document.getElementById('pasteWrap');
    if (wrap && body) {
        wrap.addEventListener('change', function () {
            body.classList.toggle('is-wrapped', wrap.checked);
        });
    }
    function copyText(value, button, doneLabel) {
        if (!button) return;
        var original = button.textContent;
        function done() {
            button.textContent = doneLabel;
            setTimeout(function () { button.textContent = original; }, 2000);
        }
        function fallbackCopy() {
            var area = document.createElement('textarea');
            area.value = value;
            document.body.appendChild(area);
            area.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            area.remove();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(fallbackCopy);
            return;
        }
        fallbackCopy();
    }
    var copyTextBtn = document.getElementById('pasteCopyText');
    if (copyTextBtn && body) {
        copyTextBtn.addEventListener('click', function () {
            copyText(body.textContent, copyTextBtn, 'Copied');
        });
    }
    var saveBtn = document.getElementById('pasteSave');
    if (saveBtn && body) {
        saveBtn.addEventListener('click', function () {
            var blob = new Blob([body.textContent], { type: 'text/plain;charset=utf-8' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = saveBtn.getAttribute('data-filename') || 'paste.txt';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    }
});
</script>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tokenParam = isset($_GET['t']) ? (string) $_GET['t'] : '';
$createdParam = isset($_GET['created']) ? (string) $_GET['created'] : '';

if ($tokenParam !== '') {
    try {
        $opened = datadock_paste_open($pdo, $tokenParam);
    } catch (Throwable $e) {
        $opened = null;
    }
    $wantsDownload = isset($_GET['dl']) && (string) $_GET['dl'] === '1';
    $wantsRaw = $wantsDownload || (isset($_GET['raw']) && (string) $_GET['raw'] === '1');
    if (!$opened) {
        if ($wantsRaw) {
            datadock_paste_plain_response(404, "This paste is unavailable. It may have expired or already been read.\n");
        }
        datadock_paste_send_privacy_headers();
        http_response_code(404);
        $pageTitle = 'Paste unavailable';
        require __DIR__ . '/includes/header.php';
        echo '<div class="page-section"><h2 class="page-title">Paste unavailable</h2><div class="empty-state"><p>This paste does not exist, has expired, or was already read.</p><a href="paste.php" class="btn btn-primary">New paste</a></div></div>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }
    if ($wantsRaw) {
        $name = $wantsDownload ? datadock_paste_download_name($opened['title'] ?? null) : null;
        datadock_paste_plain_response(200, (string) $opened['content'], $name);
    }
    datadock_paste_render_text($opened, false, $readOnly);
}

if ($createdParam !== '') {
    $created = datadock_paste_by_token($pdo, $createdParam);
    if (!$created || !datadock_paste_is_creator($created)) {
        if ($created && datadock_paste_token_ok($createdParam)) {
            header('Location: paste.php?t=' . rawurlencode($createdParam));
            exit;
        }
        datadock_paste_send_privacy_headers();
        http_response_code(404);
        $pageTitle = 'Paste unavailable';
        require __DIR__ . '/includes/header.php';
        echo '<div class="page-section"><h2 class="page-title">Paste unavailable</h2><div class="empty-state"><p>This paste does not exist or has expired.</p></div></div>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }
    $created['_burned'] = false;
    datadock_paste_render_text($created, true, $readOnly);
}

$canCreate = $pastesEnabled && !$readOnly && ($currentUserId || $guestUploadsEnabled);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pastesEnabled) {
        $_SESSION['flash_error'][] = '❌ Plaintext sharing is turned off.';
        header('Location: index.php');
        exit;
    }
    if ($readOnly) {
        datadock_block_if_read_only($settings, 'paste.php', 'paste.php');
    }
    datadock_reject_invalid_csrf('paste.php');
    if (!$canCreate) {
        $_SESSION['flash_error'][] = '❌ You need an account to share text.';
        header('Location: login.php');
        exit;
    }

    $formTitle = trim(str_replace("\0", '', (string) ($_POST['title'] ?? '')));
    $formTitle = preg_replace('/\s+/', ' ', $formTitle) ?? '';
    $formContent = str_replace(["\r\n", "\r"], "\n", str_replace("\0", '', (string) ($_POST['content'] ?? '')));
    $formDuration = (string) ($_POST['duration'] ?? $defaultExpiry);
    $formBurn = !empty($_POST['burn_after_read']);

    $postMax = return_bytes((string) ini_get('post_max_size'));
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($formContent === '' && $contentLength > $postMax && $postMax > 0) {
        $_SESSION['flash_error'][] = '❌ Paste exceeds the server limit of ' . format_filesize($postMax) . '.';
    } elseif (trim($formContent) === '') {
        $_SESSION['flash_error'][] = '❌ Paste some text before sharing.';
    } elseif (strlen($formContent) > $maxBytes) {
        $_SESSION['flash_error'][] = '❌ Paste exceeds the limit of ' . format_filesize($maxBytes) . '.';
    }
    if ($formTitle !== '' && datadock_paste_strlen($formTitle) > 200) {
        $_SESSION['flash_error'][] = '❌ Title must be 200 characters or fewer.';
    }
    if (!array_key_exists($formDuration, $expiryOffsets)) {
        $formDuration = $defaultExpiry;
    }
    if ($tosEnabled && $tosText !== '' && !isset($_POST['tos_accepted'])) {
        $_SESSION['flash_error'][] = '❌ You must accept the Terms of Service to share text.';
    }

    $clientIp = get_client_ip();
    if (empty($_SESSION['flash_error']) && !check_upload_rate_limit($pdo, $currentUserId ? (int) $currentUserId : null, $clientIp, $settings)) {
        $_SESSION['flash_error'][] = '❌ Too many uploads. Please try again in a few minutes.';
    }

    if (empty($_SESSION['flash_error'])) {
        try {
            $createdRow = datadock_paste_create($pdo, [
                'user_id' => $currentUserId ? (int) $currentUserId : null,
                'guest_id' => $currentUserId ? null : datadock_paste_guest_id(true),
                'title' => $formTitle !== '' ? $formTitle : null,
                'content' => $formContent,
                'expires_at' => datadock_paste_expiry_utc($formDuration, $settings),
                'burn_after_read' => $formBurn ? 1 : 0,
            ]);
            try {
                record_upload_rate_limit($pdo, $clientIp, $currentUserId ? (int) $currentUserId : null, 1);
            } catch (Throwable $e) {
            }
            datadock_log_activity($pdo, 'paste_created', [
                'actor_user_id' => $currentUserId ? (int) $currentUserId : null,
                'actor_guest_id' => $currentUserId ? null : datadock_paste_guest_id(false),
                'detail' => [
                    'paste_id' => $createdRow['id'],
                    'title' => $formTitle,
                    'burn_after_read' => $formBurn ? 1 : 0,
                ],
            ]);
            header('Location: paste.php?created=' . rawurlencode($createdRow['token']));
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'][] = '❌ Could not save that paste. Try again.';
        }
    }
}

$pageTitle = 'Share text';
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title">Share text</h2>
    <p class="page-description">Paste plaintext and get a link, the way a pastebin works. The link is unlisted: only people you send it to can open it.</p>
    <?php if (!$pastesEnabled): ?>
        <div class="empty-state"><p>Plaintext sharing is turned off.</p></div>
    <?php elseif ($readOnly): ?>
        <div class="flash warning persistent" role="status">Read-only mode: new pastes are disabled. Existing links still open.</div>
    <?php elseif (!$currentUserId && !$guestUploadsEnabled): ?>
        <div class="empty-state"><p><a href="login.php">Log in</a> to share text.</p></div>
    <?php else: ?>
        <form method="post" action="paste.php" class="paste-form">
            <?= datadock_csrf_field() ?>
            <?php if ($tosEnabled && $tosText !== ''): ?>
            <div class="tos-block">
                <div class="tos-text"><?= nl2br(sanitize_data($tosText)) ?></div>
                <label class="tos-checkbox">
                    <input type="checkbox" name="tos_accepted" id="tos_accepted" required<?= isset($_POST['tos_accepted']) ? ' checked' : '' ?>>
                    I agree to the Terms of Service / Acceptable Use
                </label>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="paste_title">Title <span class="paste-optional">(optional)</span></label>
                <input type="text" name="title" id="paste_title" maxlength="200" value="<?= sanitize_data($formTitle) ?>">
            </div>
            <div class="form-group">
                <label for="paste_content">Text</label>
                <textarea name="content" id="paste_content" rows="16" required spellcheck="false"><?= htmlspecialchars($formContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                <p class="settings-hint" id="paste_size" data-max="<?= (int) $maxBytes ?>">Limit <?= sanitize_data(format_filesize($maxBytes)) ?>.</p>
            </div>
            <div class="upload-options-grid">
                <div class="upload-option">
                    <label for="duration">Delete after</label>
                    <select name="duration" id="duration">
                        <?php foreach ($expiryOffsets as $key => $offset): ?>
                            <option value="<?= sanitize_data($key) ?>"<?= $key === $formDuration ? ' selected' : '' ?>><?= sanitize_data(ucwords(str_replace('_', ' ', $key))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="upload-option upload-option-checkbox">
                    <label class="tos-checkbox">
                        <input type="checkbox" name="burn_after_read" id="burn_after_read"<?= $formBurn ? ' checked' : '' ?>>
                        Delete after the first view
                    </label>
                    <small>The first person who opens the link sees it once. Then it is gone.</small>
                </div>
            </div>
            <div class="upload-submit-wrap">
                <button type="submit" class="btn btn-primary">Create link</button>
            </div>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var area = document.getElementById('paste_content');
            var hint = document.getElementById('paste_size');
            if (!area || !hint) return;
            var max = parseInt(hint.getAttribute('data-max') || '0', 10);
            var base = hint.textContent;
            function refresh() {
                var bytes = new TextEncoder().encode(area.value).length;
                hint.textContent = bytes.toLocaleString() + ' bytes. ' + base;
                hint.classList.toggle('paste-limit-over', max > 0 && bytes > max);
            }
            area.addEventListener('input', refresh);
            refresh();
        });
        </script>
    <?php endif; ?>
    <?php datadock_echo_owned_pastes($pdo, $readOnly, 'paste.php', $pastesEnabled); ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(function (el) {
        var utc = el.dataset.utc;
        if (!utc) return;
        var local = new Date(utc.replace(' ', 'T') + 'Z');
        if (isNaN(local.getTime())) return;
        el.textContent = local.toLocaleString();
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
