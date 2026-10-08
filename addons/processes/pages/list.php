<?php

/**
 * List of cases of an area.
 * @var Processes $P
 * @var array $area
 * @var array $access
 * @var string $view mine|todo|all
 * @var array $cases
 */

$base = ROOTPATH . '/processes/' . e($area['id']);
$tabs = [
    'mine' => [lang('My cases', 'Meine Vorgänge'), $access['mine'] || $access['create']],
    'todo' => [lang('To approve', 'Zur Freigabe'), $access['todo']],
    'all' => [lang('All cases', 'Alle Vorgänge'), true],
];
?>

<h1>
    <i class="ph ph-<?= e($area['icon'] ?? 'files') ?>"></i>
    <?= e(Processes::t($area)) ?>
</h1>

<div class="btn-toolbar mb-10">
    <div class="btn-group">
        <?php foreach ($tabs as $key => [$label, $show]) {
            if (!$show) continue; ?>
            <a class="btn <?= $view == $key ? 'primary' : '' ?>" href="<?= $base ?>?view=<?= $key ?>">
                <?= $label ?>
                <?php if ($key == 'todo' && $P->todoCount($area['id']) > 0) { ?>
                    <span class="badge danger ml-5"><?= $P->todoCount($area['id']) ?></span>
                <?php } ?>
            </a>
        <?php } ?>
    </div>
    <?php if ($access['create']) { ?>
        <a class="btn success ml-auto" href="<?= $base ?>/new">
            <i class="ph ph-plus-circle"></i>
            <?= lang('New case', 'Neuer Vorgang') ?>
        </a>
    <?php } ?>
</div>

<?php if ($view == 'all') { ?>
    <p class="text-muted">
        <?= lang('All cases you are allowed to see, without drafts.', 'Alle Vorgänge, die du sehen darfst, ohne Entwürfe.') ?>
    </p>
<?php } ?>

<table class="table" id="proc-table">
    <thead>
        <tr>
            <th><?= lang('No.', 'Nr.') ?></th>
            <th><?= lang('Title', 'Titel') ?></th>
            <th><?= lang('Type', 'Art') ?></th>
            <th>Status</th>
            <th><?= lang('Current step', 'Aktueller Schritt') ?></th>
            <th><?= lang('Created by', 'Erstellt von') ?></th>
            <th><?= lang('Updated', 'Geändert') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cases as $case) {
            $type = $P->type($case['type']);
            $step = $P->currentStep($case);
        ?>
            <tr>
                <td class="unbreakable"><?= e($case['number'] ?? '–') ?></td>
                <td>
                    <a href="<?= ROOTPATH ?>/processes/view/<?= $case['_id'] ?>"><?= e($P->title($case, $type)) ?></a>
                </td>
                <td><?= e(Processes::t($type, 'name')) ?></td>
                <td data-order="<?= e($case['status']) ?>"><?= Processes::statusBadge($case['status']) ?></td>
                <td><?= $step ? e(Processes::t($step)) : '' ?></td>
                <td><?= e($P->personName($case['created_by'])) ?></td>
                <td data-order="<?= e($case['updated_at']) ?>"><?= date('d.m.Y', strtotime($case['updated_at'])) ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<script>
    $('#proc-table').DataTable({
        order: [
            [6, 'desc']
        ],
        language: {
            emptyTable: '<?= $view == 'todo' ? lang('Nothing to approve.', 'Nichts zur Freigabe.') : lang('No cases yet.', 'Noch keine Vorgänge.') ?>'
        }
    });
</script>
