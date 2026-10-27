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
require_once __DIR__ . '/includes/ingest_file.php';
require_once __DIR__ . '/includes/quotas.php';

// feature flags & limits
$guestUploadsEnabled       = $settings['guest_uploads']['enabled']         ?? false;
$guestMaxFiles             = $settings['guest_uploads']['max_files']        ?? 0;
$guestMaxStorage           = $settings['guest_uploads']['max_storage']      ?? 0;
$userQuotaByCountEnabled   = $settings['user_limits']['max_files_enabled'] ?? false;
$userQuotaByStorageEnabled = $settings['user_limits']['max_storage_enabled'] ?? false;
$userMaxFiles              = $settings['user_limits']['max_files']          ?? 0;
$userMaxStorage            = $settings['user_limits']['max_storage']        ?? 0;

// determine current user vs guest
$currentUserId = $_SESSION['user_id'] ?? null;
$isGuest       = !$currentUserId && $guestUploadsEnabled;

// assign or read guest_id cookie
if ($isGuest) {
    if (empty($_COOKIE['guest_id'])) {
        $newGuestId = bin2hex(random_bytes(16));
        setcookie('guest_id', $newGuestId, time() + 86400 * 30, "/");
        $guestId = $newGuestId;
    } else {
        $guestId = $_COOKIE['guest_id'];
    }
}

if (datadock_read_only_enabled($settings)) {
    $_SESSION['flash_error'][] = '❌ Uploads are disabled (read-only / archive mode).';
    header('Location: ' . ($currentUserId ? 'dashboard.php' : 'index.php'));
    exit;
}

// page settings
$pageTitle            = "Upload File";
$appMaxFileSize       = return_bytes($settings['max_file_size'] ?? '0');
$forbiddenExtensions = get_forbidden_upload_extensions();
$autoDeleteDurations  = [
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
$defaultFileExpiry = $settings['default_file_expiry'] ?? 'never';
if (!isset($autoDeleteDurations[$defaultFileExpiry])) $defaultFileExpiry = 'never';
$thumbnailsEnabled = $settings['thumbnails_enabled'] ?? true;
$rewriteFileExtension = !empty($settings['rewrite_file_extension']);
$uploadQuarantineEnabled = !empty($settings['upload_quarantine_enabled']);

$tosEnabled = !empty($settings['tos_enabled']);
$tosText    = trim($settings['tos_text'] ?? '');
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

// Accepted file types by category (for expandable help on upload page)
$allowedFileTypesByCategory = [
    'Images'        => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'ico', 'svg', 'heic', 'avif'],
    'Documents'    => ['pdf', 'doc', 'docx', 'rtf', 'odt', 'txt', 'epub'],
    'Spreadsheets' => ['xls', 'xlsx', 'ods', 'csv'],
    'Presentations'=> ['ppt', 'pptx', 'odp'],
    'Archives'     => ['zip', 'rar', '7z', 'tar', 'gz'],
    'Audio'        => ['mp3', 'wav', 'ogg', 'flac', 'm4a'],
    'Video'        => ['mp4', 'mov', 'webm', 'mkv', 'avi'],
    'Data & code'  => ['json', 'xml', 'html', 'htm'],
];

// disable form if guest uploads off and no user
$formDisabled = false;
if (!$currentUserId && !$guestUploadsEnabled) {
    $formDisabled = true;
    $_SESSION['flash_error'][] = "❌ Guest uploads are currently disabled.";
}

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // detect AJAX
    $isAjax = (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    );

    //--- application‐level size pre-check
    // if entire payload > app limit, PHP will flag FORM_SIZE
    if (
        ( ! isset($_FILES['upload']) || empty($_FILES['upload']['name'][0]) )
        && ! empty($_SERVER['CONTENT_LENGTH'])
        && $_SERVER['CONTENT_LENGTH'] > $appMaxFileSize
    ) {
        $msg = "❌ Upload failed: exceeds application max of " . format_filesize($appMaxFileSize) . ".";
        $_SESSION['flash_error'][] = $msg;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['errors'=>[$msg],'success'=>[]]);
            exit;
        }
        header("Location: upload.php");
        exit;
    }

    // if form disabled
    if ($formDisabled) {
        header("Location: upload.php");
        exit;
    }

    if ($tosEnabled && !isset($_POST['tos_accepted'])) {
        $_SESSION['flash_error'][] = "❌ You must accept the Terms of Service to upload files.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['errors' => $_SESSION['flash_error'] ?? [], 'success' => []]);
            exit;
        }
        header("Location: upload.php");
        exit;
    }

    // rate limiting: per IP and per user
    $clientIp = get_client_ip();
    if (!check_upload_rate_limit($pdo, $currentUserId, $clientIp, $settings)) {
        $_SESSION['flash_error'][] = "❌ Upload rate limit exceeded. Please try again in a few minutes.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['errors' => $_SESSION['flash_error'] ?? [], 'success' => []]);
            exit;
        }
        header("Location: upload.php");
        exit;
    }

    // check PHP's post_max_size / upload_max_filesize
    $postMaxBytes = return_bytes(ini_get('post_max_size'));
    if (
        ( ! isset($_FILES['upload']) || empty($_FILES['upload']['name'][0]) )
        && ! empty($_SERVER['CONTENT_LENGTH'])
        && $_SERVER['CONTENT_LENGTH'] > $postMaxBytes
    ) {
        $msg = "❌ Upload failed: exceeds server limit of " . format_filesize($postMaxBytes) . ".";
        $_SESSION['flash_error'][] = $msg;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['errors'=>[$msg],'success'=>[]]);
            exit;
        }
        header("Location: upload.php");
        exit;
    }

    // enforce guest quotas
    if ($isGuest) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS file_count,
                   COALESCE(SUM(filesize), 0) AS total_size
              FROM files
             WHERE guest_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$guestId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($guestMaxFiles > 0 && $stats['file_count'] >= $guestMaxFiles) {
            $_SESSION['flash_error'][] = "❌ You have reached the guest upload limit of {$guestMaxFiles} files.";
        }
        if ($guestMaxStorage > 0 && $stats['total_size'] >= $guestMaxStorage) {
            $_SESSION['flash_error'][] = "❌ You have reached the guest storage limit of " . format_filesize($guestMaxStorage) . ".";
        }
    }

    if ($currentUserId) {
        $quotaLimits = datadock_user_quota_limits($pdo, (int) $currentUserId, $settings);
        $userQuotaByCountEnabled = $quotaLimits['count_enabled'];
        $userMaxFiles = $quotaLimits['max_files'];
        $userQuotaByStorageEnabled = $quotaLimits['bytes_enabled'];
        $userMaxStorage = $quotaLimits['max_bytes'];
    }

    // enforce user quotas
    if ($currentUserId && ($userQuotaByCountEnabled || $userQuotaByStorageEnabled)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS file_count,
                   COALESCE(SUM(filesize), 0) AS total_size
              FROM files
             WHERE user_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$currentUserId]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userQuotaByCountEnabled && $userStats['file_count'] >= $userMaxFiles) {
            $_SESSION['flash_error'][] = "❌ You have reached your file upload limit of {$userMaxFiles} files.";
        }
        if ($userQuotaByStorageEnabled && $userStats['total_size'] >= $userMaxStorage) {
            $_SESSION['flash_error'][] = "❌ You have reached your total storage limit of " . format_filesize($userMaxStorage) . ".";
        }
    }

    // process each uploaded file
    $storedCount = 0;
    foreach ($_FILES['upload']['name'] as $i => $originalName) {
        $error       = $_FILES['upload']['error'][$i];
        $tmpPath     = $_FILES['upload']['tmp_name'][$i];
        $fileSize    = $_FILES['upload']['size'][$i];
        $extension   = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($error !== UPLOAD_ERR_OK) {
            switch ($error) {
                case UPLOAD_ERR_INI_SIZE:
                    $phpMax = return_bytes(ini_get('upload_max_filesize'));
                    $msg = "❌ {$originalName} exceeds server max of " . format_filesize($phpMax) . ".";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $msg = "❌ {$originalName} exceeds application max of " . format_filesize($appMaxFileSize) . ".";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $msg = "❌ {$originalName} was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $msg = "❌ No file selected for {$originalName}.";
                    break;
                default:
                    $msg = "❌ Failed to upload {$originalName}. Error code: {$error}.";
            }
            $_SESSION['flash_error'][] = $msg;
            continue;
        }

        $folderId = null;
        if ($currentUserId && !empty($_POST['folder_id']) && is_numeric($_POST['folder_id'])) {
            $folderId = (int) $_POST['folder_id'];
        }
        $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
        $expiryTs = null;
        $chosenDuration = $_POST['duration'] ?? 'never';
        if (isset($autoDeleteDurations[$chosenDuration]) && $autoDeleteDurations[$chosenDuration] !== null) {
            $expiryTs = (clone $nowUTC)->modify($autoDeleteDurations[$chosenDuration])->format('Y-m-d H:i:s');
        }
        $stored = datadock_ingest_local_file($pdo, $settings, [
            'source_path' => $tmpPath,
            'original_name' => $originalName,
            'file_size' => $fileSize,
            'user_id' => $currentUserId ? (int) $currentUserId : 0,
            'guest_id' => $isGuest ? ($guestId ?? null) : null,
            'folder_id' => $folderId,
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'expiry_ts' => $expiryTs,
            'relocate' => 'upload',
        ]);
        if (!$stored['ok']) {
            $_SESSION['flash_error'][] = $stored['error'];
            continue;
        }
        $newFileId = (int) $stored['file_id'];
        $storedCount++;
        if (($stored['quarantine'] ?? '') === 'pending' && ($stored['scan'] ?? '') !== '') {
            $_SESSION['flash_success'][] = $originalName . ' was saved and is waiting for review.';
        }
        datadock_log_activity($pdo, 'upload', [
            'actor_user_id' => $currentUserId,
            'actor_guest_id' => $isGuest ? ($guestId ?? null) : null,
            'file_id' => $newFileId,
            'detail' => [
                'name' => $originalName,
                'size' => $fileSize,
                'public' => isset($_POST['is_public']),
            ],
        ]);
    }

    // on success: record rate limit events (one per file actually inserted)
    if ($storedCount > 0 && empty($_SESSION['flash_error'])) {
        record_upload_rate_limit($pdo, $clientIp, $currentUserId, $storedCount);
        $_SESSION['flash_success'][] = "✅ File(s) uploaded successfully.";
        if ($isGuest && !empty($guestId)) {
            require_once __DIR__ . '/includes/guest_claim.php';
            $claimToken = datadock_issue_guest_claim($pdo, (string) $guestId);
            if ($claimToken) {
                $_SESSION['flash_success'][] = 'Claim code: ' . $claimToken . '. Enter it when you register or log in to attach these uploads to an account.';
            }
        }
    }

    // AJAX vs normal
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'errors'  => $_SESSION['flash_error'] ?? [],
            'success' => $_SESSION['flash_success'] ?? []
        ]);
        exit;
    }

    header("Location: upload.php");
    exit;
}

// render page
require_once __DIR__ . '/includes/header.php';

$uploadFolderId = '';
if (!empty($_SESSION['user_id']) && isset($_GET['folder']) && is_numeric($_GET['folder']) && (int) $_GET['folder'] > 0) {
    $uploadFolderId = (string) (int) $_GET['folder'];
}
$filesBackHref = $uploadFolderId !== '' ? 'dashboard.php?folder=' . $uploadFolderId : 'dashboard.php';
?>

<div class="page-section upload-page">
  <div class="upload-hero">
    <div class="upload-hero-icon"><?= icon_svg('upload') ?></div>
    <h2 class="page-title">Upload Files</h2>
    <p class="upload-subtitle">Drag files into the zone below or click to select</p>
    <?php if (!empty($currentUserId)): ?>
    <p class="upload-subtitle"><a href="<?= sanitize_data($filesBackHref) ?>" class="btn btn-quiet btn-small">Back to your files</a></p>
    <?php endif; ?>
  </div>

  <?php if (! $formDisabled): ?>
    <form method="post" enctype="multipart/form-data" id="uploadForm" class="upload-form">
      <?= datadock_csrf_field() ?>
      <?php if ($tosEnabled && !empty($tosText)): ?>
      <div class="tos-block upload-options-block">
        <div class="tos-text"><?= nl2br(sanitize_data($tosText)) ?></div>
        <label class="tos-checkbox">
          <input type="checkbox" name="tos_accepted" id="tos_accepted" required>
          I agree to the Terms of Service / Acceptable Use
        </label>
      </div>
      <?php endif; ?>
      <!-- application‐level max -->
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= $appMaxFileSize ?>">
      <?php if ($uploadFolderId !== ''): ?>
      <input type="hidden" name="folder_id" value="<?= sanitize_data($uploadFolderId) ?>">
      <?php endif; ?>

      <div id="dropZone" class="drop-zone" role="button" tabindex="0" aria-label="Drop zone for file uploads">
        <span class="drop-zone-icon"><?= icon_svg('folder') ?></span>
        <span class="drop-zone-text">Drop files here or click to browse</span>
        <span class="drop-zone-hint">
          <?php if ($appMaxFileSize > 0): ?>
            Max <?= format_filesize($appMaxFileSize) ?> per file
          <?php else: ?>
            No size limit
          <?php endif; ?>
        </span>
      </div>
      <input type="file" name="upload[]" id="upload" multiple hidden required>

      <details class="upload-accepted-types">
        <summary class="upload-accepted-types-summary">Accepted file types</summary>
        <div class="upload-accepted-types-content">
          <p class="upload-accepted-types-intro">You can upload the following types (and other file types not listed below, except those in the forbidden list).</p>
          <div class="upload-accepted-types-grid">
            <?php foreach ($allowedFileTypesByCategory as $category => $exts): ?>
              <div class="upload-accepted-types-category">
                <strong><?= sanitize_data($category) ?></strong>
                <span class="upload-accepted-types-exts"><?= implode(', ', array_map('sanitize_data', $exts)) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="upload-accepted-types-note"><strong>Not allowed:</strong> <?= implode(', ', $forbiddenExtensions) ?> — including as any dotted segment in the name (e.g. <code>.php.jpg</code> is rejected).</p>
        </div>
      </details>

      <div id="preview" class="upload-preview"></div>
      <div id="uploadResult" class="upload-result"></div>

      <div class="upload-options-block">
        <h4 class="upload-options-title">Options</h4>
        <div class="upload-options-grid">
          <div class="upload-option">
            <label for="duration">Auto-delete after</label>
            <select name="duration" id="duration">
              <?php foreach ($autoDeleteDurations as $key => $offset): ?>
                <option value="<?= sanitize_data($key) ?>"<?= $key === $defaultFileExpiry ? ' selected' : '' ?>>
                  <?= ucwords(str_replace('_', ' ', $key)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($publicBrowsingEnabled): ?>
          <div class="upload-option upload-option-checkbox">
            <label class="tos-checkbox">
              <input type="checkbox" name="is_public" id="is_public">
              Make public
            </label>
            <small>Anyone can browse and download</small>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php
      $container_id = 'progressContainer';
      $progress_id  = 'uploadProgress';
      $text_id      = 'progressText';
      $value        = 0;
      $label        = '0%';
      $hidden       = true;
      require_once __DIR__ . '/includes/progress_bar.php';
      ?>

      <div class="form-group settings-row-checkbox">
        <label>
          <input type="checkbox" name="use_chunks" id="use_chunks" value="1">
          Send large files in chunks
        </label>
        <p class="form-hint">The server reassembles the file, then runs the same type, quota, and scan checks once.</p>
      </div>
      <div class="form-group settings-row-checkbox">
        <label>
          <input type="checkbox" name="use_chunks" id="use_chunks" value="1">
          Send large files in chunks
        </label>
        <p class="form-hint">The browser slices the file. The server reassembles it, then runs the usual type, quota, and scan checks once.</p>
      </div>
      <div class="upload-submit-wrap">
        <button type="submit" class="btn btn-primary btn-upload-submit">Upload</button>
      </div>
    </form>
  <?php endif; ?>

  <script>
    const dropZone          = document.getElementById("dropZone");
    const fileInput         = document.getElementById("upload");
    const preview           = document.getElementById("preview");
    const progressContainer = document.getElementById("progressContainer");
    const uploadProgress    = document.getElementById("uploadProgress");
    const progressText      = document.getElementById("progressText");
    const uploadForm        = document.getElementById("uploadForm");
    const uploadButton      = uploadForm.querySelector('button[type="submit"]');
    const maxSize           = <?= $appMaxFileSize ?>;
    const forbiddenExts     = <?= json_encode($forbiddenExtensions) ?>;

    function openFilePicker() {
      fileInput.click();
    }
    dropZone.addEventListener("click", openFilePicker);
    dropZone.addEventListener("keydown", e => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        openFilePicker();
      }
    });
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

    function basenameHasDangerousSegment(filename) {
      const base = filename.replace(/^.*[/\\]/, '').replace(/\.+$/g, '');
      if (!base.includes('.')) return false;
      const parts = base.split('.').map(s => s.toLowerCase());
      return parts.some(p => p !== '' && forbiddenExts.includes(p));
    }

    function showUploadResult(errors, success) {
      const resultDiv = document.getElementById("uploadResult");
      resultDiv.innerHTML = "";
      resultDiv.style.display = "block";
      if (errors && errors.length) {
        errors.forEach(msg => {
          const el = document.createElement("div");
          el.className = "flash error";
          el.textContent = msg;
          resultDiv.appendChild(el);
        });
      }
      if (success && success.length) {
        success.forEach(msg => {
          const el = document.createElement("div");
          el.className = "flash success";
          el.textContent = msg;
          resultDiv.appendChild(el);
        });
      }
    }

    function updatePreview() {
      preview.innerHTML = "";
      for (const file of fileInput.files) {
        if (
          forbiddenExts.includes(getExtension(file.name)) ||
          basenameHasDangerousSegment(file.name) ||
          (maxSize > 0 && file.size > maxSize)
        ) {
          continue;
        }
        const container = document.createElement("div");
        container.className = "preview-item";
        const nameSpan = document.createElement("span");
        nameSpan.textContent = file.name;
        nameSpan.title = file.name;
        if (file.type.startsWith("image/")) {
          const img = document.createElement("img");
          img.src = URL.createObjectURL(file);
          img.alt = file.name;
          container.appendChild(img);
          container.appendChild(nameSpan);
        } else {
          container.appendChild(nameSpan);
        }
        preview.appendChild(container);
      }
    }

    function csrfToken() {
      const field = uploadForm.querySelector('input[name="csrf_token"]');
      return field ? field.value : '';
    }

    async function uploadChunked() {
      const files = Array.from(fileInput.files);
      const errors = [];
      const success = [];
      const chunkSize = 1024 * 1024;
      let done = 0;
      const total = files.reduce((sum, file) => sum + file.size, 0) || 1;
      for (const file of files) {
        const start = new FormData();
        start.append('action', 'start');
        start.append('filename', file.name);
        start.append('size', String(file.size));
        start.append('csrf_token', csrfToken());
        const started = await fetch('upload_chunk.php', { method: 'POST', body: start, headers: { 'X-Requested-With': 'xmlhttprequest' } });
        const startJson = await started.json();
        if (!startJson.ok) {
          errors.push(startJson.error || ('Could not start ' + file.name));
          continue;
        }
        const count = Math.max(1, Math.ceil(file.size / chunkSize));
        let failed = false;
        for (let index = 0; index < count; index++) {
          const blob = file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize));
          const part = new FormData();
          part.append('action', 'chunk');
          part.append('upload_id', startJson.upload_id);
          part.append('index', String(index));
          part.append('chunk', blob, 'chunk');
          part.append('csrf_token', csrfToken());
          const sent = await fetch('upload_chunk.php', { method: 'POST', body: part });
          const partJson = await sent.json();
          if (!partJson.ok) {
            errors.push(partJson.error || ('Chunk failed for ' + file.name));
            failed = true;
            break;
          }
          done += blob.size;
          const percent = Math.round((done / total) * 100);
          uploadProgress.value = percent;
          progressText.textContent = percent + '%';
        }
        if (failed) continue;
        const finish = new FormData(uploadForm);
        finish.delete('upload');
        finish.set('action', 'finish');
        finish.set('upload_id', startJson.upload_id);
        finish.set('filename', file.name);
        const finished = await fetch('upload_chunk.php', { method: 'POST', body: finish });
        const finishJson = await finished.json();
        if (!finishJson.ok) {
          errors.push(finishJson.error || ('Could not finish ' + file.name));
        } else if (finishJson.message) {
          success.push(finishJson.message);
        }
      }
      showUploadResult(errors, success);
      if (errors.length === 0 && success.length > 0) {
        setTimeout(() => { window.location = 'upload.php'; }, 1500);
      } else {
        uploadButton.disabled = false;
        uploadButton.textContent = 'Upload';
      }
    }

    uploadForm.addEventListener("submit", function(event) {
      event.preventDefault();
      const bad = Array.from(fileInput.files).filter(f =>
        forbiddenExts.includes(getExtension(f.name)) ||
        basenameHasDangerousSegment(f.name) ||
        (maxSize > 0 && f.size > maxSize)
      );
      if (bad.length) {
        showUploadResult(["❌ Remove blocked or oversize files before uploading: " + bad.map(f => f.name).join(", ")], []);
        return;
      }
      uploadButton.disabled    = true;
      uploadButton.textContent = "Uploading…";
      progressContainer.style.display = "block";

      const chunked = document.getElementById('use_chunks');
      if (chunked && chunked.checked) {
        uploadChunked().catch(() => {
          showUploadResult(['❌ Upload failed: network error.'], []);
          uploadButton.disabled = false;
          uploadButton.textContent = 'Upload';
        });
        return;
      }

      const formData = new FormData(this);
      const xhr      = new XMLHttpRequest();
      xhr.open("POST", this.action, true);
      xhr.setRequestHeader("X-Requested-With", "xmlhttprequest");

      xhr.upload.addEventListener("progress", evt => {
        if (!evt.lengthComputable) return;
        const percent = Math.round((evt.loaded / evt.total) * 100);
        uploadProgress.value     = percent;
        progressText.textContent = percent + "%";
      });

      xhr.onerror = () => {
        showUploadResult(["❌ Upload failed: network error."], []);
        uploadButton.disabled = false;
        uploadButton.textContent = "Upload";
      };

      xhr.onload = () => {
        try {
          const resp = JSON.parse(xhr.responseText);
          const errors = resp.errors || [];
          const success = resp.success || [];
          showUploadResult(errors, success);

          if (errors.length === 0 && success.length > 0) {
            setTimeout(() => { window.location = "upload.php"; }, 1500);
          } else {
            uploadButton.disabled = false;
            uploadButton.textContent = "Upload";
          }
        } catch (e) {
          showUploadResult(["❌ Upload failed: invalid response."], []);
          uploadButton.disabled = false;
          uploadButton.textContent = "Upload";
        }
      };

      xhr.send(formData);
    });
  </script>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
