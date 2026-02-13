<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

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

// page settings
$pageTitle            = "Upload File";
$appMaxFileSize       = return_bytes($settings['max_file_size'] ?? '0');
$forbiddenExtensions  = [
    'php','php3','php4','php5','phtml','phar',
    'exe','sh','bat','cmd','js','pl','py','cgi',
    'asp','aspx','jsp','vbs','wsf','dll'
];
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

    // check PHP’s post_max_size / upload_max_filesize
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
             WHERE guest_id = ?
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

    // enforce user quotas
    if ($currentUserId && ($userQuotaByCountEnabled || $userQuotaByStorageEnabled)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS file_count,
                   COALESCE(SUM(filesize), 0) AS total_size
              FROM files
             WHERE user_id = ?
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

        // forbidden extension?
        if (in_array($extension, $forbiddenExtensions, true)) {
            $_SESSION['flash_error'][] = "❌ {$originalName} has a forbidden file type.";
            continue;
        }

        // application size check
        if ($appMaxFileSize > 0 && $fileSize > $appMaxFileSize) {
            $_SESSION['flash_error'][] = "❌ {$originalName} is too large. Max is " . format_filesize($appMaxFileSize) . ".";
            continue;
        }

        // move file
        $mimeType      = mime_content_type($tmpPath);
        $uniqueName    = uniqid('', true) . ".$extension";
        $destPath      = __DIR__ . '/uploads/' . $uniqueName;
        move_uploaded_file($tmpPath, $destPath);

        // generate thumbnail if image (when enabled)
        $thumbnailName = null;
        if ($thumbnailsEnabled && str_starts_with($mimeType, 'image/')) {
            $thumbnailName = 'thumb_' . $uniqueName . '.jpg';
            $thumbPath     = __DIR__ . '/thumbnails/' . $thumbnailName;
            if ($img = @imagecreatefromstring(file_get_contents($destPath))) {
                $thumb = imagescale($img, 100);
                imagejpeg($thumb, $thumbPath);
                imagedestroy($img);
                imagedestroy($thumb);
            } else {
                $thumbnailName = null;
            }
        }

        // timestamps
        $nowUTC         = new DateTime('now', new DateTimeZone('UTC'));
        $uploadTs       = $nowUTC->format('Y-m-d H:i:s');
        $expiryTs       = null;
        $chosenDuration = $_POST['duration'] ?? 'never';
        if ($autoDeleteDurations[$chosenDuration] !== null) {
            $expiryTs = (clone $nowUTC)
                        ->modify($autoDeleteDurations[$chosenDuration])
                        ->format('Y-m-d H:i:s');
        }

        // insert DB record
        $ins = $pdo->prepare("
            INSERT INTO files
              (user_id, guest_id, filename, original_name,
               filetype, filesize, thumbnail_path,
               upload_date, expiry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $currentUserId,
            $isGuest ? $guestId : null,
            $uniqueName,
            $originalName,
            $mimeType,
            $fileSize,
            $thumbnailName,
            $uploadTs,
            $expiryTs,
        ]);
    }

    // on success
    if (empty($_SESSION['flash_error'])) {
        $_SESSION['flash_success'][] = "✅ File(s) uploaded successfully.";
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
?>

<div class="page-section">
  <h2 class="page-title">Upload Files</h2>

  <?php if (! $formDisabled): ?>
    <form method="post" enctype="multipart/form-data" id="uploadForm">
      <!-- application‐level max -->
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= $appMaxFileSize ?>">
      <label for="upload">Select files</label>
      <div id="dropZone" class="drop-zone">
        Drag & Drop files here or click to browse
      </div>
      <input type="file" name="upload[]" id="upload" multiple hidden required>

      <?php if ($appMaxFileSize > 0): ?>
        <small id="maxSizeNote">
          Maximum allowed file size: <?= format_filesize($appMaxFileSize) ?>
        </small>
      <?php else: ?>
        <small>No file size limit is currently set.</small>
      <?php endif; ?>

      <br>
      <small>
        <strong>Forbidden file types:</strong>
        <?= implode(', ', $forbiddenExtensions) ?>
      </small>

      <div id="preview"></div>
      <div id="uploadResult" style="display:none; margin:1rem 0;"></div>

      <label for="duration">Auto-delete after</label>
      <select name="duration" id="duration">
        <?php foreach ($autoDeleteDurations as $key => $offset): ?>
          <option value="<?= sanitize_data($key) ?>"<?= $key === $defaultFileExpiry ? ' selected' : '' ?>>
            <?= ucwords(str_replace('_', ' ', $key)) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div id="progressContainer" style="display:none; margin:10px 0;">
        <progress id="uploadProgress" max="100" value="0" style="width:100%;"></progress>
        <span id="progressText">0%</span>
      </div>

      <button type="submit">Upload</button>
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
          (maxSize > 0 && file.size > maxSize)
        ) {
          continue;
        }
        const container = document.createElement("div");
        container.style.marginTop = "10px";
        container.textContent = file.name;
        if (file.type.startsWith("image/")) {
          const img = document.createElement("img");
          img.src = URL.createObjectURL(file);
          img.style.height = "200px";
          img.style.marginRight = "10px";
          container.prepend(img);
        }
        preview.appendChild(container);
      }
    }

    uploadForm.addEventListener("submit", function(event) {
      event.preventDefault();
      uploadButton.disabled    = true;
      uploadButton.textContent = "Uploading…";
      progressContainer.style.display = "block";

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
