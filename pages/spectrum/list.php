<?php

/**
 * Page to see all spectrum
 * 
 * This file is part of the OSIRIS package.
 * Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * 
 * @link        /spectrum
 *
 * @package     OSIRIS
 * @since       1.3.0
 * 
 * @copyright	Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * @author		Julia Koblitz <julia.koblitz@osiris-solutions.de>
 * @license     MIT
 */

$level = $_GET['level'] ?? 'topic';
if (!in_array($level, ['domain', 'field', 'subfield', 'topic'])) {
    $level = 'topic';
}
switch ($level) {
    case 'domain':
        $groupField = '$openalex.topics.domain';
        $idField = '$openalex.topics.domain_id';
        break;
    case 'field':
        $groupField = '$openalex.topics.field';
        $idField = '$openalex.topics.field_id';
        break;
    case 'subfield':
        $groupField = '$openalex.topics.subfield';
        $idField = '$openalex.topics.subfield_id';
        break;
    default:
        $groupField = '$openalex.topics.name';
        $idField = '$openalex.topics.id';
}

$match = [
    'openalex.topics' => ['$exists' => true, '$ne' => []],
    'affiliated' => true
];

// Domain filter
if (!empty($_GET['domain'])) {
    $match['openalex.topics.domain_id'] = $_GET['domain'];
}

// Year filter (angenommen: publication_year gespeichert)
if (!empty($_GET['year_from']) || !empty($_GET['year_to'])) {

    $yearFilter = [];

    if (!empty($_GET['year_from'])) {
        $yearFilter['$gte'] = (int)$_GET['year_from'];
    }

    if (!empty($_GET['year_to'])) {
        $yearFilter['$lte'] = (int)$_GET['year_to'];
    }

    $match['year'] = $yearFilter;
}
$aggregation = [
    ['$match' => $match],
    ['$unwind' => '$openalex.topics']
];

if (!empty($_GET['domain'])) {
    $aggregation[] = ['$match' => ['openalex.topics.domain_id' => $_GET['domain']]];
}

$aggregation = array_merge($aggregation, [
    ['$match' => $match],
    ['$unwind' => '$openalex.topics'],
    ['$group' => [
        '_id' => [
            'id' => $idField,
            'name' => $groupField
        ],
        'count' => ['$sum' => 1],
        'avg_score' => ['$avg' => '$openalex.topics.score'],
        'topic' => ['$first' => '$openalex.topics']
    ]],
    ['$sort' => ['count' => -1]],
    ['$project' => [
        '_id' => 0,
        'id' => '$_id.id',
        'name' => '$_id.name',
        'count' => 1,
        'avg_score' => 1,
        'topic' => 1
    ]],
]);

$spectrum = $osiris->activities->aggregate($aggregation)->toArray();

// Determine max count for normalization
$maxCount = 0;
foreach ($spectrum as $s) {
    if ($s['count'] > $maxCount) $maxCount = $s['count'];
}

// Add normalized strength
foreach ($spectrum as &$s) {
    $s['normalized'] = $maxCount > 0 ? $s['count'] / $maxCount : 0;
}
unset($s);
?>

<style>
    .spectrum-bar {
        display: grid;
        grid-template-columns: 2fr 4fr 60px;
        align-items: center;
        margin-bottom: 8px;
    }

    .spectrum-bar .bar {
        background: #eee;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
    }

    .spectrum-bar .fill {
        background: #2E7D5B;
        height: 100%;
    }

    .spectrum-bar .fill.fill-1 {
        background: var(--spectrum-1-color);
    }

    .spectrum-bar .fill.fill-2 {
        background: var(--spectrum-2-color);
    }

    .spectrum-bar .fill.fill-3 {
        background: var(--spectrum-3-color);
    }

    .spectrum-bar .fill.fill-4 {
        background: var(--spectrum-4-color);
    }

    .spectrum-bar .count {
        font-size: 0.9em;
        color: #666;
        margin-left: 1rem;
    }
    .level-buttons .btn {
        text-transform: uppercase;
    }
</style>

<h1>
    <i class="ph-duotone ph-lightbulb" aria-hidden="true"></i>
    <?= lang('Research Spectrum', 'Forschungs-Spektrum') ?>
</h1>

<p class="text-muted">
    <?= lang('Data-driven thematic analysis of scholarly publications based on OpenAlex.', 'Datenbasierte thematische Analyse wissenschaftlicher Publikationen auf Basis von OpenAlex.') ?>
</p>
<form method="get" class="box padded">

    <div class="btn-toolbar level-buttons">
        <input type="submit" name="level" class="btn level-domain <?= $level == 'domain' ? 'active' : '' ?>" value="domain">
        <input type="submit" name="level" class="btn level-field <?= $level == 'field' ? 'active' : '' ?>" value="field">
        <input type="submit" name="level" class="btn level-subfield <?= $level == 'subfield' ? 'active' : '' ?>" value="subfield">
        <input type="submit" name="level" class="btn level-topic <?= $level == 'topic' ? 'active' : '' ?>" value="topic">
    </div>

    <div class="row row-eq-spacing align-items-end">

        <!-- Domain Filter -->
        <div class="col-md-4">
            <label class="form-label"><?= lang('Domain', 'Domain') ?></label>
            <select name="domain" class="form-control">
                <option value=""><?= lang('All domains', 'Alle Domains') ?></option>
                <option value="1" <?= ($_GET['domain'] ?? '') == '1' ? 'selected' : '' ?>>Life Sciences</option>
                <option value="2" <?= ($_GET['domain'] ?? '') == '2' ? 'selected' : '' ?>>Social Sciences</option>
                <option value="3" <?= ($_GET['domain'] ?? '') == '3' ? 'selected' : '' ?>>Physical Sciences</option>
                <option value="4" <?= ($_GET['domain'] ?? '') == '4' ? 'selected' : '' ?>>Health Sciences</option>
            </select>
        </div>

        <!-- Year From -->
        <div class="col-md-3">
            <label class="form-label"><?= lang('From year', 'Von Jahr') ?></label>
            <input type="number" name="year_from" class="form-control"
                value="<?= e($_GET['year_from'] ?? '') ?>">
        </div>

        <!-- Year To -->
        <div class="col-md-3">
            <label class="form-label"><?= lang('To year', 'Bis Jahr') ?></label>
            <input type="number" name="year_to" class="form-control"
                value="<?= e($_GET['year_to'] ?? '') ?>">
        </div>

        <div class="col-md-2">
            <button class="btn primary block">
                <?= lang('Apply filter', 'Filter anwenden') ?>
            </button>
        </div>

    </div>
</form>

<div class="spectrum-chart box padded">
    <?php foreach (array_slice($spectrum, 0, 10) as $s):
        $percent = round($s['normalized'] * 100);
        $name = $s['name'];
    ?>
        <div class="spectrum-bar">
            <a class="label" href="<?= ROOTPATH ?>/spectrum/<?= e($level) ?>/<?= e($s['id']) ?>"><?= e($name) ?></a>
            <div class="bar">
                <div class="fill fill-<?= e($s['topic']['domain_id']) ?>" style="width: <?= $percent ?>%"></div>
            </div>
            <div class="count"><?= $s['count'] ?></div>
        </div>
    <?php endforeach; ?>
</div>
<table class="table dataTable" id="spectrum-table">
    <thead>
        <tr>
            <th><?= lang('Research focus', 'Schwerpunkt') ?></th>
            <th><?= lang('Publications', 'Publikationen') ?></th>
            <th><?= lang('Share', 'Anteil') ?></th>
            <th><?= lang('Relative strength', 'Relative Stärke') ?></th>
            <th><?= lang('Avg. topic score', 'Ø Topic-Score') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $totalPublications = array_sum(array_column($spectrum, 'count'));

        foreach ($spectrum as $s):
            $share = $totalPublications > 0 ? $s['count'] / $totalPublications : 0;
        ?>
            <tr>
                <td><a href="<?= ROOTPATH ?>/spectrum/<?= e($level) ?>/<?= e($s['id']) ?>"><?= e($s['name']) ?></a></td>
                <td><?= $s['count'] ?></td>
                <td><?= round($share * 100, 1) ?> %</td>
                <td><?= round($s['normalized'] * 100) ?> %</td>
                <td><?= round($s['avg_score'] * 100, 1) ?> %</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<script>
    var dataTable;
    $(document).ready(function() {
        dataTable = $('#spectrum-table').DataTable({
            "order": [
                [2, 'desc'],
            ],
        });
    });
</script>