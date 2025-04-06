<section class="">
    <h3 class="page-title">Reset Site</h3>
    <p><strong>Warning:</strong> This will delete all non-admin users, all uploaded files and thumbnails, all file records, and reset all settings to default values.</p>

    <form method="post" onsubmit="return confirm('Are you sure? This action is irreversible.');" class="form" style="margin-top: 1rem;">
        <input type="hidden" name="confirm_reset" value="yes">
        <button type="submit" class="btn btn-danger">Reset Site</button>
    </form>
</section>
