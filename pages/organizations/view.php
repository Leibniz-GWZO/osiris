<?php

/**
 * The detail view of an organization
 * Created in cooperation with DSMZ
 * 
 * This file is part of the OSIRIS package.
 * Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 *
 * @package     OSIRIS
 * @since       1.4.1
 * 
 * @copyright	Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * @author		Julia Koblitz <julia.koblitz@osiris-solutions.de>
 * @license     MIT
 */
include_once BASEPATH . "/php/Organization.php";
include_once BASEPATH . "/php/Project.php";

$Project = new Project();

$edit_perm = ($organization['created_by'] == $_SESSION['username'] || $Settings->hasPermission('organizations.edit'));

?>

<div class="organization">

    <h1 class="title">
        <?= lang($organization['name'], $organization['name_de'] ?? null) ?>
    </h1>

    <div class="btn-toolbar">
        <?php if ($Settings->hasPermission('organizations.edit')) { ?>
            <a href="<?= ROOTPATH ?>/organizations/edit/<?= $organization['_id'] ?>" class="btn btn-primary">
                <i class="ph ph-edit"></i>
                <?= lang('Edit organization', 'Organisation bearbeiten') ?>
            </a>
        <?php } ?>
    </div>

    <table class="table">
        <tbody>
            <tr>
                <td class="w-50">
                    <?= Organization::getIcon($organization['type'], 'ph-fw ph-2x m-0') ?>
                    <?= ($organization['type']) ?>
                </td>
                <td class="w-50">
                    <?= lang('Location', 'Standort') ?>:
                    <?= $organization['location'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    <?= lang('Latitude') ?>:
                    <?= $organization['lat'] ?? '-' ?>
                </td>
                <td>
                    <?= lang('Longitude') ?>:
                    <?= $organization['lng'] ?? '-' ?>
                </td>
            </tr>
            <?php if (!empty($organization['ror'] ?? '')) { ?>
                <tr>
                    <td>
                        <?= lang('ROR') ?>:
                        <a href="<?= $organization['ror'] ?>" target="_blank" rel="noopener noreferrer">
                            <?= $organization['ror'] ?>
                            <i class="ph ph-arrow-square-out"></i>
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>


<style>
    .module {
        border: none;
        box-shadow: none;
        padding: 0;
        margin: 0;
    }

    a.module:hover {
        box-shadow: none;
        transform: none;
        color: var(--primary-color);
    }
</style>

<?php if ($Settings->featureEnabled('projects')) { ?>
<h2>
    <?= lang('Connected projects', 'Verknüpfte Projekte') ?>
</h2>

<div class="mt-20 w-full">
    <table class="table dataTable responsive" id="projects-table">
        <thead>
            <tr>
                <th class="w-100"><?= lang('Type', 'Typ') ?></th>
                <th><?= lang('Project', 'Projekt') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $projects = $osiris->projects->find(['collaborators.organization' => $organization['_id']])->toArray();
            foreach ($projects as $project) {
                $Project->setProject($project);
            ?>
                <tr>
                    <td>
                        <?= $Project->getType('ph-fw ph-2x m-0') ?>
                    </td>
                    <td>
                        <?= $Project->widgetSmall() ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<script>
    $('#projects-table').DataTable({});
</script>
<?php } ?>



<?php if ($Settings->featureEnabled('infrastructures')) { ?>
<h2>
    <?= lang('Connected infrastructures', 'Verknüpfte Infrastrukturen') ?>
</h2>

<div class="mt-20 w-full">
    <table class="table dataTable responsive" id="infrastructures-table">
        <thead>
            <tr>
                <th><?= lang('Infrastructure', 'Infrastruktur') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $infrastructures = $osiris->infrastructures->find(['collaborators' => $organization['_id']])->toArray();
            foreach ($infrastructures as $infra) {
            ?>
                <tr>
                    <td>
                        <h6 class="m-0">
                            <a href="<?= ROOTPATH ?>/infrastructures/view/<?= $infra['_id'] ?>" class="link">
                                <?= lang($infra['name'], $infra['name_de'] ?? null) ?>
                            </a>
                            <br>
                        </h6>

                        <div class="text-muted mb-5">
                            <?php if (!empty($infra['subtitle'])) { ?>
                                <?= lang($infra['subtitle'], $infra['subtitle_de'] ?? null) ?>
                            <?php } else { ?>
                                <?= get_preview(lang($infra['description'], $infra['description_de'] ?? null), 300) ?>
                            <?php } ?>
                        </div>
                        <div>
                            <?= fromToYear($infra['start_date'], $infra['end_date'] ?? null, true) ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<script>
    $('#infrastructures-table').DataTable({});
</script>
<?php } ?>


<?php if (isset($_GET['verbose'])) {
    dump($organization, true);
} ?>