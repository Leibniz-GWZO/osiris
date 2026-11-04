<?php

/**
 * @var Processes $P
 * @var array $area
 * @var array $access
 * @var array $cases
 */

$areaId = $area['id'];
$thisYear = (int) date('Y');
$year = (int) ($_GET['year'] ?? $thisYear);
if ($year < $thisYear - 5 || $year > $thisYear + 5) $year = $thisYear;
$unit = (string) ($_GET['unit'] ?? '');
$departments = array_filter($P->groups(), fn($g) => ($g['level'] ?? null) == 1 && empty($g['inactive']));
if ($unit !== '' && !isset($departments[$unit])) $unit = '';
$canEdit = ProcessTargets::canEdit($P, $areaId);
$edit = $canEdit && !empty($_GET['edit']) && $unit === '';
$base = ROOTPATH . '/processes/' . e($areaId) . '?view=targets';
$unitQuery = $unit !== '' ? '&unit=' . urlencode($unit) : '';

$categories = $P->publicationTypes();
$targets = ProcessTargets::targets($P, $areaId, $year);
$published = ProcessTargets::published($P, $year, $unit ?: null);
$plan = ProcessTargets::planned($P, $cases, $year, $unit ?: null);
$planned = $plan['planned'];

$rows = [];
foreach ($categories as $id => $c) {
    $row = ['id' => $id, 'label' => lang($c['en'], $c['de']), 'target' => $targets[$id] ?? null, 'published' => $published[$id] ?? 0, 'planned' => $planned[$id] ?? []];
    if ($c['disabled'] && !$row['target'] && !$row['published'] && !$row['planned']) continue;
    $rows[] = $row;
}
if (!empty($planned[''])) {
    $rows[] = ['id' => '', 'label' => lang('without type', 'ohne Typ'), 'target' => null, 'published' => 0, 'planned' => $planned['']];
}
$sum = ['target' => 0, 'published' => 0, 'planned' => 0, 'with_target' => 0];
$scale = 1;
foreach ($rows as $r) {
    $sum['target'] += $r['target'] ?? 0;
    $sum['published'] += $r['published'];
    $sum['planned'] += count($r['planned']);
    if ($r['target']) $sum['with_target'] += $r['published'] + count($r['planned']);
    $scale = max($scale, $r['target'] ?? 0, $r['published'] + count($r['planned']));
}
$changed = ProcessTargets::changed($P, $areaId, $year);

$glanceYears = range($thisYear - 1, $thisYear + 3);
$glance = [];
foreach ($glanceYears as $y) {
    $glance[$y] = [
        'target' => ProcessTargets::targets($P, $areaId, $y),
        'published' => ProcessTargets::published($P, $y, $unit ?: null),
        'planned' => array_map('count', ProcessTargets::planned($P, $cases, $y, $unit ?: null)['planned']),
    ];
}

$caseLine = function ($c) use ($P) {
    $type = $P->type($c['type']);
    $phase = ProcessPhases::phase($type, $c['phase'] ?? null);
    $html = '<a href="' . ROOTPATH . '/processes/view/' . $c['_id'] . '">' . e(trim(($c['number'] ?? '') . ' ' . $P->title($c, $type))) . '</a>';
    $meta = array_filter([$c['unit'] ?? null, $phase ? Processes::t($phase) : null]);
    if ($meta) $html .= ' <small class="text-muted">· ' . e(implode(' · ', $meta)) . '</small>';
    if (($c['status'] ?? '') != 'active') $html .= ' ' . Processes::statusBadge($c['status']);
    return $html;
};
$bar = function ($target, $done, $plannedN) use ($scale) {
    $pct = fn($n) => round(100 * $n / $scale, 2);
    $title = lang('published', 'erschienen') . ": $done · " . lang('planned', 'geplant') . ": $plannedN" . ($target !== null ? ' · ' . lang('target', 'Soll') . ': ' . $target : '');
    $html = '<div class="tg-bar" title="' . e($title) . '">';
    if ($done) $html .= '<span class="tg-seg tg-done" style="width:' . $pct($done) . '%"></span>';
    if ($plannedN) $html .= '<span class="tg-seg tg-planned" style="width:' . $pct($plannedN) . '%"></span>';
    if ($target) $html .= '<span class="tg-target" style="left:' . $pct($target) . '%"></span>';
    return $html . '</div>';
};
$diff = function ($target, $n) {
    if (!$target) return '<span class="text-muted">–</span>';
    $d = $n - $target;
    if ($d >= 0) return '<span class="text-success"><i class="ph ph-check" aria-hidden="true"></i> ' . ($d > 0 ? '+' . $d : lang('reached', 'erreicht')) . '</span>';
    return '<span class="text-danger"><i class="ph ph-warning" aria-hidden="true"></i> ' . $d . '</span>';
};
?>

<div class="d-flex align-items-center flex-wrap">
    <h1 class="mr-20">
        <i class="ph ph-chart-bar"></i>
        <?= e(Processes::t($area)) ?>: <?= lang('Targets', 'Soll und Ist') ?>
    </h1>
    <?php if ($access['years']) { ?>
        <a class="btn ml-auto" href="<?= ROOTPATH ?>/processes/<?= e($areaId) ?>?view=years"><i class="ph ph-calendar-dots"></i> <?= lang('Year planning', 'Jahresplanung') ?></a>
    <?php } ?>
</div>
<p class="text-muted mt-0">
    <?= lang(
        '<b>Target</b>: number of publications planned for the institute. <b>Published</b>: publications of the year recorded in OSIRIS (affiliated, not excluded from reports). <b>Planned</b>: running entries of the publication planning with this year that are not yet linked to an OSIRIS activity.',
        '<b>Soll</b>: vorgesehene Zahl für das Institut. <b>Erschienen</b>: in OSIRIS erfasste Publikationen des Jahres (affiliiert, nicht von Berichten ausgeschlossen). <b>Geplant</b>: laufende Einträge der Publikationsplanung mit diesem Jahr, die noch nicht mit einer OSIRIS-Aktivität verknüpft sind.'
    ) ?>
</p>

<div class="proc-toolbar">
    <div class="btn-group">
        <?php foreach (range($thisYear - 2, $thisYear + 3) as $y) { ?>
            <a class="btn small <?= $y == $year ? 'primary' : '' ?>" href="<?= $base ?>&year=<?= $y ?><?= e($unitQuery) ?>"><?= $y ?></a>
        <?php } ?>
    </div>
    <form method="get" action="<?= ROOTPATH ?>/processes/<?= e($areaId) ?>">
        <input type="hidden" name="view" value="targets">
        <input type="hidden" name="year" value="<?= $year ?>">
        <select class="form-control small w-auto" name="unit" onchange="this.form.submit()" aria-label="<?= lang('Department', 'Abteilung') ?>">
            <option value=""><?= lang('Whole institute', 'Ganzes Institut') ?></option>
            <?php foreach ($departments as $g) { ?>
                <option value="<?= e($g['id']) ?>" <?= $g['id'] === $unit ? 'selected' : '' ?>><?= e($P->groupName($g['id'])) ?></option>
            <?php } ?>
        </select>
    </form>
    <?php if ($canEdit && !$edit && $unit === '') { ?>
        <a class="btn small" href="<?= $base ?>&year=<?= $year ?>&edit=1"><i class="ph ph-pencil-simple"></i> <?= lang('Edit targets', 'Soll bearbeiten') ?></a>
    <?php } ?>
    <span class="tg-legend ml-auto" aria-hidden="true">
        <span class="tg-key tg-done"></span> <?= lang('published', 'erschienen') ?>
        <span class="tg-key tg-planned ml-10"></span> <?= lang('planned', 'geplant') ?>
        <span class="tg-key-target ml-10"></span> <?= lang('target', 'Soll') ?>
    </span>
</div>

<?php if ($unit !== '') { ?>
    <p class="text-muted small"><?= lang('Department view: published and planned of', 'Abteilungsansicht: erschienen und geplant für') ?> <b><?= e($P->groupName($unit)) ?></b>. <?= lang('The target applies to the whole institute and is shown for comparison.', 'Das Soll gilt für das ganze Institut und steht nur zum Vergleich da.') ?></p>
<?php } ?>

<form method="post" action="<?= ROOTPATH ?>/processes/targets/<?= e($areaId) ?>">
    <?= Processes::csrfField() ?>
    <input type="hidden" name="year" value="<?= $year ?>">
    <div class="table-responsive">
        <table class="table tg-table">
            <caption class="sr-only"><?= lang('Targets', 'Soll und Ist') ?> <?= $year ?></caption>
            <thead>
                <tr>
                    <th><?= lang('Type of publication', 'Publikationstyp') ?></th>
                    <th class="text-right"><?= lang('Target', 'Soll') ?><?= $unit !== '' ? ' <small class="text-muted">(' . lang('institute', 'Institut') . ')</small>' : '' ?></th>
                    <th class="text-right"><?= lang('Published', 'Erschienen') ?></th>
                    <th class="text-right"><?= lang('Planned', 'Geplant') ?></th>
                    <th class="text-right"><?= lang('Total', 'Zusammen') ?></th>
                    <th><?= lang('Compared to target', 'Abgleich Soll') ?></th>
                    <th class="tg-col-bar"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) {
                    $n = count($r['planned']); ?>
                    <tr>
                        <td><?= e($r['label']) ?></td>
                        <td class="text-right">
                            <?php if ($edit && $r['id'] !== '') { ?>
                                <input type="number" min="0" step="1" class="form-control small tg-input" name="values[<?= e($r['id']) ?>]" value="<?= $r['target'] ?? '' ?>" aria-label="<?= lang('Target', 'Soll') ?> <?= e($r['label']) ?>">
                            <?php } else { ?>
                                <?= $r['target'] ?? '<span class="text-muted">–</span>' ?>
                            <?php } ?>
                        </td>
                        <td class="text-right"><?= $r['published'] ?></td>
                        <td class="text-right">
                            <?php if ($n) { ?>
                                <details class="tg-details">
                                    <summary><?= $n ?></summary>
                                    <ul class="tg-list">
                                        <?php foreach ($r['planned'] as $c) { ?><li><?= $caseLine($c) ?></li><?php } ?>
                                    </ul>
                                </details>
                            <?php } else { ?>0<?php } ?>
                        </td>
                        <td class="text-right"><b><?= $r['published'] + $n ?></b></td>
                        <td><?= $unit === '' ? $diff($r['target'], $r['published'] + $n) : '<span class="text-muted">–</span>' ?></td>
                        <td class="tg-col-bar"><?= $bar($r['target'], $r['published'], $n) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th><?= lang('Total', 'Summe') ?></th>
                    <th class="text-right"><?= $sum['target'] ?: '–' ?></th>
                    <th class="text-right"><?= $sum['published'] ?></th>
                    <th class="text-right"><?= $sum['planned'] ?></th>
                    <th class="text-right"><?= $sum['published'] + $sum['planned'] ?></th>
                    <th><?= $unit === '' ? $diff($sum['target'], $sum['with_target']) . ($sum['target'] ? ' <small class="text-muted font-weight-normal d-block">' . lang('types with a target', 'Typen mit Soll') . ': ' . $sum['with_target'] . '</small>' : '') : '' ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php if ($edit) { ?>
        <button class="btn primary"><i class="ph ph-floppy-disk"></i> <?= lang('Save targets', 'Soll speichern') ?> <?= $year ?></button>
        <a class="btn" href="<?= $base ?>&year=<?= $year ?>"><?= lang('Cancel', 'Abbrechen') ?></a>
        <small class="text-muted ml-10"><?= lang('Empty = no target.', 'Leer = kein Soll.') ?></small>
    <?php } elseif ($changed && $changed[0]) { ?>
        <small class="text-muted"><?= lang('Targets last changed by', 'Soll zuletzt geändert von') ?> <?= e($P->personName($changed[0])) ?>, <?= date('d.m.Y', strtotime($changed[1])) ?></small>
    <?php } ?>
</form>

<?php if (!empty($plan['unlinked'])) { ?>
    <div class="alert signal mt-20">
        <b><?= lang('Marked as published, but not linked to an OSIRIS activity', 'Als erschienen markiert, aber nicht mit einer OSIRIS-Aktivität verknüpft') ?> (<?= count($plan['unlinked']) ?>)</b><br>
        <?= lang('They only count as published once they are recorded in OSIRIS. Please link the activity in the entry.', 'Sie zählen erst als erschienen, wenn sie in OSIRIS erfasst sind. Bitte im Eintrag die Aktivität verknüpfen.') ?>
        <ul class="tg-list mb-0">
            <?php foreach ($plan['unlinked'] as $c) { ?><li><?= $caseLine($c) ?></li><?php } ?>
        </ul>
    </div>
<?php } ?>

<h3 class="mt-20"><?= lang('Years at a glance', 'Mehrjahresübersicht') ?></h3>
<p class="text-muted small mt-0"><?= lang('Published + planned / target per year.', 'Erschienen + geplant / Soll je Jahr.') ?></p>
<div class="table-responsive">
    <table class="table small tg-glance">
        <thead>
            <tr>
                <th><?= lang('Type of publication', 'Publikationstyp') ?></th>
                <?php foreach ($glanceYears as $y) { ?>
                    <th class="text-right<?= $y == $year ? ' tg-sel' : '' ?>"><a href="<?= $base ?>&year=<?= $y ?><?= e($unitQuery) ?>"><?= $y ?></a></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r) {
                if ($r['id'] === '') continue; ?>
                <tr>
                    <td><?= e($r['label']) ?></td>
                    <?php foreach ($glanceYears as $y) {
                        $g = $glance[$y];
                        $d = $g['published'][$r['id']] ?? 0;
                        $p = $g['planned'][$r['id']] ?? 0;
                        $t = $g['target'][$r['id']] ?? null;
                        $short = $t && $unit === '' && $d + $p < $t; ?>
                        <td class="text-right<?= $y == $year ? ' tg-sel' : '' ?>" title="<?= lang('published', 'erschienen') ?> <?= $d ?>, <?= lang('planned', 'geplant') ?> <?= $p ?><?= $t ? ', ' . lang('target', 'Soll') . ' ' . $t : '' ?>">
                            <?= $d ?> + <?= $p ?>
                            <span class="text-muted">/ <?= $t ?? '–' ?></span>
                            <?php if ($short) { ?><i class="ph ph-warning text-danger" aria-label="<?= lang('below target', 'unter Soll') ?>"></i><?php } ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<style>
    .proc-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem .75rem;
        margin-bottom: .75rem;
    }

    .tg-table td,
    .tg-table th {
        vertical-align: top;
    }

    .tg-input {
        width: 6rem;
        margin-left: auto;
        text-align: right;
    }

    .tg-col-bar {
        width: 30%;
        min-width: 10rem;
    }

    .tg-bar {
        position: relative;
        display: flex;
        gap: 2px;
        height: 1.2rem;
        margin-top: .2rem;
    }

    .tg-seg {
        height: 100%;
    }

    .tg-seg:first-child {
        border-radius: 4px 0 0 4px;
    }

    .tg-seg:last-of-type {
        border-radius: 0 4px 4px 0;
    }

    .tg-seg:only-of-type {
        border-radius: 4px;
    }

    .tg-done,
    .tg-key.tg-done {
        background: #2a78d6;
    }

    .tg-planned,
    .tg-key.tg-planned {
        background: #eb6834;
    }

    .tg-target {
        position: absolute;
        top: -.2rem;
        bottom: -.2rem;
        width: 2px;
        margin-left: -1px;
        background: #0b0b0b;
    }

    .tg-legend {
        font-size: 1.2rem;
        white-space: nowrap;
    }

    .tg-key {
        display: inline-block;
        width: .9rem;
        height: .9rem;
        border-radius: 2px;
        vertical-align: -1px;
    }

    .tg-key-target {
        display: inline-block;
        width: 2px;
        height: 1rem;
        background: #0b0b0b;
        vertical-align: -2px;
    }

    .tg-details summary {
        cursor: pointer;
    }

    .tg-details[open] summary {
        margin-bottom: .25rem;
    }

    .tg-list {
        text-align: left;
        padding-left: 1.2rem;
        margin: 0;
        font-size: .95em;
    }

    .tg-glance .tg-sel {
        background: var(--gray-color-very-light, #f3f6f9);
    }

    .tg-glance td {
        white-space: nowrap;
    }
</style>
