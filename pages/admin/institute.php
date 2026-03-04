<div class="container w-800 mw-full">

    <h2 class="title">Institut</h2>

    <form action="<?= ROOTPATH ?>/crud/admin/general" method="post">
        <div class="row row-eq-spacing">
            <div class="col-sm-2">
                <label for="icon" class="required"><?= lang('Abbreviation', 'Kürzel') ?></label>
                <input type="text" class="form-control" name="general[affiliation][id]" required value="<?= $affiliation['id'] ?>">
            </div>
            <div class="col-sm">
                <label for="name" class="required ">Name</label>
                <input type="text" class="form-control" name="general[affiliation][name]" required value="<?= $affiliation['name'] ?? '' ?>">
            </div>
            <div class="col-sm">
                <label for="link" class="required ">Link</label>
                <input type="text" class="form-control" name="general[affiliation][link]" required value="<?= $affiliation['link'] ?? '' ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="regex">
                Regular Expression (Regex) <?= lang('for affilation', 'für Affilierung') ?>
            </label>
            <input type="text" class="form-control" name="general[regex]" value="<?= $Settings->getRegex(); ?>" style="font-family: monospace;">
            <small class="text-muted">
                <?= lang('This pattern is used to match the affiliation in online repositories such as CrossRef. If you leave this empty, the institute abbreviation is used as is.', 'Dieses Muster wird verwendet, um die Zugehörigkeit in Online-Repositorien wie CrossRef abzugleichen. Wenn Sie dieses Feld leer lassen, wird die Institutsabkürzung unverändert verwendet.') ?>
                <!-- hint -->
                <br>
                <?= lang('As a reference, see', 'Als Referenz, siehe') ?> <a href="https://regex101.com/" target="_blank" rel="noopener noreferrer">Regex101</a> <?= lang('with flavour JavaScript', 'mit Flavour JavaScript') ?>.
            </small>
        </div>
        <div class="form-group">
            <label for="openalex">
                OpenAlex ID
            </label>
            <input type="text" class="form-control" name="general[affiliation][openalex]" value="<?= $affiliation['openalex'] ?? '' ?>">
            <small class="text-primary">
                <?= lang('Needed for OpenAlex imports!', 'Diese ID ist notwendig um OpenAlex-Importe zu ermöglichen!') ?>
            </small>
        </div>
        <div class="row row-eq-spacing">
            <div class="col-sm-2">
                <label for="ror">ROR (inkl. URL)</label>
                <input type="text" class="form-control" name="general[affiliation][ror]" value="<?= $affiliation['ror'] ?? 'https://ror.org/' ?>">
            </div>
            <div class="col-sm">
                <label for="location">Location</label>
                <input type="text" class="form-control" name="general[affiliation][location]" value="<?= $affiliation['location'] ?? '' ?>">
            </div>
            <div class="col-sm">
                <label for="country">Country Code (2lttr)</label>
                <input type="text" class="form-control" name="general[affiliation][country]" value="<?= $affiliation['country'] ?? 'DE' ?>">
            </div>
        </div>
        <div class="row row-eq-spacing">
            <div class="col-sm">
                <label for="lat">Latitude</label>
                <input type="float" class="form-control" name="general[affiliation][lat]" value="<?= $affiliation['lat'] ?? '' ?>">
            </div>
            <div class="col-sm">
                <label for="lng">Longitude</label>
                <input type="float" class="form-control" name="general[affiliation][lng]" value="<?= $affiliation['lng'] ?? '' ?>">
            </div>
        </div>

        <button class="btn primary">
            <i class="ph ph-floppy-disk"></i>
            <?= lang('Save', 'Speichern') ?>
        </button>
    </form>
</div>