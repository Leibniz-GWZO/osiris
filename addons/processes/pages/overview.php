<?php

/**
 * Overview of the phase flow projects of an area: a table (one row per
 * project, one column per phase with its note) or a board (one column per
 * phase with the projects in it). View options are kept in the browser.
 * @var Processes $P
 * @var array $area
 * @var array $access
 * @var array $cases cases the user may see
 */

$fmt = function ($d) {
    return $d ? date('d.m.y', strtotime($d)) : '';
};
$order = ['active' => 0, 'on_hold' => 1, 'published' => 2, 'rejected' => 3, 'withdrawn' => 4];
?>

<h1>
    <i class="ph ph-<?= e($area['icon'] ?? 'files') ?>"></i>
    <?= e(Processes::t($area)) ?>: <?= lang('Overview', 'Übersicht') ?>
</h1>

<?php foreach ($P->types($area['id']) as $type) {
    if (!ProcessPhases::isPhases($type)) continue;
    $rows = array_values(array_filter($cases, function ($c) use ($type) {
        return $c['type'] == $type['id'];
    }));
    if (empty($rows) && !ProcessPhases::canManage($P, $type) && !$P->canSeeAll($type)) continue;
    usort($rows, function ($a, $b) use ($type, $order) {
        $s = ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);
        if ($s) return $s;
        return (ProcessPhases::phaseIndex($type, $b['phase'] ?? null) ?? -1) <=> (ProcessPhases::phaseIndex($type, $a['phase'] ?? null) ?? -1);
    });
    $units = array_unique(array_filter(array_column($rows, 'unit')));
    $tid = e($type['id']);

    // prepare rows once for table and board
    $items = [];
    foreach ($rows as $c) {
        $values = DB::doc2Arr($c['values'] ?? []);
        $closed = !in_array($c['status'], ProcessPhases::OPEN);
        $openTasks = $closed ? [] : array_values(array_filter(ProcessPhases::tasks($P, $c, $type), function ($t) {
            return !$t['done'];
        }));
        $items[] = [
            'case' => $c,
            'values' => $values,
            'log' => DB::doc2Arr($c['phase_log'] ?? []),
            'cur' => ProcessPhases::phaseIndex($type, $c['phase'] ?? null),
            'closed' => $closed,
            'overdue' => ProcessPhases::overdue($type, $c),
            'tasks' => $openTasks,
            'authors' => ProcessForm::plainValue(ProcessForm::field($type, $type['authors_field'] ?? ''), $values[$type['authors_field'] ?? ''] ?? null, $P),
            'funding' => ProcessForm::plainValue(ProcessForm::field($type, $type['funding_field'] ?? ''), $values[$type['funding_field'] ?? ''] ?? null, $P),
        ];
    }
    $nOpen = count(array_filter($items, fn($i) => !$i['closed']));
    $nOverdue = count(array_filter($items, fn($i) => $i['overdue']));
    $nTasks = count(array_filter($items, fn($i) => !empty($i['tasks'])));

    $badges = function ($it) {
        $html = '';
        if ($it['overdue']) {
            $html .= ' <span class="badge signal" title="' . lang('Longer than the target duration', 'Länger als die Soll-Dauer') . '"><i class="ph ph-clock-countdown"></i></span>';
        }
        if (!empty($it['tasks'])) {
            $titles = implode(', ', array_map(fn($t) => Processes::t($t['task']), $it['tasks']));
            $html .= ' <span class="badge" title="' . lang('Open tasks', 'Offene Aufgaben') . ': ' . e($titles) . '"><i class="ph ph-list-checks"></i> ' . count($it['tasks']) . '</span>';
        }
        return $html;
    };
?>
    <section class="proc-overview" id="ov-<?= $tid ?>" data-tid="<?= $tid ?>">
        <div class="d-flex align-items-center flex-wrap mt-20 mb-5">
            <h3 class="my-0 mr-20"><?= e(Processes::t($type, 'name')) ?></h3>
            <span class="text-muted mr-20">
                <?= $nOpen ?> <?= lang('running', 'laufend') ?>
                <?php if ($nOverdue) { ?> · <span class="text-signal"><i class="ph ph-clock-countdown"></i> <?= $nOverdue ?> <?= lang('overdue', 'überfällig') ?></span><?php } ?>
                <?php if ($nTasks) { ?> · <i class="ph ph-list-checks"></i> <?= $nTasks ?> <?= lang('with open tasks', 'mit offenen Aufgaben') ?><?php } ?>
            </span>
            <?php if (ProcessPhases::canManage($P, $type)) { ?>
                <a class="btn success ml-auto" href="<?= ROOTPATH ?>/processes/<?= e($area['id']) ?>/new/<?= $tid ?>"><i class="ph ph-plus-circle"></i> <?= lang('New project', 'Neues Projekt') ?></a>
            <?php } ?>
        </div>

        <div class="proc-toolbar">
            <div class="btn-group mr-10">
                <button type="button" class="btn small" data-mode="table"><i class="ph ph-table"></i> <?= lang('Table', 'Tabelle') ?></button>
                <button type="button" class="btn small" data-mode="board"><i class="ph ph-kanban"></i> Board</button>
            </div>
            <select class="form-control small w-auto mr-10" data-opt="unit">
                <option value=""><?= lang('All departments', 'Alle Abteilungen') ?></option>
                <?php foreach ($units as $u) { ?>
                    <option value="<?= e($u) ?>"><?= e($P->groupName($u)) ?></option>
                <?php } ?>
            </select>
            <label class="proc-opt"><input type="checkbox" data-opt="action"> <?= lang('Needs action', 'Handlungsbedarf') ?></label>
            <label class="proc-opt"><input type="checkbox" data-opt="closed"> <?= lang('Published and closed', 'Erschienene und beendete') ?></label>
            <span class="proc-sep"></span>
            <label class="proc-opt table-only"><input type="checkbox" data-opt="compact" checked> <?= lang('Completed phases compact', 'Erledigte Phasen kompakt') ?></label>
            <label class="proc-opt table-only"><input type="checkbox" data-opt="full"> <?= lang('Full notes', 'Notizen vollständig') ?></label>
            <label class="proc-opt table-only"><input type="checkbox" data-opt="funding" checked> <?= lang('Funding', 'Finanzierung') ?></label>
        </div>

        <!-- table -->
        <div class="proc-matrix-wrap view-table">
            <table class="proc-matrix">
                <thead>
                    <tr>
                        <th class="sticky col-project"><?= lang('Project', 'Projekt') ?></th>
                        <th class="col-fit"><?= lang('Dept.', 'Abt.') ?></th>
                        <th class="col-funding"><?= lang('Funding', 'Finanzierung') ?></th>
                        <?php foreach ($type['phases'] as $i => $ph) { ?>
                            <th class="col-phase" data-phase="<?= $i ?>" title="<?= e(Processes::t($ph)) ?>">
                                <div class="th-label">
                                    <?= $i ?>. <?= e(Processes::t($ph)) ?>
                                    <?php if (ProcessPhases::durationLabel($ph)) { ?><br><small class="text-muted font-weight-normal"><?= e(ProcessPhases::durationLabel($ph)) ?></small><?php } ?>
                                </div>
                            </th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it) {
                        $c = $it['case']; ?>
                        <tr class="status-<?= e($c['status']) ?>" data-unit="<?= e($c['unit'] ?? '') ?>" data-closed="<?= $it['closed'] ? 1 : 0 ?>" data-action="<?= ($it['overdue'] || !empty($it['tasks'])) ? 1 : 0 ?>">
                            <td class="sticky col-project">
                                <div class="project-cell">
                                    <a href="<?= ROOTPATH ?>/processes/view/<?= $c['_id'] ?>" class="proc-title"><b><?= e($P->title($c, $type)) ?></b></a>
                                    <?php if ($it['authors']) { ?><div class="proc-authors"><?= e($it['authors']) ?></div><?php } ?>
                                    <div><?= Processes::statusBadge($c['status']) ?><?= $badges($it) ?></div>
                                </div>
                            </td>
                            <td class="col-fit" title="<?= e($c['unit'] ? $P->groupName($c['unit']) : '') ?>"><?= e($c['unit'] ?? '') ?></td>
                            <td class="col-funding" title="<?= e($it['funding']) ?>"><div class="clamp"><?= e($it['funding']) ?></div></td>
                            <?php foreach ($type['phases'] as $i => $ph) {
                                $cur = $it['cur'];
                                $state = $cur === null ? 'future' : ($i < $cur ? 'done' : ($i === $cur ? 'current' : 'future'));
                                $note = (string) ($it['values']['note_' . $ph['id']] ?? '');
                                $pl = DB::doc2Arr($it['log'][$ph['id']] ?? []);
                                $dates = !empty($pl['start']) && $state != 'future' ? $fmt($pl['start']) . (!empty($pl['end']) ? '–' . $fmt($pl['end']) : '') : '';
                            ?>
                                <td class="col-phase phase-<?= $state ?>" data-phase="<?= $i ?>" title="<?= e($note) ?>">
                                    <?php if ($state == 'done') { ?><span class="done-mark"><i class="ph ph-check"></i> <?= $dates ?></span><?php } ?>
                                    <?php if ($note !== '') { ?><div class="clamp note"><?= e($note) ?></div><?php } ?>
                                    <?php if ($dates && $state != 'done') { ?><div class="text-muted dates"><?= $dates ?></div><?php } ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                    <?php if (empty($items)) { ?>
                        <tr>
                            <td colspan="<?= 3 + count($type['phases']) ?>" class="text-muted"><?= lang('No projects yet.', 'Noch keine Projekte.') ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- board -->
        <div class="proc-board view-board">
            <?php foreach ($type['phases'] as $i => $ph) {
                $inPhase = array_filter($items, fn($it) => $it['cur'] === $i); ?>
                <div class="proc-lane" data-phase="<?= $i ?>">
                    <div class="proc-lane-head">
                        <b><?= $i ?>. <?= e(Processes::t($ph)) ?></b>
                        <span class="text-muted count"></span>
                        <?php if (ProcessPhases::durationLabel($ph)) { ?><br><small class="text-muted"><?= e(ProcessPhases::durationLabel($ph)) ?></small><?php } ?>
                    </div>
                    <?php foreach ($inPhase as $it) {
                        $c = $it['case'];
                        $note = (string) ($it['values']['note_' . $ph['id']] ?? '');
                        $weeks = ProcessPhases::weeksInPhase($c); ?>
                        <a class="proc-card status-<?= e($c['status']) ?>" href="<?= ROOTPATH ?>/processes/view/<?= $c['_id'] ?>" data-unit="<?= e($c['unit'] ?? '') ?>" data-closed="<?= $it['closed'] ? 1 : 0 ?>" data-action="<?= ($it['overdue'] || !empty($it['tasks'])) ? 1 : 0 ?>" title="<?= e($note) ?>">
                            <b class="clamp2"><?= e($P->title($c, $type)) ?></b>
                            <?php if ($it['authors']) { ?><div class="proc-authors"><?= e($it['authors']) ?></div><?php } ?>
                            <div class="small mt-5">
                                <?php if ($c['status'] != 'active') { ?><?= Processes::statusBadge($c['status']) ?><?php } ?>
                                <?php if ($c['unit']) { ?><span class="badge"><?= e($c['unit']) ?></span><?php } ?>
                                <?php if ($weeks !== null && !$it['closed']) { ?><span class="text-muted"><?= $weeks < 1 ? lang('new', 'neu') : lang('for', 'seit') . ' ' . round($weeks) . ' ' . lang('wk.', 'Wo.') ?></span><?php } ?>
                                <?= $badges($it) ?>
                            </div>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </section>
<?php } ?>

<script>
    (function() {
        // per-viewer options, kept in the browser
        function load(tid) {
            try {
                return JSON.parse(localStorage.getItem('proc-overview-' + tid)) || {};
            } catch (e) {
                return {};
            }
        }

        function save(tid, o) {
            try {
                localStorage.setItem('proc-overview-' + tid, JSON.stringify(o));
            } catch (e) {}
        }

        function apply(sec) {
            var tid = sec.dataset.tid;
            var o = {
                mode: sec.dataset.mode || 'table'
            };
            sec.querySelectorAll('[data-opt]').forEach(function(el) {
                o[el.dataset.opt] = el.type == 'checkbox' ? el.checked : el.value;
            });
            save(tid, o);

            sec.querySelectorAll('[data-mode]').forEach(function(b) {
                b.classList.toggle('primary', b.dataset.mode == o.mode);
            });
            sec.classList.toggle('mode-board', o.mode == 'board');
            sec.classList.toggle('compact', !!o.compact);
            sec.classList.toggle('full-notes', !!o.full);
            sec.classList.toggle('no-funding', !o.funding);

            sec.querySelectorAll('tr[data-unit], .proc-card').forEach(function(el) {
                var show = (!o.unit || el.dataset.unit == o.unit) &&
                    (o.closed || el.dataset.closed == '0') &&
                    (!o.action || el.dataset.action == '1');
                el.style.display = show ? '' : 'none';
            });
            sec.querySelectorAll('.proc-lane').forEach(function(lane) {
                var n = Array.from(lane.querySelectorAll('.proc-card')).filter(function(c) {
                    return c.style.display != 'none';
                }).length;
                lane.querySelector('.count').textContent = n ? '(' + n + ')' : '';
                lane.classList.toggle('empty', n == 0);
            });
        }

        document.querySelectorAll('.proc-overview').forEach(function(sec) {
            var o = load(sec.dataset.tid);
            sec.dataset.mode = o.mode || 'table';
            sec.querySelectorAll('[data-opt]').forEach(function(el) {
                if (!(el.dataset.opt in o)) return;
                if (el.type == 'checkbox') el.checked = !!o[el.dataset.opt];
                else el.value = o[el.dataset.opt];
            });
            sec.querySelectorAll('[data-opt]').forEach(function(el) {
                el.addEventListener('change', function() {
                    apply(sec);
                });
            });
            sec.querySelectorAll('[data-mode]').forEach(function(b) {
                b.addEventListener('click', function() {
                    sec.dataset.mode = b.dataset.mode;
                    apply(sec);
                });
            });
            apply(sec);
        });
    })();
</script>

<style>
    .proc-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .25rem .75rem;
        margin-bottom: .75rem;
    }

    .proc-toolbar .proc-opt {
        margin: 0;
        white-space: nowrap;
        cursor: pointer;
    }

    .proc-toolbar .proc-sep {
        width: 1px;
        align-self: stretch;
        background: var(--border-color);
    }

    .proc-overview.mode-board .table-only,
    .proc-overview.mode-board .proc-sep,
    .proc-overview.mode-board .view-table,
    .proc-overview:not(.mode-board) .view-board {
        display: none;
    }

    /* table */
    .proc-matrix-wrap {
        overflow-x: auto;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        background: white;
    }

    .proc-matrix {
        border-collapse: collapse;
        font-size: 1.2rem;
        /* only as wide as the content: with width auto, Chrome stretches the
           table to the container and gives the rest to a random column */
        width: max-content;
    }

    .proc-matrix th,
    .proc-matrix td {
        border: 1px solid var(--border-color);
        padding: .35rem .5rem;
        vertical-align: top;
    }

    .proc-matrix th {
        background: var(--gray-color-very-light, #f3f6f9);
        text-align: left;
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    /* columns only take the space they need: the width comes from the
       text blocks inside the cells, empty and compact cells stay narrow */
    .proc-matrix .col-fit {
        white-space: nowrap;
    }

    /* fixed blocks inside the cells keep the browser from stretching the
       table to the length of the longest single line */
    .proc-matrix .project-cell {
        width: 16rem;
    }

    /* fixed width: a max-width here makes Chrome hand the difference to
       another column */
    .proc-matrix .th-label {
        width: 6.5rem;
    }

    .proc-matrix .col-funding .clamp {
        width: 10rem;
    }

    .proc-matrix th.col-phase {
        font-size: .95em;
    }

    .proc-matrix .col-phase .note {
        width: 10rem;
    }

    .proc-overview.full-notes .proc-matrix .col-phase .note {
        width: 14rem;
    }

    .proc-overview.no-funding .col-funding {
        display: none;
    }

    .proc-matrix .sticky {
        position: sticky;
        left: 0;
        background: white;
        z-index: 1;
    }

    .proc-matrix th.sticky {
        z-index: 2;
        background: var(--gray-color-very-light, #f3f6f9);
    }

    .proc-matrix .clamp {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .proc-overview.full-notes .proc-matrix .clamp {
        display: block;
        -webkit-line-clamp: unset;
    }

    .proc-matrix .proc-authors,
    .proc-board .proc-authors {
        color: var(--muted-color, #666);
        font-size: .95em;
    }

    .proc-matrix td.phase-done {
        background: #e3f1dc;
    }

    .proc-matrix td.phase-current {
        background: #fff1d6;
        box-shadow: inset 0 0 0 2px var(--signal-color);
    }

    .proc-matrix td.phase-future {
        background: #fcfcfc;
    }

    .proc-matrix .done-mark {
        color: var(--success-color);
        font-size: .9em;
        white-space: nowrap;
    }

    .proc-matrix .phase-done .done-mark+.note {
        margin-top: .15rem;
    }

    /* compact: completed phases only show the check mark */
    .proc-overview.compact .proc-matrix td.phase-done .note {
        display: none;
    }

    .proc-overview.compact .proc-matrix td.phase-done {
        min-width: 3.5rem;
    }

    .proc-matrix tr.status-on_hold td {
        background: #ececec;
        color: #666;
    }

    .proc-matrix .dates {
        font-size: .9em;
        margin-top: .2rem;
    }

    /* board */
    .proc-board {
        display: flex;
        gap: .5rem;
        overflow-x: auto;
        padding-bottom: .5rem;
        align-items: flex-start;
    }

    .proc-lane {
        flex: 0 0 15rem;
        background: var(--gray-color-very-light, #f3f6f9);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: .5rem;
        font-size: 1.2rem;
    }

    .proc-lane.empty {
        flex-basis: 8rem;
        opacity: .6;
    }

    .proc-lane-head {
        margin-bottom: .5rem;
    }

    .proc-card {
        display: block;
        background: white;
        border: 1px solid var(--border-color);
        border-left: 3px solid var(--signal-color);
        border-radius: var(--border-radius);
        padding: .4rem .5rem;
        margin-bottom: .4rem;
        color: inherit;
        text-decoration: none;
    }

    .proc-card:hover {
        border-color: var(--primary-color);
        text-decoration: none;
    }

    .proc-card.status-on_hold {
        border-left-color: #aaa;
        background: #f1f1f1;
    }

    .proc-card.status-published {
        border-left-color: var(--success-color);
    }

    .proc-card .clamp2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
