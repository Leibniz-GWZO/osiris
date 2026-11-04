<?php

/**
 * Detail view of a case: values, approval steps, decision, files, history.
 * @var Processes $P
 * @var array $case
 * @var array $perm
 * @var array $type
 * @var array $area
 */

$id = strval($case['_id']);
$values = DB::doc2Arr($case['values'] ?? []);
$step = $P->currentStep($case);
$states = $P->stepStates($case);
$hints = in_array($case['status'], Processes::OPEN) ? $P->hints($type, $values) : [];
$files = DB::doc2Arr($case['files'] ?? []);
$history = array_reverse(DB::doc2Arr($case['history'] ?? []));
$approvers = ($case['status'] == 'submitted' && $step) ? $P->approvers($case, $step) : null;

$actions = [
    'created' => lang('created', 'angelegt'),
    'submitted' => lang('submitted', 'eingereicht'),
    'resubmitted' => lang('resubmitted', 'erneut eingereicht'),
    'approve' => lang('approved', 'freigegeben'),
    'return' => lang('returned for revision', 'zur Überarbeitung zurückgegeben'),
    'reject' => lang('rejected', 'abgelehnt'),
    'withdraw' => lang('withdrawn', 'zurückgezogen'),
    'edited' => lang('edited', 'bearbeitet'),
    'file_added' => lang('added a file', 'Datei hinzugefügt'),
    'file_deleted' => lang('deleted a file', 'Datei gelöscht'),
];
$stepIcons = [
    'done' => ['check-circle', 'text-success', lang('approved', 'freigegeben')],
    'current' => ['hourglass-medium', 'text-signal', lang('waiting for decision', 'wartet auf Entscheidung')],
    'returned' => ['arrow-u-up-left', 'text-signal', lang('returned', 'zurückgegeben')],
    'skipped' => ['minus-circle', 'text-muted', lang('not needed', 'entfällt')],
    'open' => ['circle', 'text-muted', lang('open', 'offen')],
    'not-reached' => ['circle-dashed', 'text-muted', lang('not reached', 'nicht erreicht')],
];
?>

<div class="d-flex align-items-center flex-wrap">
    <h1 class="mr-20">
        <i class="ph ph-<?= e($type['icon'] ?? 'file-text') ?>"></i>
        <?= e($P->title($case, $type)) ?>
    </h1>
    <?= Processes::statusBadge($case['status']) ?>
</div>
<p class="text-muted mt-0">
    <?= e(Processes::t($type, 'name')) ?>
    · <?= e($case['number'] ?? lang('Draft, no number yet', 'Entwurf, noch ohne Nummer')) ?>
    · <?= lang('created by', 'erstellt von') ?> <?= e($P->personName($case['created_by'])) ?>, <?= date('d.m.Y', strtotime($case['created_at'])) ?>
    <?php if (!empty($case['unit'])) { ?>
        · <?= e($P->groupName($case['unit'])) ?>
    <?php } ?>
</p>

<div class="btn-toolbar mb-20">
    <?php if ($perm['edit']) { ?>
        <a class="btn primary" href="<?= ROOTPATH ?>/processes/edit/<?= $id ?>">
            <i class="ph ph-pencil-simple"></i> <?= lang('Edit', 'Bearbeiten') ?>
        </a>
    <?php } ?>
    <?php if ($perm['submit']) { ?>
        <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-inline" onsubmit="return confirm('<?= lang('Submit the case for approval now?', 'Vorgang jetzt zur Freigabe einreichen?') ?>')">
            <?= Processes::csrfField() ?>
            <input type="hidden" name="action" value="submit">
            <button class="btn success"><i class="ph ph-paper-plane-tilt"></i> <?= $case['status'] == 'returned' ? lang('Resubmit', 'Erneut einreichen') : lang('Submit', 'Einreichen') ?></button>
        </form>
    <?php } ?>
    <?php if ($perm['withdraw']) { ?>
        <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-inline" onsubmit="return confirm('<?= lang('Withdraw the case? This cannot be undone.', 'Vorgang zurückziehen? Das lässt sich nicht rückgängig machen.') ?>')">
            <?= Processes::csrfField() ?>
            <input type="hidden" name="action" value="withdraw">
            <button class="btn"><i class="ph ph-arrow-counter-clockwise"></i> <?= lang('Withdraw', 'Zurückziehen') ?></button>
        </form>
    <?php } ?>
    <?php if ($perm['delete']) { ?>
        <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-inline" onsubmit="return confirm('<?= lang('Delete the draft?', 'Entwurf löschen?') ?>')">
            <?= Processes::csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <button class="btn danger"><i class="ph ph-trash"></i> <?= lang('Delete draft', 'Entwurf löschen') ?></button>
        </form>
    <?php } ?>
</div>

<?php if ($P->hasLostStep($case)) { ?>
    <div class="alert danger mb-10">
        <i class="ph ph-warning"></i>
        <?= lang('The current step of this case (', 'Den aktuellen Schritt dieses Vorgangs (') ?><code><?= e($case['step']) ?></code><?= lang(') no longer exists in the definition. Nobody can decide. Please contact the administration of OSIRIS.', ') gibt es in der Definition nicht mehr. Niemand kann entscheiden. Bitte wende dich an die OSIRIS-Administration.') ?>
    </div>
<?php } ?>

<?php foreach ($hints as $hint) { ?>
    <div class="alert signal mb-10"><i class="ph ph-warning"></i> <?= e($hint) ?></div>
<?php } ?>

<div class="row row-eq-spacing">
    <div class="col-lg-8">

        <?php if ($perm['decide']) { ?>
            <div class="box padded" id="decide" style="border-color: var(--signal-color)">
                <h4 class="title mt-0"><?= lang('Your decision', 'Deine Entscheidung') ?>: <?= e(Processes::t($step)) ?></h4>
                <?php if (!empty($step['help'])) { ?>
                    <p class="text-muted"><?= e(Processes::t($step, 'help')) ?></p>
                <?php } ?>
                <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" id="decide-form">
                    <?= Processes::csrfField() ?>
                    <input type="hidden" name="action" value="" id="decide-action">
                    <div class="form-group">
                        <label for="decide-comment"><?= lang('Comment (required for return and rejection)', 'Kommentar (Pflicht bei Rückgabe und Ablehnung)') ?></label>
                        <textarea class="form-control" name="comment" id="decide-comment" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn success" onclick="return procDecide('approve')"><i class="ph ph-check"></i> <?= lang('Approve', 'Freigeben') ?></button>
                    <button type="submit" class="btn signal" onclick="return procDecide('return')"><i class="ph ph-arrow-u-up-left"></i> <?= lang('Return for revision', 'Zur Überarbeitung zurückgeben') ?></button>
                    <button type="submit" class="btn danger" onclick="return procDecide('reject')"><i class="ph ph-x"></i> <?= lang('Reject', 'Ablehnen') ?></button>
                    <?php if (!empty($step['editable'])) { ?>
                        <a class="btn ml-10" href="<?= ROOTPATH ?>/processes/edit/<?= $id ?>"><i class="ph ph-pencil-simple"></i> <?= lang('Fill in fields of this step', 'Felder dieses Schritts ausfüllen') ?></a>
                    <?php } ?>
                </form>
            </div>
            <script>
                function procDecide(action) {
                    var comment = document.getElementById('decide-comment').value.trim();
                    if (action != 'approve' && comment === '') {
                        toastError('<?= lang('Please give a reason.', 'Bitte gib eine Begründung an.') ?>');
                        return false;
                    }
                    var q = {
                        approve: '<?= lang('Approve this step?', 'Diesen Schritt freigeben?') ?>',
                        return: '<?= lang('Return the case to the applicant?', 'Vorgang an die antragstellende Person zurückgeben?') ?>',
                        reject: '<?= lang('Reject the case? This ends the process.', 'Vorgang ablehnen? Damit ist der Vorgang beendet.') ?>'
                    };
                    if (!confirm(q[action])) return false;
                    document.getElementById('decide-action').value = action;
                    return true;
                }
            </script>
        <?php } ?>

        <div class="box padded">
            <?php ProcessForm::display($type, $values, $P); ?>
        </div>

        <?php if (($type['attachments'] ?? true)) { ?>
            <div class="box padded" id="files">
                <h4 class="title mt-0"><?= lang('Attachments', 'Anhänge') ?></h4>
                <?php if (!empty($type['attachments_help'])) { ?>
                    <p class="text-muted"><?= e(Processes::t($type, 'attachments_help')) ?></p>
                <?php } ?>
                <?php if (empty($files)) { ?>
                    <p class="text-muted"><?= lang('No attachments.', 'Keine Anhänge.') ?></p>
                <?php } else { ?>
                    <table class="table small">
                        <tbody>
                            <?php foreach ($files as $f) { ?>
                                <tr>
                                    <td>
                                        <a href="<?= ROOTPATH ?>/processes/files/<?= $id ?>/<?= e($f['id']) ?>"><i class="ph ph-file"></i> <?= e($f['name']) ?></a>
                                        <small class="text-muted">(<?= round(($f['size'] ?? 0) / 1024) ?> KB)</small>
                                    </td>
                                    <td class="text-muted"><?= e($P->personName($f['by'])) ?>, <?= date('d.m.Y', strtotime($f['at'])) ?></td>
                                    <td class="text-right">
                                        <?php if (ProcessFiles::canDelete($P, $case, $f)) { ?>
                                            <form action="<?= ROOTPATH ?>/processes/files/<?= $id ?>/<?= e($f['id']) ?>/delete" method="post" class="d-inline" onsubmit="return confirm('<?= lang('Delete the file?', 'Datei löschen?') ?>')">
                                                <?= Processes::csrfField() ?>
                                                <button class="btn link text-danger small" title="<?= lang('Delete', 'Löschen') ?>"><i class="ph ph-trash"></i></button>
                                            </form>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
                <?php if ($perm['upload']) { ?>
                    <form action="<?= ROOTPATH ?>/processes/files/<?= $id ?>" method="post" enctype="multipart/form-data" class="d-flex align-items-center mt-10">
                        <?= Processes::csrfField() ?>
                        <input type="file" name="file" class="form-control mr-10" required>
                        <button class="btn"><i class="ph ph-upload"></i> <?= lang('Upload', 'Hochladen') ?></button>
                    </form>
                    <small class="text-muted"><?= lang('Max. 20 MB', 'Max. 20 MB') ?>: <?= implode(', ', ProcessFiles::EXTENSIONS) ?></small>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <div class="col-lg-4">
        <?php if (!empty($states)) { ?>
            <div class="box padded">
                <h4 class="title mt-0"><?= lang('Approval', 'Freigabe') ?></h4>
                <ol class="proc-steps mb-0" style="list-style:none;padding-left:0">
                    <?php foreach ($states as $s) {
                        [$icon, $color, $label] = $stepIcons[$s['status']] ?? $stepIcons['open']; ?>
                        <li class="mb-10">
                            <i class="ph ph-<?= $icon ?> <?= $color ?>"></i>
                            <b><?= e(Processes::t($s['step'])) ?></b>
                            <br>
                            <small class="text-muted">
                                <?= $label ?>
                                <?php if ($s['status'] == 'done') { ?>
                                    – <?= e($P->personName($s['by'])) ?>, <?= date('d.m.Y', strtotime($s['at'])) ?>
                                <?php } ?>
                            </small>
                            <?php if ($s['status'] == 'current' && $approvers !== null) { ?>
                                <br>
                                <?php if (empty($approvers)) { ?>
                                    <small class="text-danger"><?= lang('Nobody is responsible for this step. Please contact the administration of OSIRIS.', 'Für diesen Schritt ist niemand zuständig. Bitte wende dich an die OSIRIS-Administration.') ?></small>
                                <?php } else { ?>
                                    <small><?= lang('Responsible', 'Zuständig') ?>: <?= e(implode(', ', array_map([$P, 'personName'], $approvers))) ?></small>
                                <?php } ?>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            </div>
        <?php } ?>

        <div class="box padded">
            <h4 class="title mt-0"><?= lang('History', 'Verlauf') ?></h4>
            <ul class="mb-0" style="list-style:none;padding-left:0">
                <?php foreach ($history as $h) { ?>
                    <li class="mb-10">
                        <small class="text-muted"><?= date('d.m.Y H:i', strtotime($h['at'])) ?></small><br>
                        <?= e($P->personName($h['by'])) ?>:
                        <?= $actions[$h['action']] ?? e($h['action']) ?>
                        <?php if (!empty($h['step'])) {
                            foreach ($type['steps'] as $st) {
                                if ($st['id'] == $h['step']) echo '<small class="text-muted">(' . e(Processes::t($st)) . ')</small>';
                            }
                        } ?>
                        <?php if (!empty($h['fields'])) { ?>
                            <br><small class="text-muted"><?= e(implode(', ', array_map(function ($f) use ($type) {
                                                                return Processes::t(ProcessForm::field($type, $f) ?? ['label' => $f]);
                                                            }, DB::doc2Arr($h['fields'])))) ?></small>
                        <?php } ?>
                        <?php if (!empty($h['comment'])) { ?>
                            <div class="text-muted" style="white-space:pre-line">„<?= e($h['comment']) ?>“</div>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</div>
