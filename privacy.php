<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

$pageTitle = 'Privacy';
require_once __DIR__ . '/includes/header.php';

$enabled = !empty($settings['privacy_enabled']);
$text = trim((string) ($settings['privacy_text'] ?? ''));
?>

<div class="page-section narrow-form">
    <h2 class="page-title">Privacy</h2>
    <?php if ($enabled && $text !== ''): ?>
        <div class="privacy-text"><?= nl2br(sanitize_data($text)) ?></div>
    <?php else: ?>
        <div class="empty-state">
            <p>No privacy notice has been published.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
