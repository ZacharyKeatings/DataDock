<h2 class="page-title">Storage partitions</h2>
<p class="page-description" style="max-width:42rem;">Each partition is a separate storage root (empty <code>root path</code> inherits the site <strong>Custom Storage Base Path</strong> from Site Settings). Every root has its own <code>uploads/</code> and <code>thumbnails/</code> subdirectories. Assign users to a partition in User Management so new uploads land on the correct disk.</p>

<div class="settings-card" style="margin-bottom:1.5rem;">
    <h3 class="settings-card-title">Add partition</h3>
    <div class="settings-card-body">
        <form method="post" action="admin.php" class="settings-form">
            <input type="hidden" name="add_storage_partition" value="1">
            <div class="settings-row">
                <label for="partition_name">Name</label>
                <input type="text" name="partition_name" id="partition_name" required maxlength="100" placeholder="e.g. Team A NAS">
            </div>
            <div class="settings-row">
                <label for="partition_root">Root path</label>
                <input type="text" name="partition_root" id="partition_root" maxlength="500" placeholder="Leave empty for site default, or e.g. /mnt/volume2/datadock">
            </div>
            <p class="settings-hint">Absolute path or relative to the project root (same rules as Site Settings → storage base path).</p>
            <button type="submit" class="btn btn-primary">Add partition</button>
        </form>
    </div>
</div>

<?php if (!empty($storagePartitions)): ?>
<div class="file-list">
    <div class="file-row-dashboard file-header">
        <div>Name</div>
        <div>Root path</div>
        <div>Files</div>
        <div>Users</div>
        <div>Default</div>
    </div>
    <?php foreach ($storagePartitions as $sp): ?>
        <div class="file-row-dashboard">
            <div><?= sanitize_data($sp['name']) ?> (id <?= (int) $sp['id'] ?>)</div>
            <div><code><?= $sp['root_path'] === '' ? '(site default)' : sanitize_data($sp['root_path']) ?></code></div>
            <div><?= (int) ($sp['file_count'] ?? 0) ?></div>
            <div><?= (int) ($sp['user_count'] ?? 0) ?></div>
            <div>
                <?php if (!empty($sp['is_default'])): ?>
                    <strong>Default</strong>
                <?php else: ?>
                    <form method="post" action="admin.php" style="display:inline;">
                        <input type="hidden" name="set_default_partition" value="1">
                        <input type="hidden" name="partition_id" value="<?= (int) $sp['id'] ?>">
                        <button type="submit" class="btn btn-small">Set default</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<p>No partitions defined.</p>
<?php endif; ?>
