<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Upload File";

$errors = [];

// Duration map
$durations = [
    '1_minute'   => '+1 minute',
    '30_minutes' => '+30 minutes',
    '1_hour'     => '+1 hour',
    '6_hours'    => '+6 hours',
    '1_day'      => '+1 day',
    '1_week'     => '+1 week',
    '1_month'    => '+1 month',
    '1_year'     => '+1 year',
    'forever'    => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
    $file = $_FILES['upload'];
    $durationKey = $_POST['duration'] ?? 'forever';

    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $originalName = $file['name'];
        $filetype = mime_content_type($file['tmp_name']);
        $filesize = $file['size'];
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;

        // Save uploaded file
        $destination = __DIR__ . '/uploads/' . $filename;
        move_uploaded_file($file['tmp_name'], $destination);

        // Generate thumbnail if image
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

        // Set expiry
        $expiryDate = null;
        if ($durations[$durationKey] !== null) {
            $expiryDate = (new DateTime())->modify($durations[$durationKey])->format('Y-m-d H:i:s');
        }

        // Insert into DB
        $stmt = $pdo->prepare("INSERT INTO files 
            (user_id, filename, original_name, filetype, filesize, thumbnail_path, expiry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $filename,
            $originalName,
            $filetype,
            $filesize,
            $thumbnail,
            $expiryDate
        ]);

        // Redirect to dashboard with flag
        header("Location: dashboard.php?uploaded=1");
        exit;
    } else {
        $errors[] = "Failed to upload file.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2>Upload a File</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $e): ?>
            <div>• <?= sanitize_data($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <label for="upload">Select a file</label>
    <input type="file" name="upload" id="upload" required>

    <label for="duration">Auto-delete after</label>
    <select name="duration" id="duration">
        <?php foreach ($durations as $key => $val): ?>
            <option value="<?= sanitize_data($key) ?>"><?= ucwords(str_replace('_', ' ', $key)) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Upload</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
