<?php

/**
 * Deleted cases, for admins: restore or delete permanently.
 * @var Processes $P
 * @var array $entries
 */
?>

<h1><i class="ph ph-trash"></i> <?= lang('Trash', 'Papierkorb') ?></h1>
<p class="text-muted">
    <?= lang(
        'Deleted cases stay here with their files for ' . Processes::TRASH_DAYS . ' days and are then deleted permanently.',
        'Gelöschte Vorgänge bleiben mit ihren Dateien ' . Processes::TRASH_DAYS . ' Tage hier und werden danach endgültig gelöscht.'
    ) ?>
</p>

<?php if (empty($entries)) { ?>
    <p><?= lang('The trash is empty.', 'Der Papierkorb ist leer.') ?></p>
<?php } else { ?>
    <div style="overflow-x:auto">
        <table class="table">
            <thead>
                <tr>
                    <th><?= lang('Number', 'Nummer') ?></th>
                    <th><?= lang('Title', 'Titel') ?></th>
                    <th><?= lang('Type', 'Art') ?></th>
                    <th><?= lang('Created', 'Angelegt') ?></th>
                    <th><?= lang('Deleted', 'Gelöscht') ?></th>
                    <th><?= lang('Files', 'Dateien') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $t) {
                    $c = DB::doc2Arr($t['case']);
                    $type = $P->type($c['type'] ?? '');
                    $id = strval($t['_id']);
                ?>
                    <tr>
                        <td><?= e($c['number'] ?? lang('Draft', 'Entwurf')) ?></td>
                        <td><?= e($c['title'] ?? '') ?></td>
                        <td><?= $type ? e(Processes::t($type, 'name')) : '<code>' . e($c['type'] ?? '') . '</code>' ?></td>
                        <td><?= e(substr($c['created_at'] ?? '', 0, 16)) ?><br><small class="text-muted"><?= e($c['created_by'] ?? '') ?></small></td>
                        <td><?= e(substr($t['deleted_at'], 0, 16)) ?><br><small class="text-muted"><?= e($t['deleted_by'] ?? '') ?></small></td>
                        <td><?= count(DB::doc2Arr($c['files'] ?? [])) ?></td>
                        <td class="text-nowrap">
                            <form action="<?= ROOTPATH ?>/processes/trash/<?= $id ?>/restore" method="post" class="d-inline">
                                <?= Processes::csrfField() ?>
                                <button class="btn small"><i class="ph ph-arrow-counter-clockwise"></i> <?= lang('Restore', 'Wiederherstellen') ?></button>
                            </form>
                            <form action="<?= ROOTPATH ?>/processes/trash/<?= $id ?>/purge" method="post" class="d-inline" onsubmit="return confirm('<?= lang('Delete permanently with all files? This cannot be undone.', 'Endgültig mit allen Dateien löschen? Das lässt sich nicht rückgängig machen.') ?>')">
                                <?= Processes::csrfField() ?>
                                <button class="btn small danger" title="<?= lang('Delete permanently', 'Endgültig löschen') ?>"><i class="ph ph-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>
