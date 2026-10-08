<?php

/**
 * Catalog of the process types of an area that the user may create.
 * @var Processes $P
 * @var array $area
 * @var array $types
 */

$groups = [];
foreach ($area['groups'] ?? [] as $g) {
    $groups[$g['id']] = ['group' => $g, 'types' => []];
}
foreach ($types as $t) {
    $gid = $t['group'] ?? '_other';
    if (!isset($groups[$gid])) $groups[$gid] = ['group' => ['id' => $gid, 'label' => 'Weitere', 'label_en' => 'Other'], 'types' => []];
    $groups[$gid]['types'][] = $t;
}
?>

<h1>
    <i class="ph ph-<?= e($area['icon'] ?? 'files') ?>"></i>
    <?= e(Processes::t($area)) ?>: <?= lang('New case', 'Neuer Vorgang') ?>
</h1>

<?php if (!empty($area['intro'])) { ?>
    <p class="text-muted"><?= e(Processes::t($area, 'intro')) ?></p>
<?php } ?>

<?php if (empty($types)) { ?>
    <div class="alert">
        <?= lang('There are no forms that you can use here.', 'Hier gibt es keine Formulare, die du nutzen kannst.') ?>
    </div>
<?php } ?>

<?php foreach ($groups as $g) {
    if (empty($g['types'])) continue; ?>
    <h3 class="mt-20"><?= e(Processes::t($g['group'])) ?></h3>
    <div class="row row-eq-spacing">
        <?php foreach ($g['types'] as $t) { ?>
            <div class="col-md-6 col-lg-4">
                <a class="box padded d-block h-full proc-tile" href="<?= ROOTPATH ?>/processes/<?= e($area['id']) ?>/new/<?= e($t['id']) ?>">
                    <h5 class="title mt-0">
                        <i class="ph ph-<?= e($t['icon'] ?? 'file-text') ?>"></i>
                        <?= e(Processes::t($t, 'name')) ?>
                    </h5>
                    <?php if (!empty($t['description'])) { ?>
                        <p class="text-muted mb-0"><?= e(Processes::t($t, 'description')) ?></p>
                    <?php } ?>
                </a>
            </div>
        <?php } ?>
    </div>
<?php } ?>

<style>
    .proc-tile {
        color: inherit;
        text-decoration: none;
    }

    .proc-tile:hover {
        border-color: var(--primary-color);
        text-decoration: none;
    }
</style>
