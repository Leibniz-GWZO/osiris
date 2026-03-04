<form action="<?= ROOTPATH ?>/crud/admin/general" method="post" enctype="multipart/form-data">

    <div class="container w-800 mw-full">
        <h2 class="title">Logo</h2>

        <b><?= lang('Current Logo', 'Derzeitiges Logo') ?>: <br></b>
        <div class="w-300 mw-full my-20">

            <?= $Settings->printLogo("img-fluid") ?>
        </div>

        <div class="custom-file mb-20" id="file-input-div">
            <input type="file" id="file-input" name="logo" data-default-value="<?= lang("No file chosen", "Keine Datei ausgewählt") ?>">
            <label for="file-input"><?= lang('Upload a new logo', 'Lade ein neues Logo hoch') ?></label>
            <br><small class="text-danger">Max. 2 MB.</small>
        </div>

        <button class="btn primary">
            <i class="ph ph-floppy-disk"></i>
            <?= lang('Save', 'Speichern') ?>
        </button>
    </div>
</form>