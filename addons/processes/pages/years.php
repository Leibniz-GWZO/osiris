<?php

/**
 * @var Processes $P
 * @var array $area
 * @var array $access
 * @var array $cases
 */

$thisYear = (int) date('Y');
$order = ['active' => 0, 'on_hold' => 1, 'published' => 2, 'rejected' => 3, 'withdrawn' => 4];
$colors = ['#1f77b4', '#9c4fb8', '#c0661b', '#2e8b57', '#b03a48', '#5b6abf'];

$types = [];
foreach ($P->types($area['id']) as $type) {
    if (!ProcessPhases::isPhases($type) || !ProcessPhases::yearField($type)) continue;
    $type['_color'] = $colors[count($types) % count($colors)];
    $type['_manage'] = ProcessPhases::canManage($P, $type);
    $type['_due_year'] = ProcessPhases::dueDependsOnYear($type);
    $types[$type['id']] = $type;
}
$cases = array_values(array_filter($cases, fn($c) => isset($types[$c['type']])));
$types = array_filter($types, function ($t) use ($cases, $P) {
    return empty($t['disabled']) && ($t['_manage'] || $P->canSeeAll($t)) || in_array($t['id'], array_column($cases, 'type'));
});

$years = array_filter(array_map(fn($c) => ProcessPhases::year($c, $types[$c['type']]), $cases), fn($y) => $y !== null);
$from = min(array_merge($years, [$thisYear]));
$to = max(array_merge(array_map(fn($y) => $y + 1, $years), [$thisYear + 2]));
$lanes = range($from, $to);

$items = [];
foreach ($cases as $c) {
    $type = $types[$c['type']];
    $values = DB::doc2Arr($c['values'] ?? []);
    $closed = !in_array($c['status'], ProcessPhases::OPEN);
    $cur = ProcessPhases::phaseIndex($type, $c['phase'] ?? null);
    $phase = $cur === null ? null : $type['phases'][$cur];
    $year = ProcessPhases::year($c, $type);
    $canMove = !$closed && $P->permissions($c)['manage'];
    $overdue = ProcessPhases::overdue($type, $c);
    $tasks = $closed ? [] : array_values(array_filter(ProcessPhases::tasks($P, $c, $type), fn($t) => !$t['done']));

    $due = null;
    if ($canMove && $type['_due_year']) {
        $due = [];
        foreach (array_merge($lanes, ['']) as $y) {
            $v = $values;
            $v[ProcessPhases::yearField($type)] = $y === '' ? null : $y;
            $due[$y === '' ? 'none' : $y] = [
                'due' => $phase ? ProcessPhases::dueDate($phase, $v) : null,
                'overdue' => ProcessPhases::overdue($type, ['values' => $v] + $c),
            ];
        }
    }

    $title = $P->title($c, $type);
    $authors = ProcessForm::plainValue(ProcessForm::field($type, $type['authors_field'] ?? ''), $values[$type['authors_field'] ?? ''] ?? null, $P);
    $extraField = $type['year_extra_field'] ?? null;
    $items[] = [
        'case' => $c,
        'type' => $type,
        'year' => $year,
        'closed' => $closed,
        'move' => $canMove,
        'required' => !empty(ProcessForm::field($type, ProcessPhases::yearField($type))['required']),
        'overdue' => $overdue,
        'tasks' => $tasks,
        'cur' => $cur,
        'phase' => $phase,
        'due' => $due,
        'title' => $title,
        'authors' => ($authors !== '' && mb_strpos($title, $authors) === false) ? $authors : '',
        'extra' => $extraField ? ProcessForm::plainValue(ProcessForm::field($type, $extraField), $values[$extraField] ?? null, $P) : '',
        'extra_raw' => $extraField ? (string) ($values[$extraField] ?? '') : '',
    ];
}
$typeOrder = array_flip(array_keys($types));
usort($items, function ($a, $b) use ($typeOrder, $order) {
    return [$order[$a['case']['status']] ?? 9, $typeOrder[$a['type']['id']], $a['extra_raw'], -($a['cur'] ?? -1), $a['case']['number'] ?? '']
        <=> [$order[$b['case']['status']] ?? 9, $typeOrder[$b['type']['id']], $b['extra_raw'], -($b['cur'] ?? -1), $b['case']['number'] ?? ''];
});
$units = array_unique(array_filter(array_map(fn($i) => $i['case']['unit'] ?? null, $items)));
$canMoveAny = count(array_filter($items, fn($i) => $i['move'])) > 0;

$fmt = fn($d) => $d ? date('d.m.Y', strtotime($d)) : '';
$card = function ($it) use ($lanes, $thisYear, $fmt, $P) {
    $c = $it['case'];
    $type = $it['type'];
    $id = strval($c['_id']);
    $action = $it['overdue'] || !empty($it['tasks']);
    ob_start(); ?>
    <div class="yr-card status-<?= e($c['status']) ?><?= $it['move'] ? '' : ' yr-locked' ?>"
        data-id="<?= $id ?>" data-type="<?= e($type['id']) ?>" data-unit="<?= e($c['unit'] ?? '') ?>"
        data-closed="<?= $it['closed'] ? 1 : 0 ?>" data-action="<?= $action ? 1 : 0 ?>" data-tasks="<?= count($it['tasks']) ?>"
        data-year="<?= $it['year'] ?? '' ?>" data-required="<?= $it['required'] ? 1 : 0 ?>"
        data-label="<?= e(trim(($c['number'] ?? '') . ' ' . $it['title'])) ?>"
        data-phase="<?= e($it['phase'] ? Processes::t($it['phase']) : '') ?>"
        <?php if ($it['due'] !== null) { ?>data-due="<?= e(json_encode($it['due'])) ?>" <?php } ?>
        style="--type-color: <?= $type['_color'] ?>">
        <div class="yr-card-top">
            <span class="yr-type" title="<?= e(Processes::t($type, 'name')) ?>"><?= e($type['prefix'] ?? $type['id']) ?></span>
            <small class="text-muted yr-number"><?= e($c['number'] ?? '') ?></small>
            <?php if ($it['move']) { ?>
                <button type="button" class="yr-move-btn" aria-expanded="false" title="<?= lang('Change year', 'Jahr ändern') ?>" aria-label="<?= lang('Change year', 'Jahr ändern') ?>: <?= e($c['number'] ?? '') ?>"><i class="ph ph-calendar-dots"></i></button>
            <?php } ?>
        </div>
        <a class="yr-title" draggable="false" href="<?= ROOTPATH ?>/processes/view/<?= $id ?>"><b><?= e($it['title']) ?></b></a>
        <?php if ($it['authors'] !== '') { ?><div class="proc-authors"><?= e($it['authors']) ?></div><?php } ?>
        <div class="small mt-5">
            <?php if ($it['phase']) { ?>
                <span class="text-muted" title="<?= e(ProcessPhases::dueLabel($it['phase'], DB::doc2Arr($c['values'] ?? []))) ?>"><?= lang('Phase', 'Phase') ?> <?= ProcessPhases::number($type, $it['cur']) ?>/<?= ProcessPhases::number($type, count($type['phases']) - 1) ?>: <?= e(Processes::t($it['phase'])) ?></span>
            <?php } ?>
        </div>
        <div class="small yr-badges">
            <?php if ($c['status'] != 'active') { ?><?= Processes::statusBadge($c['status']) ?><?php } ?>
            <?php if ($it['extra'] !== '') { ?><span class="badge"><i class="ph ph-calendar-check"></i> <?= e($it['extra']) ?></span><?php } ?>
            <?php if (!empty($c['unit'])) { ?><span class="badge" title="<?= e($P->groupName($c['unit'])) ?>"><?= e($c['unit']) ?></span><?php } ?>
            <span class="badge signal yr-overdue" title="<?= lang('Longer than the target duration or past the deadline', 'Länger als die Soll-Dauer oder Frist abgelaufen') ?>" <?= $it['overdue'] ? '' : 'hidden' ?>><i class="ph ph-clock-countdown"></i> <?= lang('overdue', 'überfällig') ?></span>
            <?php if (!empty($it['tasks'])) { ?>
                <span class="badge" title="<?= lang('Open tasks', 'Offene Aufgaben') ?>: <?= e(implode(', ', array_map(fn($t) => Processes::t($t['task']), $it['tasks']))) ?>"><i class="ph ph-list-checks"></i> <?= count($it['tasks']) ?></span>
            <?php } ?>
        </div>
        <?php if ($it['move']) { ?>
            <div class="yr-move-form" hidden>
                <select class="form-control small" aria-label="<?= lang('Planned year', 'Geplantes Jahr') ?>: <?= e($c['number'] ?? '') ?>">
                    <?php if (!$it['required']) { ?><option value="" <?= $it['year'] === null ? 'selected' : '' ?>><?= lang('without year', 'ohne Jahr') ?></option><?php } ?>
                    <?php foreach ($lanes as $y) {
                        if ($y < $thisYear && $y !== $it['year']) continue; ?>
                        <option value="<?= $y ?>" <?= $y === $it['year'] ? 'selected' : '' ?>><?= $y ?></option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>
    </div>
<?php return ob_get_clean();
};
?>

<div class="d-flex align-items-center flex-wrap">
    <h1 class="mr-20">
        <i class="ph ph-calendar-dots"></i>
        <?= e(Processes::t($area)) ?>: <?= lang('Year planning', 'Jahresplanung') ?>
    </h1>
    <?php if ($access['overview']) { ?>
        <a class="btn ml-auto" href="<?= ROOTPATH ?>/processes/<?= e($area['id']) ?>?view=overview"><i class="ph ph-kanban"></i> <?= lang('Overview by phase', 'Übersicht nach Phasen') ?></a>
    <?php } ?>
</div>
<p class="text-muted mt-0">
    <?= lang('All projects by planned year of publication.', 'Alle Vorhaben nach geplantem Erscheinungsjahr.') ?>
    <?php if ($canMoveAny) { ?>
        <?= lang('Drag a card to another year or use', 'Zum Verschieben eine Karte in ein anderes Jahr ziehen oder') ?> <i class="ph ph-calendar-dots" aria-hidden="true"></i> <?= lang('on the card.', 'auf der Karte nutzen.') ?>
        <?= lang('Deadlines that depend on the year move with it.', 'Fristen, die am Jahr hängen, verschieben sich mit.') ?>
    <?php } ?>
</p>

<section class="yr-board-wrap" id="yr-board" data-area="<?= e($area['id']) ?>">
    <div class="proc-toolbar">
        <select class="form-control small w-auto" data-opt="type" aria-label="<?= lang('Kind', 'Art') ?>">
            <option value=""><?= lang('All kinds', 'Alle Arten') ?></option>
            <?php foreach ($types as $t) { ?>
                <option value="<?= e($t['id']) ?>"><?= e(Processes::t($t, 'name')) ?></option>
            <?php } ?>
        </select>
        <select class="form-control small w-auto" data-opt="unit" aria-label="<?= lang('Department', 'Abteilung') ?>">
            <option value=""><?= lang('All departments', 'Alle Abteilungen') ?></option>
            <?php foreach ($units as $u) { ?>
                <option value="<?= e($u) ?>"><?= e($P->groupName($u)) ?></option>
            <?php } ?>
        </select>
        <label class="proc-opt"><input type="checkbox" data-opt="action"> <?= lang('Needs action', 'Handlungsbedarf') ?></label>
        <label class="proc-opt"><input type="checkbox" data-opt="closed"> <?= lang('Published and closed', 'Erschienene und beendete') ?></label>
        <span class="yr-legend">
            <?php foreach ($types as $t) { ?>
                <span class="yr-type" style="--type-color: <?= $t['_color'] ?>" title="<?= e(Processes::t($t, 'name')) ?>"><?= e($t['prefix'] ?? $t['id']) ?></span> <span class="text-muted mr-10"><?= e(Processes::t($t, 'name')) ?></span>
            <?php } ?>
        </span>
    </div>

    <div class="yr-board">
        <?php foreach (array_merge(['none'], $lanes) as $y) {
            $inLane = array_filter($items, fn($it) => ($y === 'none' ? $it['year'] === null : $it['year'] === $y)); ?>
            <div class="yr-lane<?= $y === $thisYear ? ' yr-current' : '' ?><?= $y === 'none' ? ' yr-none' : '' ?><?= is_int($y) && $y < $thisYear ? ' yr-past' : '' ?>" data-year="<?= $y === 'none' ? '' : $y ?>">
                <div class="yr-lane-head">
                    <b><?= $y === 'none' ? lang('Without year', 'Ohne Jahr') : $y ?></b>
                    <?php if ($y === $thisYear) { ?><small class="text-muted"><?= lang('this year', 'dieses Jahr') ?></small><?php } ?>
                    <span class="text-muted count"></span>
                    <div class="yr-lane-types small"></div>
                </div>
                <div class="yr-cards" data-year="<?= $y === 'none' ? '' : $y ?>">
                    <?php foreach ($inLane as $it) echo $card($it); ?>
                </div>
            </div>
        <?php } ?>
    </div>
    <div class="sr-only" id="yr-live" role="status" aria-live="polite"></div>
</section>

<script src="<?= ROOTPATH ?>/js/Sortable.min.js?v=<?= OSIRIS_BUILD ?>"></script>
<script>
    (function() {
        var board = document.getElementById('yr-board');
        var key = 'proc-years-' + board.dataset.area;
        var thisYear = <?= $thisYear ?>;
        var csrf = <?= json_encode(Processes::csrfToken()) ?>;
        var root = <?= json_encode(ROOTPATH) ?>;
        var colors = <?= json_encode(array_map(fn($t) => ['prefix' => $t['prefix'] ?? $t['id'], 'color' => $t['_color']], $types)) ?>;
        var L = <?= json_encode([
                    'move' => lang('Move “%s” from %f to %t?', '„%s“ von %f nach %t verschieben?'),
                    'none' => lang('without year', 'ohne Jahr'),
                    'deadlines' => lang('The deadlines of this series depend on the year and move with it.', 'Die Fristen dieser Reihe hängen am Erscheinungsjahr und verschieben sich mit.'),
                    'current' => lang('Deadline of the current phase “%p”: %a → %b.', 'Frist der aktuellen Phase „%p“: %a → %b.'),
                    'nodue' => lang('The deadline of the current phase “%p” stays the same; later deadlines move.', 'Die Frist der aktuellen Phase „%p“ bleibt gleich; spätere Fristen verschieben sich.'),
                    'passed' => lang('Attention: this deadline will already have passed.', 'Achtung: Diese Frist ist dann bereits verstrichen.'),
                    'past' => lang('The year is in the past.', 'Das Jahr liegt in der Vergangenheit.'),
                    'saved' => lang('Moved to %t.', 'Nach %t verschoben.'),
                    'error' => lang('The year could not be changed.', 'Das Jahr konnte nicht geändert werden.'),
                    'required' => lang('The year is required for this series.', 'Das Jahr ist bei dieser Reihe Pflicht.'),
                ]) ?>;

        function fmtDate(d) {
            if (!d) return '–';
            var p = d.split('-');
            return p[2] + '.' + p[1] + '.' + p[0];
        }

        function yearLabel(y) {
            return y === '' ? L.none : y;
        }

        /* ---------- filters ---------- */
        function load() {
            try {
                return JSON.parse(localStorage.getItem(key)) || {};
            } catch (e) {
                return {};
            }
        }

        function save(o) {
            try {
                localStorage.setItem(key, JSON.stringify(o));
            } catch (e) {}
        }

        function options() {
            var o = {};
            board.querySelectorAll('[data-opt]').forEach(function(el) {
                o[el.dataset.opt] = el.type == 'checkbox' ? el.checked : el.value;
            });
            return o;
        }

        function apply() {
            var o = options();
            save(o);
            var unsettable = false;
            board.querySelectorAll('.yr-card').forEach(function(el) {
                var show = (!o.type || el.dataset.type == o.type) &&
                    (!o.unit || el.dataset.unit == o.unit) &&
                    (o.closed || el.dataset.closed == '0') &&
                    (!o.action || el.dataset.action == '1');
                el.hidden = !show;
                if (show && el.dataset.required == '0' && !el.classList.contains('yr-locked')) unsettable = true;
            });
            board.querySelectorAll('.yr-lane').forEach(function(lane) {
                var cards = Array.from(lane.querySelectorAll('.yr-card')).filter(function(c) {
                    return !c.hidden;
                });
                lane.querySelector('.count').textContent = cards.length ? '(' + cards.length + ')' : '';
                var per = {};
                cards.forEach(function(c) {
                    per[c.dataset.type] = (per[c.dataset.type] || 0) + 1;
                });
                lane.querySelector('.yr-lane-types').innerHTML = Object.keys(colors).filter(function(t) {
                    return per[t];
                }).map(function(t) {
                    return '<span class="yr-type" style="--type-color:' + colors[t].color + '">' + per[t] + ' ' + colors[t].prefix + '</span>';
                }).join(' ');
                lane.classList.toggle('empty', cards.length == 0);
                var y = lane.dataset.year;
                lane.hidden = cards.length == 0 && (y === '' ? !unsettable : parseInt(y) < thisYear);
            });
        }

        var o = load();
        board.querySelectorAll('[data-opt]').forEach(function(el) {
            if (el.dataset.opt in o) {
                if (el.type == 'checkbox') el.checked = !!o[el.dataset.opt];
                else el.value = o[el.dataset.opt];
                // a stored option that no longer exists
                if (el.tagName == 'SELECT' && el.value !== o[el.dataset.opt]) el.value = '';
            }
            el.addEventListener('change', apply);
        });
        apply();

        /* ---------- moving ---------- */
        function confirmMove(card, to) {
            var from = card.dataset.year;
            var y = to === '' ? null : parseInt(to);
            var lines = [];
            if (card.dataset.due) {
                var due = JSON.parse(card.dataset.due);
                var a = due[from === '' ? 'none' : from] || {};
                var b = due[to === '' ? 'none' : to] || {};
                lines.push(L.deadlines);
                if (a.due !== b.due) {
                    lines.push(L.current.replace('%p', card.dataset.phase).replace('%a', fmtDate(a.due)).replace('%b', fmtDate(b.due)));
                    if (b.due && b.due < new Date().toISOString().slice(0, 10)) lines.push(L.passed);
                } else if (card.dataset.phase) {
                    lines.push(L.nodue.replace('%p', card.dataset.phase));
                }
            }
            if (y !== null && y < thisYear) lines.push(L.past);
            if (!lines.length) return true;
            return confirm(L.move.replace('%s', card.dataset.label).replace('%f', yearLabel(from)).replace('%t', yearLabel(to)) + '\n\n' + lines.join('\n'));
        }

        function updateCard(card, to) {
            card.dataset.year = to;
            if (card.dataset.due) {
                var d = JSON.parse(card.dataset.due)[to === '' ? 'none' : to] || {};
                card.querySelector('.yr-overdue').hidden = !d.overdue;
                card.dataset.action = (d.overdue || parseInt(card.dataset.tasks) > 0) ? '1' : '0';
            }
            var sel = card.querySelector('.yr-move-form select');
            if (sel) sel.value = to;
        }

        function send(card, to) {
            var body = new URLSearchParams();
            body.set('csrf', csrf);
            body.set('action', 'year');
            body.set('target', to);
            body.set('json', '1');
            return fetch(root + '/processes/action/' + card.dataset.id, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }).then(function(r) {
                return r.json().catch(function() {
                    return {
                        ok: false,
                        error: L.error + ' (HTTP ' + r.status + ')'
                    };
                });
            }).catch(function() {
                return {
                    ok: false,
                    error: L.error
                };
            });
        }

        function lane(year) {
            return board.querySelector('.yr-cards[data-year="' + year + '"]');
        }

        function announce(msg) {
            document.getElementById('yr-live').textContent = msg;
        }

        function move(card, to, back) {
            var from = card.dataset.year;
            if (to === from) return;
            if (to === '' && card.dataset.required == '1') {
                toastError(L.required);
                back();
                return;
            }
            if (!confirmMove(card, to)) {
                back();
                return;
            }
            card.classList.add('yr-saving');
            send(card, to).then(function(res) {
                card.classList.remove('yr-saving');
                if (!res.ok) {
                    back();
                    toastError(res.error || L.error);
                    return;
                }
                if (card.parentNode !== lane(to)) lane(to).appendChild(card);
                updateCard(card, to);
                apply();
                var msg = L.saved.replace('%t', yearLabel(to));
                announce(card.dataset.label + ': ' + msg);
                toastSuccess(msg);
            });
        }

        board.querySelectorAll('.yr-cards').forEach(function(list) {
            if (typeof Sortable == 'undefined') return;
            Sortable.create(list, {
                group: 'years',
                sort: false,
                draggable: '.yr-card:not(.yr-locked)',
                filter: 'button, select',
                preventOnFilter: false,
                delayOnTouchOnly: true,
                delay: 150,
                ghostClass: 'yr-ghost',
                onMove: function(ev) {
                    return !(ev.to.dataset.year === '' && ev.dragged.dataset.required == '1');
                },
                onAdd: function(ev) {
                    var card = ev.item;
                    move(card, ev.to.dataset.year, function() {
                        ev.from.insertBefore(card, ev.from.children[ev.oldIndex] || null);
                    });
                }
            });
        });

        board.querySelectorAll('.yr-move-btn').forEach(function(btn) {
            var card = btn.closest('.yr-card');
            var form = card.querySelector('.yr-move-form');
            var sel = form.querySelector('select');
            btn.addEventListener('click', function() {
                form.hidden = !form.hidden;
                btn.setAttribute('aria-expanded', form.hidden ? 'false' : 'true');
                if (!form.hidden) sel.focus();
            });
            sel.addEventListener('change', function() {
                var to = sel.value;
                move(card, to, function() {
                    sel.value = card.dataset.year;
                });
                form.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
            });
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

    .yr-legend {
        margin-left: auto;
        font-size: 1.2rem;
    }

    .yr-board {
        display: flex;
        gap: .5rem;
        overflow-x: auto;
        padding-bottom: .5rem;
        align-items: stretch;
    }

    .yr-lane {
        flex: 0 0 16rem;
        display: flex;
        flex-direction: column;
        background: var(--gray-color-very-light, #f3f6f9);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: .5rem;
        font-size: 1.2rem;
    }

    .yr-lane.yr-current {
        border-color: var(--signal-color);
        box-shadow: inset 0 3px 0 var(--signal-color);
    }

    .yr-lane.yr-none,
    .yr-lane.yr-past {
        background: #ececec;
    }

    .yr-lane.empty {
        flex-basis: 10rem;
    }

    .yr-lane-head {
        margin-bottom: .5rem;
        min-height: 3.2rem;
    }

    .yr-lane-head b {
        font-size: 1.1em;
        margin-right: .3rem;
    }

    .yr-lane-types .yr-type {
        margin-right: .15rem;
    }

    /* the whole column takes drops, also when empty */
    .yr-cards {
        flex: 1;
        min-height: 6rem;
    }

    .yr-card {
        background: white;
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--type-color);
        border-radius: var(--border-radius);
        padding: .4rem .5rem;
        margin-bottom: .4rem;
        cursor: grab;
    }

    .yr-card.yr-locked {
        cursor: default;
    }

    .yr-card.status-on_hold {
        background: #f1f1f1;
    }

    .yr-card.status-published,
    .yr-card.status-rejected,
    .yr-card.status-withdrawn {
        opacity: .75;
    }

    .yr-card.yr-saving {
        opacity: .5;
        pointer-events: none;
    }

    .yr-ghost {
        opacity: .4;
        border-style: dashed;
    }

    .yr-card-top {
        display: flex;
        align-items: center;
        gap: .35rem;
        margin-bottom: .15rem;
    }

    .yr-number {
        white-space: nowrap;
    }

    .yr-type {
        display: inline-block;
        background: var(--type-color);
        color: white;
        border-radius: 3px;
        padding: 0 .3rem;
        font-size: .85em;
        font-weight: 600;
        line-height: 1.5;
    }

    .yr-move-btn {
        margin-left: auto;
        border: 1px solid transparent;
        background: none;
        border-radius: 3px;
        padding: 0 .25rem;
        color: var(--muted-color, #666);
        cursor: pointer;
    }

    .yr-move-btn:hover,
    .yr-move-btn:focus,
    .yr-move-btn[aria-expanded="true"] {
        border-color: var(--border-color);
        color: var(--primary-color);
    }

    .yr-title {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        color: inherit;
    }

    .yr-card .proc-authors {
        color: var(--muted-color, #666);
        font-size: .95em;
    }

    .yr-badges {
        margin-top: .2rem;
    }

    #yr-board [hidden] {
        display: none !important;
    }

    .yr-move-form {
        margin-top: .35rem;
    }
</style>
