<?php

/**
 * Form to create or edit a case.
 * @var Processes $P
 * @var array|null $case null for a new case
 * @var array|null $perm
 * @var array $type
 * @var array $area
 */

$isNew = empty($case);
$values = $isNew ? [] : DB::doc2Arr($case['values'] ?? []);
if ($isNew) {
    $editable = array_values(array_filter(ProcessForm::fieldIds($type), function ($id) use ($type) {
        return empty(ProcessForm::field($type, $id)['only_steps']);
    }));
    $canSubmit = true;
} else {
    $editable = $perm['edit_fields'];
    $canSubmit = $perm['submit'];
}
$isPhases = ProcessPhases::isPhases($type);
if ($isPhases) $canSubmit = false;
// phase flow: edit one section (base fields or one phase) at a time
$section = null;
$formType = $type;
if ($isPhases && !$isNew && !empty($_GET['section'])) {
    $section = (string) $_GET['section'];
    $formType['fields'] = ProcessPhases::sectionFields($type, $section);
    if (empty($formType['fields'])) $section = null;
}
if ($isPhases && $isNew) {
    // new projects: base fields only, phases are filled in later
    $formType['fields'] = ProcessPhases::sectionFields($type, 'base');
}
$isApproverEdit = !$isNew && !$isPhases && !$perm['submit'];

// last return comment, shown to the creator
$returned = null;
if (!$isNew && $case['status'] == 'returned') {
    foreach (array_reverse(DB::doc2Arr($case['history'] ?? [])) as $h) {
        if (($h['action'] ?? '') == 'return') {
            $returned = $h;
            break;
        }
    }
}
?>

<h1>
    <i class="ph ph-<?= e($type['icon'] ?? 'file-text') ?>"></i>
    <?= e(Processes::t($type, 'name')) ?>
    <?php if (!$isNew && !empty($case['number'])) { ?>
        <small class="text-muted"><?= e($case['number']) ?></small>
    <?php } ?>
</h1>

<?php if (!empty($type['description'])) { ?>
    <p class="text-muted"><?= e(Processes::t($type, 'description')) ?></p>
<?php } ?>

<?php if ($returned) { ?>
    <div class="alert signal mb-20">
        <h5 class="title"><?= lang('Returned for revision', 'Zur Überarbeitung zurückgegeben') ?></h5>
        <?= e($P->personName($returned['by'])) ?>, <?= date('d.m.Y H:i', strtotime($returned['at'])) ?>:
        <br><?= nl2br(e($returned['comment'] ?? '')) ?>
    </div>
<?php } ?>

<?php if ($isApproverEdit) { ?>
    <div class="alert blue mb-20">
        <?= lang('As approver of this step you can edit the fields marked for this step. Other fields are read-only.', 'Als zuständige Person für diesen Schritt kannst du die dafür vorgesehenen Felder bearbeiten. Die übrigen Felder sind schreibgeschützt.') ?>
    </div>
<?php } ?>

<form action="<?= ROOTPATH ?>/processes/save" method="post" class="box padded" id="proc-form" novalidate>
    <?= Processes::csrfField() ?>
    <?php if ($isNew) { ?>
        <input type="hidden" name="type" value="<?= e($type['id']) ?>">
    <?php } else { ?>
        <input type="hidden" name="id" value="<?= e(strval($case['_id'])) ?>">
    <?php } ?>
    <input type="hidden" name="submit" value="0" id="proc-submit">

    <?php if ($isPhases && $isNew) { ?>
        <div class="form-group">
            <label for="start-phase" class="required"><?= lang('Current phase', 'Aktuelle Phase') ?></label>
            <select class="form-control w-auto" name="start_phase" id="start-phase">
                <?php foreach ($type['phases'] as $ph) { ?>
                    <option value="<?= e($ph['id']) ?>"><?= e(Processes::t($ph)) ?></option>
                <?php } ?>
            </select>
            <small class="text-muted"><?= lang('For projects that are already running, choose the phase they are in.', 'Bei Projekten, die schon laufen, die Phase wählen, in der sie gerade sind.') ?></small>
        </div>
    <?php } ?>

    <?php if ($section) { ?>
        <input type="hidden" name="section" value="<?= e($section) ?>">
    <?php } ?>
    <?php ProcessForm::render($formType, $values, $editable, $P, $isNew); ?>

    <?php if ($isApproverEdit) { ?>
        <div class="form-group">
            <label for="proc-comment"><?= lang('Comment on the change (optional)', 'Kommentar zur Änderung (optional)') ?></label>
            <input type="text" class="form-control" name="comment" id="proc-comment">
        </div>
    <?php } ?>

    <div class="mt-20">
        <button type="submit" class="btn primary" onclick="document.getElementById('proc-submit').value='0'">
            <i class="ph ph-floppy-disk"></i>
            <?= $canSubmit ? lang('Save as draft', 'Als Entwurf speichern') : lang('Save', 'Speichern') ?>
        </button>
        <?php if ($canSubmit) { ?>
            <button type="submit" class="btn success" onclick="return procSubmit()">
                <i class="ph ph-paper-plane-tilt"></i>
                <?= !$isNew && $case['status'] == 'returned' ? lang('Save and resubmit', 'Speichern und erneut einreichen') : lang('Save and submit', 'Speichern und einreichen') ?>
            </button>
        <?php } ?>
        <a class="btn" href="<?= ROOTPATH . ($isNew ? '/processes/' . e($area['id']) . '/new' : '/processes/view/' . strval($case['_id'])) ?>">
            <?= lang('Cancel', 'Abbrechen') ?>
        </a>
    </div>
    <?php if ($canSubmit) { ?>
        <p class="text-muted small mt-10">
            <?= lang('Drafts are only visible to you. After submitting, the case goes into approval and can only be changed if it is returned to you.', 'Entwürfe siehst nur du. Nach dem Einreichen geht der Vorgang in die Freigabe und kann nur noch geändert werden, wenn er an dich zurückgegeben wird.') ?>
        </p>
    <?php } ?>
</form>

<?php ProcessForm::script(); ?>

<script>
    function procSubmit() {
        // check required fields that are visible
        var missing = [];
        document.querySelectorAll('#proc-form .proc-field').forEach(function(box) {
            if (box.style.display == 'none') return;
            var req = box.querySelector('[data-required]');
            if (!req) return;
            var filled;
            if (req.type == 'radio') filled = !!box.querySelector('input:checked');
            else filled = req.value.trim() !== '';
            box.classList.toggle('is-invalid', !filled);
            if (!filled) missing.push(box.querySelector('label').textContent.trim());
        });
        if (missing.length) {
            toastError('<?= lang('Please fill in all required fields: ', 'Bitte fülle alle Pflichtfelder aus: ') ?>' + missing.join(', '));
            return false;
        }
        if (!confirm('<?= lang('Submit the case for approval now?', 'Vorgang jetzt zur Freigabe einreichen?') ?>')) return false;
        document.getElementById('proc-submit').value = '1';
        return true;
    }
</script>

<style>
    .proc-field.is-invalid label {
        color: var(--danger-color);
    }

    .proc-readonly {
        padding: .4rem 0;
    }
</style>
