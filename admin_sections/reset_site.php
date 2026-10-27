<section>
    <h2 class="page-title">Reset Site</h2>
    <div class="stats-alert is-danger">
        This deletes every non-admin user, every uploaded file and thumbnail, every file record, and restores settings to their defaults. It cannot be undone.
    </div>
    <form method="post" class="form-actions" onsubmit="return confirm('Are you sure? This action is irreversible.');">
        <input type="hidden" name="confirm_reset" value="yes">
        <button type="submit" class="btn btn-danger">Reset Site</button>
    </form>
</section>
