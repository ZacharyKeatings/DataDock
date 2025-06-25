<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$guestAllowed = $settings['guest_uploads']['enabled'] ?? false;
$guestMaxFiles = $settings['guest_uploads']['max_files'] ?? 0;
$guestMaxStorage = $settings['guest_uploads']['max_storage'] ?? 0;

$userFileLimitEnabled = $settings['user_quota']['file_limit_enabled'] ?? false;
$userStorageLimitEnabled = $settings['user_quota']['storage_limit_enabled'] ?? false;
$userMaxFiles = $settings['user_quota']['max_files'] ?? 0;
$userMaxStorage = $settings['user_quota']['max_storage'] ?? 0;

$userId = $_SESSION['user_id'] ?? null;
$isGuest = !$userId && $guestAllowed;

if ($isGuest) {
    if (empty($_COOKIE['guest_id'])) {
        $guestId = bin2hex(random_bytes(16));
        setcookie('guest_id', $guestId, time() + (86400 * 30), "/"); // 30-day cookie
    } else {
        $guestId = $_COOKIE['guest_id'];
    }
}

$pageTitle = "Upload File";
$maxSize = (int) ($settings['max_file_size'] ?? 0);

$forbiddenExtensions = [
    'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
    'exe', 'sh', 'bat', 'cmd', 'js', 'pl', 'py', 'cgi',
    'asp', 'aspx', 'jsp', 'vbs', 'wsf', 'dll'
];

$durations = [
    '1_minute'   => '+1 minute',
    '30_minutes' => '+30 minutes',
    '1_hour'     => '+1 hour',
    '6_hours'    => '+6 hours',
    '1_day'      => '+1 day',
    '1_week'     => '+1 week',
    '1_month'    => '+1 month',
    '1_year'     => '+1 year',
    'never'      => null,
];

$formDisabled = false;
$guestError = '';

if (!$userId && !$guestAllowed) {
    $formDisabled = true;
    $guestError = "Guest uploads are currently disabled.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload']) && !$formDisabled) {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
       && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isGuest) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS file_count, COALESCE(SUM(filesize), 0) AS total_size FROM files WHERE guest_id = ?");
        $stmt->execute([$guestId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($guestMaxFiles > 0 && $stats['file_count'] >= $guestMaxFiles) {
            $_SESSION['flash_error'][] = "❌ You have reached the guest upload limit of $guestMaxFiles files.";
        }
        if ($guestMaxStorage > 0 && $stats['total_size'] >= $guestMaxStorage) {
            $_SESSION['flash_error'][] = "❌ You have reached the guest storage limit of " . format_filesize($guestMaxStorage) . ".";
        }
    }

    if ($userId && ($userFileLimitEnabled || $userStorageLimitEnabled)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS file_count, COALESCE(SUM(filesize), 0) AS total_size FROM files WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userFileLimitEnabled && $userStats['file_count'] >= $userMaxFiles) {
            $_SESSION['flash_error'][] = "❌ You have reached your file upload limit of $userMaxFiles files.";
        }
        if ($userStorageLimitEnabled && $userStats['total_size'] >= $userMaxStorage) {
            $_SESSION['flash_error'][] = "❌ You have reached your total storage limit of " . format_filesize($userMaxStorage) . ".";
        }
    }

    if (empty($errors)) {
        foreach ($_FILES['upload']['tmp_name'] as $index => $tmpName) {
            $file = [
                'tmp_name' => $tmpName,
                'name' => $_FILES['upload']['name'][$index],
                'type' => $_FILES['upload']['type'][$index],
                'size' => $_FILES['upload']['size'][$index],
                'error' => $_FILES['upload']['error'][$index]
            ];

            $durationKey = $_POST['duration'] ?? 'never';

            if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (in_array($ext, $forbiddenExtensions)) {
                    $_SESSION['flash_error'][] = "❌ {$file['name']} has a forbidden file type.";
                    continue;
                }

                if ($file['size'] > $maxSize) {
                    $_SESSION['flash_error'][] = "❌ {$file['name']} is too large. Max size is " . format_filesize($maxSize);
                    continue;
                }

                $originalName = $file['name'];
                $filetype = mime_content_type($file['tmp_name']);
                $filesize = $file['size'];
                $filename = uniqid() . '.' . $ext;
                $destination = __DIR__ . '/uploads/' . $filename;

                move_uploaded_file($file['tmp_name'], $destination);

                $thumbnail = null;
                if (str_starts_with($filetype, 'image/')) {
                    $thumbnail = 'thumb_' . $filename . '.jpg';
                    $thumbPath = __DIR__ . '/thumbnails/' . $thumbnail;

                    $image = @imagecreatefromstring(file_get_contents($destination));
                    if ($image) {
                        $thumb = imagescale($image, 100);
                        imagejpeg($thumb, $thumbPath);
                        imagedestroy($image);
                        imagedestroy($thumb);
                    } else {
                        $thumbnail = null;
                    }
                }

                $uploadDate = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                $expiryDate = null;
                if ($durations[$durationKey] !== null) {
                    $expiryDate = (new DateTime('now', new DateTimeZone('UTC')))
                        ->modify($durations[$durationKey])
                        ->format('Y-m-d H:i:s');
                }

                $stmt = $pdo->prepare("INSERT INTO files 
                    (user_id, guest_id, filename, original_name, filetype, filesize, thumbnail_path, upload_date, expiry_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $isGuest ? $guestId : null,
                    $filename,
                    $originalName,
                    $filetype,
                    $filesize,
                    $thumbnail,
                    $uploadDate,
                    $expiryDate
                ]);
            } else {
                $_SESSION['flash_error'][] = "❌ Failed to upload {$file['name']}.";
            }
        }
    }

    if (empty($_SESSION['flash_error'])) {
        $_SESSION['flash_success'][] = "✅ File(s) uploaded successfully.";
    }

    if (! $isAjax) {
        header("Location: upload.php");
        exit;
    } else {
        // on AJAX we just return a 200 and let JS reload the page
        http_response_code(200);
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div id="uploadError" class="warning" style="display:none;"></div>
<div class="page-section">
    <h2 class="page-title">Upload Files</h2>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $e): ?>
                <div>• <?= sanitize_data($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($formDisabled): ?>
        <div class="error"><?= htmlspecialchars($guestError) ?></div>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <label for="upload">Select files</label>
        <div id="dropZone" class="drop-zone">Drag & Drop files here or click to browse</div>
        <input type="file" name="upload[]" id="upload" multiple hidden required>

        <?php if ($maxSize > 0): ?>
            <small id="maxSizeNote">Maximum allowed file size: <?= format_filesize($maxSize) ?></small>
        <?php else: ?>
            <small>No file size limit is currently set.</small>
        <?php endif; ?>

        <br /><small><strong>Forbidden file types:</strong> <?= implode(', ', $forbiddenExtensions) ?></small>

        <div id="preview"></div>

        <label for="duration">Auto-delete after</label>
        <select name="duration" id="duration">
            <?php foreach ($durations as $key => $val): ?>
                <option value="<?= sanitize_data($key) ?>"><?= ucwords(str_replace('_', ' ', $key)) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- progress bar -->
        <div id="progressContainer" style="display:none; margin:10px 0;">
            <progress id="uploadProgress" max="100" value="0" style="width:100%;"></progress>
            <span id="progressText">0%</span>
        </div>

        <button type="submit">Upload</button>
    </form>
    <?php endif; ?>

<script>
    const dropZone = document.getElementById("dropZone");
    const fileInput = document.getElementById("upload");
    const preview = document.getElementById("preview");
    const maxSize = <?= (int) $maxSize ?>;
    const forbiddenExtensions = <?= json_encode($forbiddenExtensions) ?>;

    dropZone.addEventListener("click", () => fileInput.click());

    dropZone.addEventListener("dragover", e => {
        e.preventDefault();
        dropZone.classList.add("dragover");
    });

    dropZone.addEventListener("dragleave", () => {
        dropZone.classList.remove("dragover");
    });

    dropZone.addEventListener("drop", e => {
        e.preventDefault();
        dropZone.classList.remove("dragover");
        fileInput.files = e.dataTransfer.files;
        updatePreview();
    });

    fileInput.addEventListener("change", updatePreview);

    function getExtension(filename) {
        return filename.split('.').pop().toLowerCase();
    }

    function updatePreview() {
        preview.innerHTML = "";
        const files = fileInput.files;
        let errorMessages = [];

        for (const file of files) {
            const ext = getExtension(file.name);
            if (forbiddenExtensions.includes(ext)) {
                errorMessages.push(`${file.name} has a forbidden extension.`);
                continue;
            }

            if (file.size > maxSize) {
                errorMessages.push(`${file.name} is too large. Max allowed is ${(maxSize / 1048576).toFixed(2)} MB.`);
                continue;
            }

            const div = document.createElement("div");
            div.style.marginTop = "10px";
            div.textContent = file.name;

            if (file.type.startsWith("image/")) {
                const img = document.createElement("img");
                img.src = URL.createObjectURL(file);
                img.style.height = "200px";
                img.style.marginRight = "10px";
                div.prepend(img);
            }

            preview.appendChild(div);
        }

        const errorBox = document.getElementById("uploadError");
        if (errorMessages.length > 0) {
            errorBox.classList.add("error");
            errorBox.style.display = "block";
            errorBox.innerHTML = errorMessages.map(msg => `<div>• ${msg}</div>`).join('');
        } else {
            errorBox.style.display = "none";
        }
    }

    const uploadForm = document.getElementById("uploadForm");
    const progressContainer = document.getElementById("progressContainer");
    const progressBar       = document.getElementById("uploadProgress");
    const progressText      = document.getElementById("progressText");
    const uploadButton      = uploadForm.querySelector('button[type="submit"]');

    uploadForm.addEventListener("submit", function(event) {
        event.preventDefault();              // stop normal navigation

        uploadButton.disabled    = true;
        uploadButton.textContent = "Uploading…";

        const formData = new FormData(this); // grab all inputs

        const xhr = new XMLHttpRequest();
        xhr.open("POST", this.action, true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

        // show progress container
        progressContainer.style.display = "block";

        // update bar on upload progress
        xhr.upload.addEventListener("progress", function(evt) {
        if (!evt.lengthComputable) return;
        const percent = Math.round((evt.loaded / evt.total) * 100);
        progressBar.value = percent;
        progressText.textContent = percent + "%";
        });

        // when done, reload page so session‐flash messages render
        xhr.addEventListener("load", function() {
        window.location = "upload.php";
        });

        // send it
        xhr.send(formData);
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>