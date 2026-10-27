<?php
/**
 * Optional upload scanner. Off by default. No signatures ship with the app.
 */

function datadock_upload_scan_config(array $settings): array {
    $cfg = is_array($settings['upload_scan'] ?? null) ? $settings['upload_scan'] : [];
    $mode = strtolower(trim((string) ($cfg['mode'] ?? 'off')));
    if (!in_array($mode, ['off', 'command', 'clamd'], true)) {
        $mode = 'off';
    }
    return [
        'mode' => $mode,
        'command' => trim((string) ($cfg['command'] ?? '')),
        'socket' => trim((string) ($cfg['socket'] ?? '')),
    ];
}

/**
 * @return array{passed:bool,message:string}
 */
function datadock_upload_scan_file(string $path, array $settings): array {
    $cfg = datadock_upload_scan_config($settings);
    if ($cfg['mode'] === 'off') {
        return ['passed' => true, 'message' => ''];
    }
    if (!is_readable($path)) {
        return ['passed' => false, 'message' => 'Scan could not read the upload.'];
    }
    if ($cfg['mode'] === 'command') {
        return datadock_upload_scan_command($path, $cfg['command']);
    }
    return datadock_upload_scan_clamd($path, $cfg['socket']);
}

/**
 * @return array{passed:bool,message:string}
 */
function datadock_upload_scan_command(string $path, string $command): array {
    if ($command === '') {
        return ['passed' => false, 'message' => 'Upload scan command is not configured.'];
    }
    $parts = preg_split('/\s+/', $command) ?: [];
    $parts = array_values(array_filter($parts, static function ($p) {
        return $p !== '';
    }));
    if ($parts === []) {
        return ['passed' => false, 'message' => 'Upload scan command is not configured.'];
    }
    $parts[] = $path;
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($parts, $desc, $pipes);
    if (!is_resource($proc)) {
        return ['passed' => false, 'message' => 'Upload scanner could not be started.'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code === 0) {
        return ['passed' => true, 'message' => ''];
    }
    $detail = trim((string) $stdout . "\n" . (string) $stderr);
    $detail = preg_replace('/\s+/', ' ', $detail) ?? $detail;
    if (strlen($detail) > 180) {
        $detail = substr($detail, 0, 180);
    }
    return ['passed' => false, 'message' => $detail !== '' ? $detail : 'Upload scan failed.'];
}

/**
 * clamd INSTREAM. Socket is a unix path or host:port.
 *
 * @return array{passed:bool,message:string}
 */
function datadock_upload_scan_clamd(string $path, string $socket): array {
    if ($socket === '') {
        return ['passed' => false, 'message' => 'clamd socket is not configured.'];
    }
    $target = (strpos($socket, ':') !== false && strpos($socket, '/') === false)
        ? 'tcp://' . $socket
        : 'unix://' . $socket;
    $fp = @stream_socket_client($target, $errno, $errstr, 5);
    if (!is_resource($fp)) {
        return ['passed' => false, 'message' => 'Could not reach clamd.'];
    }
    stream_set_timeout($fp, 30);
    fwrite($fp, "nINSTREAM\n");
    $in = @fopen($path, 'rb');
    if (!is_resource($in)) {
        fclose($fp);
        return ['passed' => false, 'message' => 'Scan could not read the upload.'];
    }
    while (!feof($in)) {
        $chunk = fread($in, 8192);
        if ($chunk === false) {
            break;
        }
        if ($chunk === '') {
            continue;
        }
        fwrite($fp, pack('N', strlen($chunk)) . $chunk);
    }
    fclose($in);
    fwrite($fp, pack('N', 0));
    $reply = '';
    while (!feof($fp)) {
        $line = fgets($fp);
        if ($line === false) {
            break;
        }
        $reply .= $line;
        if (strpos($line, 'OK') !== false || strpos($line, 'FOUND') !== false || strpos($line, 'ERROR') !== false) {
            break;
        }
    }
    fclose($fp);
    $reply = trim($reply);
    if ($reply !== '' && preg_match('/:\s*OK\s*$/', $reply)) {
        return ['passed' => true, 'message' => ''];
    }
    return ['passed' => false, 'message' => $reply !== '' ? $reply : 'clamd scan failed.'];
}
