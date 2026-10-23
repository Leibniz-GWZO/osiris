<?php

/**
 * List of tickets
 *
 * This file is part of the OSIRIS package.
 *
 * @link        /tickets
 *
 * @package     OSIRIS
 * @license     MIT
 *
 * @var Tickets $T
 * @var array $views the tabs the user may see
 * @var string $view mine|open|assigned|all|public
 * @var array $tickets
 */
$manage = $T->canManage();
$tabs = [
    'open' => lang('Open tickets', 'Offene Tickets'),
    'assigned' => lang('Assigned to me', 'Mir zugewiesen'),
    'all' => lang('All tickets', 'Alle Tickets'),
    'mine' => lang('My tickets', 'Meine Tickets'),
    'public' => lang('Tickets of others', 'Tickets anderer'),
];
$tabs = array_intersect_key($tabs, array_flip($views));
?>

<h1>
    <i class="ph-duotone ph-chat-circle-dots"></i>
    <?= count($tabs) > 1 ? lang('Tickets', 'Tickets') : lang('My tickets', 'Meine Tickets') ?>
</h1>

<div class="btn-toolbar mb-10">
    <?php if (count($tabs) > 1) { ?>
        <div class="btn-group">
            <?php foreach ($tabs as $key => $label) { ?>
                <a class="btn <?= $view == $key ? 'primary' : '' ?>" href="<?= ROOTPATH ?>/tickets?view=<?= $key ?>">
                    <?= $label ?>
                    <?php if ($key == 'open' && $T->newCount() > 0) { ?>
                        <span class="badge signal ml-5" title="<?= lang('New', 'Neu') ?>"><?= $T->newCount() ?></span>
                    <?php } ?>
                </a>
            <?php } ?>
        </div>
    <?php } ?>
    <a class="btn success ml-auto" href="<?= ROOTPATH ?>/tickets/new">
        <i class="ph ph-plus-circle"></i>
        <?= lang('New ticket', 'Neues Ticket') ?>
    </a>
</div>

<?php if ($view == 'mine' && !$manage) { ?>
    <p class="text-muted">
        <?= lang(
            'Here you find the tickets you have submitted to the OSIRIS administrators or joined, with their current status.',
            'Hier findest du die Tickets, die du an die OSIRIS-Administration geschickt oder denen du dich angeschlossen hast, mit ihrem aktuellen Stand.'
        ) ?>
    </p>
<?php } else if ($view == 'public') { ?>
    <p class="text-muted">
        <?= lang(
            'Tickets that others have made visible. If you have the same concern, open the ticket and click "Affects me too".',
            'Tickets, die andere sichtbar gemacht haben. Wenn du dasselbe Anliegen hast, öffne das Ticket und klicke auf „Betrifft mich auch“.'
        ) ?>
    </p>
<?php } ?>

<table class="table" id="ticket-table">
    <thead>
        <tr>
            <th><?= lang('No.', 'Nr.') ?></th>
            <th><?= lang('Title', 'Titel') ?></th>
            <th><?= lang('Category', 'Kategorie') ?></th>
            <th>Status</th>
            <th title="<?= lang('Reporter and everyone who joined', 'Meldende Person und alle, die sich angeschlossen haben') ?>"><?= lang('Affected', 'Betroffen') ?></th>
            <?php if ($manage) { ?>
                <th><?= lang('Priority', 'Priorität') ?></th>
                <th><?= lang('Submitted by', 'Eingereicht von') ?></th>
                <th><?= lang('Assigned to', 'Zugewiesen an') ?></th>
            <?php } ?>
            <th><?= lang('Updated', 'Geändert') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $priorityOrder = ['high' => 0, 'normal' => 1, 'low' => 2];
        foreach ($tickets as $t) { ?>
            <tr>
                <td data-order="<?= intval($t['number']) ?>">#<?= intval($t['number']) ?></td>
                <td>
                    <a href="<?= ROOTPATH ?>/tickets/view/<?= $t['_id'] ?>"><?= e($t['title']) ?></a>
                </td>
                <td><?= Tickets::categoryBadge($t['category']) ?></td>
                <td data-order="<?= e($t['status']) ?>"><?= Tickets::statusBadge($t['status']) ?></td>
                <td><i class="ph ph-users text-muted"></i> <?= 1 + count(DB::doc2Arr($t['followers'] ?? [])) ?></td>
                <?php if ($manage) { ?>
                    <td data-order="<?= $priorityOrder[$t['priority'] ?? 'normal'] ?? 1 ?>">
                        <?php if (($t['priority'] ?? 'normal') == 'high') { ?>
                            <span class="text-danger"><i class="ph ph-arrow-fat-up"></i> <?= Tickets::priorities()['high'] ?></span>
                        <?php } else { ?>
                            <?= Tickets::priorities()[$t['priority'] ?? 'normal'] ?? '' ?>
                        <?php } ?>
                    </td>
                    <td><?= e($DB->getNameFromId($t['created_by'])) ?></td>
                    <td><?= empty($t['assigned_to']) ? '<span class="text-muted">–</span>' : e($DB->getNameFromId($t['assigned_to'])) ?></td>
                <?php } ?>
                <td data-order="<?= e($t['updated_at']) ?>"><?= date('d.m.Y', strtotime($t['updated_at'])) ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<script>
    $('#ticket-table').DataTable({
        order: [
            [<?= $manage ? 8 : 5 ?>, 'desc']
        ],
        language: {
            emptyTable: '<?= lang('No tickets.', 'Keine Tickets.') ?>'
        }
    });
</script>
