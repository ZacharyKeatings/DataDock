<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/personal.php';
require_once __DIR__ . '/includes/notices.php';

$token = (string) ($_GET['t'] ?? '');
$userId = datadock_user_id_for_notice_feed($pdo, $token);
if ($userId < 1) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "This notice feed is not available.\n";
    exit;
}

$notices = datadock_list_notices($pdo, $userId, 50, 0);
$self = function_exists('datadock_public_url') ? datadock_public_url('notices_feed.php?t=' . $token) : ('notices_feed.php?t=' . $token);
$feedId = 'urn:datadock:notices:' . hash('sha256', $token);
$xml = datadock_notices_atom(get_site_name(), $self, $feedId, $notices);
header('Content-Type: application/atom+xml; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
echo $xml;
exit;
