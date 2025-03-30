<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$pageTitle = "Upload File";
$maxSize = isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 0; // 0 means no limit
$errors = [];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
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
            if ($file['size'] > $maxSize) {
                $errors[] = "{$file['name']} is too large. Max size is " . format_filesize($maxSize);
                continue;
            }

            $originalName = $file['name'];
            $filetype = mime_content_type($file['tmp_name']);
            $filesize = $file['size'];
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
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
                (user_id, filename, original_name, filetype, filesize, thumbnail_path, upload_date, expiry_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $filename,
                $originalName,
                $filetype,
                $filesize,
                $thumbnail,
                $uploadDate,
                $expiryDate
            ]);
        } else {
            $errors[] = "Failed to upload {$file['name']}.";
        }
    }

    if (empty($errors)) {
        header("Location: dashboard.php?uploaded=1");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div id="uploadError" class="warning" style="display:none;"></div>
<h2>Upload Files</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $e): ?>
            <div>• <?= sanitize_data($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="uploadForm">
    <label for="upload">Select files</label>
    <div id="dropZone" class="drop-zone">Drag & Drop files here or click to browse</div>
    <input type="file" name="upload[]" id="upload" multiple hidden required>

    <?php if ($maxSize > 0): ?>
        <small id="maxSizeNote">Maximum allowed file size: <?= format_filesize($maxSize) ?></small>
    <?php else: ?>
        <small>No file size limit is currently set.</small>
    <?php endif; ?>

    <div id="preview"></div>

    <label for="duration">Auto-delete after</label>
    <select name="duration" id="duration">
        <?php foreach ($durations as $key => $val): ?>
            <option value="<?= sanitize_data($key) ?>"><?= ucwords(str_replace('_', ' ', $key)) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Upload</button>
</form>

<script>
const dropZone = document.getElementById("dropZone");
const fileInput = document.getElementById("upload");
const preview = document.getElementById("preview");
const maxSize = <?= (int) $maxSize ?>;

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

function updatePreview() {
    preview.innerHTML = "";
    const files = fileInput.files;
    let errorShown = false;

    for (const file of files) {
        if (file.size > maxSize && !errorShown) {
            document.getElementById("uploadError").style.display = "block";
            document.getElementById("uploadError").textContent = file.name + " is too large. Max allowed is " + (maxSize / 1048576).toFixed(2) + " MB.";
            errorShown = true;
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

    if (!errorShown) {
        document.getElementById("uploadError").style.display = "none";
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>