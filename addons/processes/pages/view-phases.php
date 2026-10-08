<?php

/**
 * Detail view of a project in the phase flow: phases with notes, fields and
 * tasks, base data, files, history.
 * @var Processes $P
 * @var array $case
 * @var array $perm
 * @var array $type
 * @var array $area
 */

$id = strval($case['_id']);
$values = DB::doc2Arr($case['values'] ?? []);
$log = DB::doc2Arr($case['phase_log'] ?? []);
$currentIndex = ProcessPhases::phaseIndex($type, $case['phase'] ?? null);
$current = ProcessPhases::phase($type, $case['phase'] ?? null);
$tasks = ProcessPhases::tasks($P, $case, $type);
$myTasks = ProcessPhases::myOpenTasks($P, $case);
$files = DB::doc2Arr($case['files'] ?? []);
$history = array_reverse(DB::doc2Arr($case['history'] ?? []));
$isOpen = $P->isOpen($case);

$phaseName = function ($pid) use ($type) {
    $ph = ProcessPhases::phase($type, $pid);
    return $ph ? Processes::t($ph) : (string) $pid;
};
$fmt = function ($d) {
    return $d ? date('d.m.Y', strtotime($d)) : '';
};
?>

<div class="d-flex align-items-center flex-wrap">
    <h1 class="mr-20">
        <i class="ph ph-<?= e($type['icon'] ?? 'book') ?>"></i>
        <?= e($P->title($case, $type)) ?>
    </h1>
    <?= Processes::statusBadge($case['status']) ?>
</div>
<p class="text-muted mt-0">
    <?= e(Processes::t($type, 'name')) ?> · <?= e($case['number'] ?? '') ?>
    <?php if (!empty($case['unit'])) { ?> · <?= e($P->groupName($case['unit'])) ?><?php } ?>
    · <?= lang('created by', 'angelegt von') ?> <?= e($P->personName($case['created_by'])) ?>, <?= $fmt($case['created_at']) ?>
</p>

<?php if ($perm['manage']) { ?>
    <div class="box padded mb-20">
        <div class="d-flex flex-wrap align-items-end">
            <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-flex align-items-end flex-wrap mr-20 mb-10">
                <?= Processes::csrfField() ?>
                <input type="hidden" name="action" value="phase">
                <div class="mr-10">
                    <label for="phase-target" class="d-block small"><?= lang('Move to phase', 'In Phase verschieben') ?></label>
                    <select class="form-control" name="target" id="phase-target">
                        <?php foreach ($type['phases'] as $i => $ph) { ?>
                            <option value="<?= e($ph['id']) ?>" <?= $i === $currentIndex + 1 ? 'selected' : '' ?> <?= $i === $currentIndex ? 'disabled' : '' ?>>
                                <?= $i + 0 ?>. <?= e(Processes::t($ph)) ?><?= $i === $currentIndex ? ' (' . lang('current', 'aktuell') . ')' : '' ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mr-10">
                    <label for="phase-comment" class="d-block small"><?= lang('Comment (optional)', 'Kommentar (optional)') ?></label>
                    <input type="text" class="form-control" name="comment" id="phase-comment">
                </div>
                <button class="btn primary"><i class="ph ph-arrow-right"></i> <?= lang('Move', 'Verschieben') ?></button>
            </form>

            <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-flex align-items-end mr-20 mb-10">
                <?= Processes::csrfField() ?>
                <input type="hidden" name="action" value="status">
                <div class="mr-10">
                    <label for="status-target" class="d-block small">Status</label>
                    <select class="form-control" name="target" id="status-target">
                        <?php foreach (ProcessPhases::STATUSES as $st) {
                            $sl = Processes::status($st); ?>
                            <option value="<?= $st ?>" <?= $st == $case['status'] ? 'selected disabled' : '' ?>><?= lang($sl['en'], $sl['de']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <button class="btn"><?= lang('Set', 'Setzen') ?></button>
            </form>

            <div class="mb-10 ml-auto">
                <a class="btn" href="<?= ROOTPATH ?>/processes/edit/<?= $id ?>?section=base"><i class="ph ph-pencil-simple"></i> <?= lang('Edit base data', 'Stammdaten bearbeiten') ?></a>
                <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-inline" onsubmit="return confirm('<?= lang('Delete this project with all data and files? This cannot be undone.', 'Dieses Projekt mit allen Daten und Dateien löschen? Das lässt sich nicht rückgängig machen.') ?>')">
                    <?= Processes::csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <button class="btn danger" title="<?= lang('Delete', 'Löschen') ?>"><i class="ph ph-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<?php if (ProcessPhases::overdue($type, $case)) { ?>
    <div class="alert signal mb-10">
        <i class="ph ph-clock-countdown"></i>
        <?= lang('The project has been in the phase', 'Das Projekt ist seit') ?>
        <?= round(ProcessPhases::weeksInPhase($case)) ?> <?= lang('weeks in the phase', 'Wochen in der Phase') ?>
        „<?= e(Processes::t($current)) ?>“.
        <?= lang('Target', 'Soll') ?>: <?= e(ProcessPhases::durationLabel($current)) ?>.
    </div>
<?php } ?>

<?php if (!empty($myTasks)) { ?>
    <div class="box padded mb-20" style="border-color: var(--signal-color)">
        <h4 class="title mt-0"><?= lang('Your tasks', 'Deine Aufgaben') ?></h4>
        <?php foreach ($myTasks as $t) { ?>
            <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-flex align-items-end flex-wrap mb-10">
                <?= Processes::csrfField() ?>
                <input type="hidden" name="action" value="task_done">
                <input type="hidden" name="target" value="<?= e($t['task']['id']) ?>">
                <div class="mr-10" style="min-width: 20rem">
                    <b><?= e(Processes::t($t['task'])) ?></b>
                    <?php if (!empty($t['task']['help'])) { ?><br><small class="text-muted"><?= e(Processes::t($t['task'], 'help')) ?></small><?php } ?>
                    <input type="text" class="form-control mt-5" name="comment" placeholder="<?= lang('Comment (optional)', 'Kommentar (optional)') ?>">
                </div>
                <button class="btn success"><i class="ph ph-check"></i> <?= lang('Done', 'Erledigt') ?></button>
            </form>
        <?php } ?>
    </div>
<?php } ?>

<div class="row row-eq-spacing">
    <div class="col-lg-8">
        <?php foreach ($type['phases'] as $i => $ph) {
            $state = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : 'future');
            $pl = DB::doc2Arr($log[$ph['id']] ?? []);
            $note = $values['note_' . $ph['id']] ?? null;
            $phaseFields = array_filter(ProcessPhases::sectionFields($type, $ph['id']), function ($f) {
                return $f['type'] != 'heading' && strpos($f['id'], 'note_') !== 0;
            });
            $phaseTasks = array_filter($tasks, function ($t) use ($ph) {
                return $t['phase']['id'] == $ph['id'];
            });
            $filled = array_filter($phaseFields, function ($f) use ($values) {
                return !ProcessForm::isEmpty($values[$f['id']] ?? null) && ProcessForm::visible($f, $values);
            });
            $icon = ['done' => 'check-circle text-success', 'current' => 'circle-half text-signal', 'future' => 'circle text-muted'][$state];
        ?>
            <div class="box padded proc-phase proc-phase-<?= $state ?>" id="phase-<?= e($ph['id']) ?>">
                <div class="d-flex align-items-center">
                    <h5 class="title my-0">
                        <i class="ph ph-<?= $icon ?>"></i>
                        <?= $i ?>. <?= e(Processes::t($ph)) ?>
                    </h5>
                    <small class="text-muted ml-10">
                        <?php if (!empty($pl['start'])) { ?>
                            <?= $fmt($pl['start']) ?><?= !empty($pl['end']) ? ' – ' . $fmt($pl['end']) : ($state == 'current' ? ' – ' . lang('today', 'heute') : '') ?>
                        <?php } ?>
                        <?php if (ProcessPhases::durationLabel($ph)) { ?>
                            · <?= lang('target', 'Soll') ?> <?= e(ProcessPhases::durationLabel($ph)) ?>
                        <?php } ?>
                        <?php if (!empty($ph['responsible'])) { ?>
                            · <?= e(Processes::t($ph, 'responsible')) ?>
                        <?php } ?>
                    </small>
                    <?php if ($perm['manage']) { ?>
                        <a class="ml-auto small" href="<?= ROOTPATH ?>/processes/edit/<?= $id ?>?section=<?= e($ph['id']) ?>"><i class="ph ph-pencil-simple"></i> <?= lang('Edit', 'Bearbeiten') ?></a>
                    <?php } ?>
                </div>
                <?php if ($state == 'current' && !empty($ph['help'])) { ?>
                    <p class="text-muted small mb-0"><?= e(Processes::t($ph, 'help')) ?></p>
                <?php } ?>
                <?php if (!ProcessForm::isEmpty($note)) { ?>
                    <p class="mb-0 mt-10" style="white-space: pre-line"><?= e($note) ?></p>
                <?php } ?>
                <?php if (!empty($filled)) { ?>
                    <table class="table small simple mt-10 mb-0">
                        <?php foreach ($filled as $f) { ?>
                            <tr>
                                <td class="text-muted" style="width: 40%"><?= e(Processes::t($f)) ?></td>
                                <td><?= ProcessForm::displayValue($f, $values[$f['id']], $P) ?></td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } ?>
                <?php foreach ($phaseTasks as $t) {
                    $st = $t['state']; ?>
                    <div class="mt-10 small">
                        <?php if ($t['done']) { ?>
                            <i class="ph ph-check-square text-success"></i>
                            <?= e(Processes::t($t['task'])) ?>
                            <span class="text-muted">– <?= implode(', ', array_filter([e($P->personName($st['by'] ?? '')), $fmt($st['at'] ?? null)])) ?><?= !empty($st['comment']) ? ': „' . e($st['comment']) . '“' : '' ?></span>
                            <?php if ($perm['manage'] || ProcessPhases::isAssignee($P, $t['task'])) { ?>
                                <form action="<?= ROOTPATH ?>/processes/action/<?= $id ?>" method="post" class="d-inline">
                                    <?= Processes::csrfField() ?>
                                    <input type="hidden" name="action" value="task_reopen">
                                    <input type="hidden" name="target" value="<?= e($t['task']['id']) ?>">
                                    <button class="btn link small p-0 text-muted"><?= lang('reopen', 'wieder öffnen') ?></button>
                                </form>
                            <?php } ?>
                        <?php } else { ?>
                            <i class="ph ph-square text-signal"></i>
                            <?= e(Processes::t($t['task'])) ?>
                            <span class="text-muted">– <?= lang('open', 'offen') ?>, <?= e(implode(', ', array_map([$P, 'personName'], $P->audienceUsers($t['task']['assignees'] ?? [])))) ?></span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <div class="col-lg-4">
        <div class="box padded">
            <h4 class="title mt-0"><?= lang('Base data', 'Stammdaten') ?></h4>
            <?php ProcessForm::display(['fields' => ProcessPhases::sectionFields($type, 'base')], $values, $P); ?>
        </div>

        <div class="box padded" id="files">
            <h4 class="title mt-0"><?= lang('Attachments', 'Anhänge') ?></h4>
            <?php if (empty($files)) { ?>
                <p class="text-muted"><?= lang('No attachments.', 'Keine Anhänge.') ?></p>
            <?php } ?>
            <?php foreach ($files as $f) { ?>
                <div class="mb-5">
                    <a href="<?= ROOTPATH ?>/processes/files/<?= $id ?>/<?= e($f['id']) ?>"><i class="ph ph-file"></i> <?= e($f['name']) ?></a>
                    <small class="text-muted"><?= e($P->personName($f['by'])) ?>, <?= $fmt($f['at']) ?></small>
                    <?php if (ProcessFiles::canDelete($P, $case, $f)) { ?>
                        <form action="<?= ROOTPATH ?>/processes/files/<?= $id ?>/<?= e($f['id']) ?>/delete" method="post" class="d-inline" onsubmit="return confirm('<?= lang('Delete the file?', 'Datei löschen?') ?>')">
                            <?= Processes::csrfField() ?>
                            <button class="btn link text-danger small p-0" title="<?= lang('Delete', 'Löschen') ?>"><i class="ph ph-trash"></i></button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($perm['upload']) { ?>
                <form action="<?= ROOTPATH ?>/processes/files/<?= $id ?>" method="post" enctype="multipart/form-data" class="mt-10">
                    <?= Processes::csrfField() ?>
                    <input type="file" name="file" class="form-control mb-5" required>
                    <button class="btn small"><i class="ph ph-upload"></i> <?= lang('Upload', 'Hochladen') ?></button>
                </form>
            <?php } ?>
        </div>

        <div class="box padded">
            <h4 class="title mt-0"><?= lang('History', 'Verlauf') ?></h4>
            <ul class="mb-0" style="list-style:none;padding-left:0">
                <?php foreach ($history as $h) { ?>
                    <li class="mb-10">
                        <small class="text-muted"><?= date('d.m.Y H:i', strtotime($h['at'])) ?></small><br>
                        <?= e($P->personName($h['by'])) ?>:
                        <?php switch ($h['action']) {
                            case 'created':
                                echo lang('created', 'angelegt');
                                break;
                            case 'imported':
                                echo lang('imported from the spreadsheet', 'aus der Tabelle übernommen');
                                break;
                            case 'phase':
                                echo lang('phase', 'Phase') . ' „' . e($phaseName($h['from'] ?? '')) . '“ → „' . e($phaseName($h['to'] ?? '')) . '“';
                                break;
                            case 'status':
                                $sl = Processes::status($h['to'] ?? '');
                                echo 'Status → ' . lang($sl['en'], $sl['de']);
                                break;
                            case 'task_done':
                            case 'task_reopen':
                                $tl = $h['task'] ?? '';
                                foreach ($type['phases'] as $ph) foreach ($ph['tasks'] ?? [] as $tk) if ($tk['id'] == $tl) $tl = Processes::t($tk);
                                echo ($h['action'] == 'task_done' ? lang('task done', 'Aufgabe erledigt') : lang('task reopened', 'Aufgabe wieder geöffnet')) . ': ' . e($tl);
                                break;
                            case 'edited':
                                echo lang('edited', 'bearbeitet');
                                break;
                            case 'file_added':
                                echo lang('added a file', 'Datei hinzugefügt');
                                break;
                            case 'file_deleted':
                                echo lang('deleted a file', 'Datei gelöscht');
                                break;
                            default:
                                echo e($h['action']);
                        } ?>
                        <?php if (!empty($h['fields'])) { ?>
                            <br><small class="text-muted"><?= e(implode(', ', array_map(function ($f) use ($type) {
                                                                $field = ProcessForm::field($type, $f);
                                                                if ($field && !empty($field['phase']) && strpos($f, 'note_') === 0) {
                                                                    return lang('Note', 'Notiz') . ' ' . Processes::t(ProcessPhases::phase($type, $field['phase']));
                                                                }
                                                                return Processes::t($field ?? ['label' => $f]);
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

<style>
    .proc-phase-done {
        border-left: 4px solid var(--success-color);
    }

    .proc-phase-current {
        border-left: 4px solid var(--signal-color);
    }

    .proc-phase-future {
        opacity: .65;
    }

    .proc-phase {
        margin-bottom: 1rem;
    }
</style>
