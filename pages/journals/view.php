<?php

/**
 * Page to see a journal
 * 
 * This file is part of the OSIRIS package.
 * Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * 
 * @link        /journal/view/<journal_id>
 *
 * @package     OSIRIS
 * @since       1.0.0
 * 
 * @copyright	Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * @author		Julia Koblitz <julia.koblitz@osiris-solutions.de>
 * @license     MIT
 */
?>

<script src="<?= ROOTPATH ?>/js/chart.min.js"></script>
<script src="<?= ROOTPATH ?>/js/datatables/jquery.dataTables.naturalsort.js"></script>


<?php if ($Settings->hasPermission('journals.edit')) { ?>
    <a href="<?= ROOTPATH ?>/journal/edit/<?= $id ?>" class="btn osiris float-right"><?= lang('Edit Journal', 'Journal bearbeiten') ?></a>
<?php } ?>


<h2 class="mt-0">
    <i class="ph ph-stack text-primary"></i>
    <?= $data['journal'] ?>
</h2>


<table class="table" id="result-table">
    <tr>
        <td>ID</td>
        <td><?= $data['_id'] ?></td>
    </tr>
    <tr>
        <td>Journal</td>
        <td><?= $data['journal'] ?></td>
    </tr>
    <tr>
        <td><?= lang('Abbreviated', 'Abgekürzt') ?></td>
        <td><?= $data['abbr'] ?></td>
    </tr>
    <tr>
        <td>Publisher</td>
        <td><?= $data['publisher'] ?? '' ?></td>
    </tr>
    <tr>
        <td>ISSN</td>
        <td><?= implode('<br>', DB::doc2Arr($data['issn'])) ?></td>
    </tr>
    <tr>
        <td>Open Access</td>
        <td>
            <?php
            if (!($data['oa'] ?? false)) {
                echo lang('No', 'Nein');
            } elseif ($data['oa'] > 1900) {
                echo lang('since ', 'seit ') . $data['oa'];
            } else {
                echo lang('Yes', 'Ja');
            }
            ?>
        </td>
    </tr>
    <?php if (isset($data['wos'])) { ?>
        <tr>
            <td>Web of Science Links</td>
            <td>
                <?php foreach ($data['wos']['links'] as $link) { ?>
                    <a href="<?= $link['url'] ?>" target="_blank" rel="noopener noreferrer" class="badge secondary"><?= $link['type'] ?></a>
                <?php } ?>
            </td>
        </tr>
    <?php } ?>
</table>

<h3>
    <?= lang('Publications in this journal', 'Publikationen in diesem Journal') ?>
</h3>

<!-- <canvas id="spark"></canvas> -->

<table class="table" id="publication-table">
    <thead>
        <th><?=lang('Year', 'Jahr')?></th>
        <th><?=lang('Publication', 'Publikation')?></th>
        <th>Link</th>
    </thead>
    <tbody>
    </tbody>
</table>
<script>
    var dataTable;

    $(document).ready(function() {
        $('#publication-table').DataTable({
            ajax: {
                "url": ROOTPATH + '/api/activities',
                "data": {
                    "filter": {
                        journal_id: '<?= $id ?>',
                        type: 'publication'
                    },
                    formatted: true
                }
            },
            language: {
                "zeroRecords": "No matching records found",
                "emptyTable": lang('No publications available for this journal.', 'Für dieses Journal sind noch keine Publikationen verfügbar.'),
            },
            "pageLength": 5,
            columnDefs: [
                {
                    targets: 0,
                    data: 'year'
                },
                {
                    targets: 1,
                    data: 'activity'
                },
                {
                    "targets": 2,
                    "data": "name",
                    "render": function(data, type, full, meta) {
                        return `<a href="${ROOTPATH}/activities/view/${full.id}"><i class="ph ph-arrow-fat-line-right"></a>`;
                    }
                },
            ],
            "order": [
                [0, 'desc'],
            ],
            <?php if (isset($_GET['q'])) { ?> "oSearch": {
                    "sSearch": "<?= $_GET['q'] ?>"
                }
            <?php } ?>
        });

        // spark('spark', {
        //     journal_id: '<?= $id ?>',
        //     type: 'publication'
        // });
    });
</script>


<h3>
    <?= lang('Peer-Reviews & Editorial board memberships', 'Peer-Reviews & Mitglieder des Editorial Board') ?>
</h3>

<table class="table" id="review-table">
    <thead>
        <th>Name</th>
        <th><?= lang('Reviewer count', 'Anzahl Reviews') ?></th>
        <th><?= lang('Editor activity', 'Editoren-Tätigkeit') ?></th>
    </thead>
    <tbody>
    </tbody>
</table>
<script>
    $(document).ready(function() {
        $('#review-table').DataTable({
            ajax: {
                "url": ROOTPATH + '/api/reviews',
                "data": {
                    "filter": {
                        journal_id: '<?= $id ?>'
                    }
                }
            },
            language: {
                "zeroRecords": "No matching records found",
                "emptyTable": lang('No reviews/editorials available for this journal.', 'Für dieses Journal sind noch keine Reviews/Editorials verfügbar.'),
            },
            "pageLength": 5,
            columnDefs: [{
                    "targets": 2,
                    "data": "Editor",
                    "render": function(data, type, full, meta) {
                        var res = []
                        full.Editorials.forEach(el => {
                            res.push(`${el.date} (${el.details == '' ? 'Editor' : el.details})`)
                        });
                        return res.join('<br>');
                    }
                },
                {
                    targets: 1,
                    data: 'Reviewer'
                },
                {
                    "targets": 0,
                    "data": "Name",
                    "render": function(data, type, full, meta) {
                        return `<a href="${ROOTPATH}/profile/${full.User}">${data}</a>`;
                    }
                },
            ],
            "order": [
                [1, 'desc'],
            ],
        });
    });
</script>

<h3><?= lang('Catergories', 'Kategorien') ?></h3>
<?php
$categories = $data['categories'] ?? [];
if (empty($categories)) {
    echo '<p>' . lang('No categories available.', 'Keine Kategorien verfügbar.') . '</p>';
} else {
    echo '<ul>';
    foreach ($categories as $cat) { ?>
        <li>
            <?= $cat['name'] ?>
        </li>
<?php
    }
    echo '</ul>';
}
?>

<?php if ($Settings->hasPermission('journals.edit')) { ?>
    <form action="<?= ROOTPATH ?>/crud/journal/update-metrics/<?= $id ?>" method="post">
        <button class="btn primary float-md-right"><i class="ph ph-arrows-clockwise"></i> <?= lang('Update Metrics', 'Metriken aktualisieren') ?></button>
    </form>
<?php } ?>
<h3><?= lang('Metrics', 'Metriken') ?></h3>

<!-- crud/journal/update-metrics/ -->

<?php
$metrics = DB::doc2Arr($data['metrics'] ?? array());

if (empty($metrics)) {
    echo '<p>' . lang('No metrics available.', 'Keine Metriken verfügbar.') . '</p>';
} else { ?>
    <table class="table small">
        <thead>
            <th><?= lang('Year', 'Jahr') ?></th>
            <th>SJR</th>
            <th>IF (2Y)</th>
            <th>IF (3Y)</th>
            <th><?= lang('Best Quartile', 'Bestes Quartil') ?></th>
        </thead>
        <tbody>
            <?php
            foreach ($metrics as $metric) {
                echo '<tr>';
                echo '<th>' . $metric['year'] . '</th>';
                echo '<td>' . $metric['sjr'] . '</td>';
                echo '<td>' . $metric['if_2y'] . '</td>';
                echo '<td>' . $metric['if_3y'] . '</td>';
                echo '<td>';
                if ($metric['quartile']) {
                    echo '<span class="quartile ' . $metric['quartile'] . '">' . $metric['quartile'] . '</span>';
                }
                echo'</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
<?php } ?>


<h3><?= lang('Impact factors', 'Impact-Faktoren') ?></h3>
<?php
$impacts = $data['impact'] ?? array();
if ($impacts instanceof MongoDB\Model\BSONArray) {
    $impacts = DB::doc2Arr($impacts);
}
?>

<div class="box">
    <div class="content">

        <?php if ($Settings->hasPermission('journals.edit')) { ?>
            <div class="dropdown with-arrow float-right mb-20">
                <button class="btn osiris" data-toggle="dropdown" type="button" id="dropdown-2" aria-haspopup="true" aria-expanded="false">
                    <?= lang('Add IF', 'Füge IF hinzu') ?> <i class="ph ph-fill ph-angle-down ml-5" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdown-2">
                    <div class="content">
                        <form action="<?= ROOTPATH ?>/crud/journal/update/<?= $id ?>" method="post">
                            <input type="hidden" class="hidden" name="redirect" value="<?= $url ?? $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?>">
                            <div class="form-group">
                                <label for="year"><?= lang('Year', 'Jahr') ?></label>
                                <input type="number" min="1970" max="2050" step="1" class="form-control" name="values[year]" id="year" value="<?= CURRENTYEAR - 1 ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="if"><?= lang('Impact') ?></label>
                                <input type="number" min="0" max="300" step="0.001" class="form-control" name="values[if]" id="if">
                            </div>
                            <button class="btn block"><i class="ph ph-check"></i> <?= lang('Add', 'Hinzuf.') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php } ?>


        <?php
        if (!empty($impacts)) {

            sort($impacts);
            $years = array_column((array) $impacts, 'year');
        ?>
            <canvas id="chart-if" style="max-height: 400px;"></canvas>

            <script>
                var barChartConfig = {
                    type: 'bar',
                    data: [],
                    options: {
                        plugins: {
                            title: {
                                display: false,
                                text: 'Chart'
                            },
                            legend: {
                                display: false,
                            }
                        },
                        responsive: true,
                        scales: {
                            x: {
                                stacked: true,
                            },
                            y: {
                                stacked: true,
                            }
                        }
                    },

                };
                var ctx = document.getElementById('chart-if')
                var data = Object.assign({}, barChartConfig)
                var raw_data = Object.values(<?= json_encode($impacts) ?>);
                console.log(raw_data);
                data.data = {
                    labels: <?= json_encode($years) ?>,
                    datasets: [{
                        label: 'Impact factor',
                        data: raw_data,
                        parsing: {
                            yAxisKey: 'impact',
                            xAxisKey: 'year'
                        },
                        backgroundColor: 'rgba(236, 175, 0, 0.7)',
                        borderColor: 'rgba(236, 175, 0, 1)',
                        borderWidth: 3
                    }, ],
                }


                console.log(data);
                var myChart = new Chart(ctx, data);
            </script>
        <?php } else { ?>
            <p><?= lang('No impact factors available.', 'Keine Impact Faktoren verfügbar.') ?></p>
        <?php } ?>


    </div>
</div>

<?php

if (isset($_GET['verbose'])) {
    dump($data, true);
}
?>