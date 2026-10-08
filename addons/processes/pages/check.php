<?php

/**
 * Overview of the process definitions for admins: areas, types, who may
 * create, approval steps and definition errors.
 * @var Processes $P
 */

$roles = DB::doc2Arr($osiris->adminGeneral->findOne(['key' => 'roles'])['value'] ?? []);

function proc_audience($a, $roles)
{
    if (empty($a)) return '<span class="text-danger">' . lang('nobody', 'niemand') . '</span>';
    $parts = [];
    if (!empty($a['all'])) $parts[] = lang('all users', 'alle Nutzenden');
    if (!empty($a['unit_heads'])) $parts[] = lang('unit heads', 'Einheitsleitungen');
    foreach ($a['roles'] ?? [] as $r) {
        $parts[] = 'Rolle <code>' . e($r) . '</code>' . (in_array($r, $roles) ? '' : ' <span class="badge danger">' . lang('missing', 'fehlt') . '</span>');
    }
    foreach ($a['units'] ?? [] as $u) $parts[] = lang('unit', 'Einheit') . ' <code>' . e($u) . '</code>';
    foreach ($a['persons'] ?? [] as $p) $parts[] = '<code>' . e($p) . '</code>';
    return implode(', ', $parts);
}
?>

<h1><i class="ph ph-gear"></i> <?= lang('Process definitions', 'Vorgangsdefinitionen') ?></h1>
<p class="text-muted">
    <?= lang('Definitions are JSON files in', 'Die Definitionen sind JSON-Dateien in') ?> <code>addons/processes/definitions</code>.
</p>

<?php foreach ($P->errors() as $err) { ?>
    <div class="alert danger mb-10"><?= e($err) ?></div>
<?php } ?>

<?php
// open cases whose current step was removed from the definition
foreach ($osiris->proc_cases->find(['status' => ['$in' => ['submitted', 'returned']]]) as $c) {
    $c = DB::doc2Arr($c);
    if (!$P->type($c['type']) || !$P->hasLostStep($c)) continue; ?>
    <div class="alert danger mb-10">
        <a href="<?= ROOTPATH ?>/processes/view/<?= $c['_id'] ?>"><?= e($c['number'] ?? strval($c['_id'])) ?></a>:
        <?= lang('current step', 'aktueller Schritt') ?> <code><?= e($c['step']) ?></code> <?= lang('does not exist anymore.', 'existiert nicht mehr.') ?>
    </div>
<?php } ?>

<?php foreach ($P->areas() as $areaId => $area) { ?>
    <h2 class="mt-20"><i class="ph ph-<?= e($area['icon'] ?? 'files') ?>"></i> <?= e(Processes::t($area)) ?> <small class="text-muted"><code><?= e($areaId) ?></code></small></h2>
    <?php if (empty($P->types($areaId))) { ?>
        <p class="text-muted"><?= lang('No process types yet. The area is hidden.', 'Noch keine Vorgangsarten. Der Bereich ist ausgeblendet.') ?></p>
    <?php } ?>
    <?php foreach ($P->types($areaId) as $t) { ?>
        <div class="box padded">
            <h4 class="title mt-0">
                <?= e(Processes::t($t, 'name')) ?> <small class="text-muted"><code><?= e($t['id']) ?></code></small>
                <?php if (!empty($t['disabled'])) { ?><span class="badge muted"><?= lang('disabled', 'deaktiviert') ?></span><?php } ?>
            </h4>
            <table class="table small">
                <tr>
                    <th style="width:25%"><?= lang('May create', 'Darf anlegen') ?></th>
                    <td><?= proc_audience($t['create'] ?? [], $roles) ?></td>
                </tr>
                <tr>
                    <th><?= lang('Sees all cases', 'Sieht alle Vorgänge') ?></th>
                    <td><?= proc_audience($t['view_all'] ?? [], $roles) ?></td>
                </tr>
                <?php if (ProcessPhases::isPhases($t)) { ?>
                    <tr>
                        <th><?= lang('Manages (phases, status, all fields)', 'Steuert (Phasen, Status, alle Felder)') ?></th>
                        <td><?= proc_audience($t['manage'] ?? [], $roles) ?></td>
                    </tr>
                    <?php foreach ($t['phases'] as $i => $ph) { ?>
                        <tr>
                            <th><?= lang('Phase', 'Phase') ?> <?= $i ?>: <?= e(Processes::t($ph)) ?></th>
                            <td>
                                <?= e(ProcessPhases::durationLabel($ph)) ?>
                                <?php foreach ($ph['tasks'] ?? [] as $task) { ?>
                                    <br><small><?= lang('Task', 'Aufgabe') ?>: <?= e(Processes::t($task)) ?> → <?= proc_audience($task['assignees'] ?? [], $roles) ?></small>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                <tr>
                    <th><?= lang('Fields', 'Felder') ?></th>
                    <td><?= count(ProcessForm::fieldIds($t)) ?></td>
                </tr>
                <tr>
                    <th><?= lang('E-mail notifications', 'E-Mail-Benachrichtigung') ?></th>
                    <td><?= !empty($t['email']) ? lang('yes', 'ja') : lang('no', 'nein') ?></td>
                </tr>
                <?php foreach ($t['steps'] ?? [] as $i => $s) {
                    $a = $s['approvers'] ?? [];
                    $desc = proc_audience(['roles' => $a['roles'] ?? [], 'persons' => $a['persons'] ?? []], $roles);
                    if (!empty($a['unit_head'])) {
                        $desc = lang('head of the unit', 'Leitung der Einheit') . ' (' . (($a['self'] ?? 'skip') == 'skip' ? lang('skipped if the applicant is the head', 'entfällt, wenn die antragstellende Person die Leitung ist') : lang('next unit above if the applicant is the head', 'nächsthöhere Einheit, wenn die antragstellende Person die Leitung ist')) . ')' . (empty($a['roles']) && empty($a['persons']) ? '' : ', ' . $desc);
                    }
                ?>
                    <tr>
                        <th><?= lang('Step', 'Schritt') ?> <?= $i + 1 ?>: <?= e(Processes::t($s)) ?></th>
                        <td>
                            <?= $desc ?>
                            <?php if (!empty($s['condition'])) { ?><br><small class="text-muted"><?= lang('Condition', 'Bedingung') ?>: <code><?= e(json_encode($s['condition'], JSON_UNESCAPED_UNICODE)) ?></code></small><?php } ?>
                            <?php if (!empty($s['editable'])) { ?><br><small class="text-muted"><?= lang('Edits', 'Bearbeitet') ?>: <?= e(implode(', ', $s['editable'])) ?></small><?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    <?php } ?>
<?php } ?>
