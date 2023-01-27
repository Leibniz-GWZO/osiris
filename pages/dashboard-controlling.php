
<div class="content">
    <h2><?= lang('Overview on the past four quarters', 'Überblick über die letzten vier Quartale') ?></h2>

</div>

<div class="row row-eq-spacing mb-0">

    <?php
    foreach ($quarters as $q => $d) {
    ?>
        <div class="col-md-6 col-lg-3">
            <div class="box">
                <div class="chart content h-250">
                    <h5 class="title text-center"><?= $q ?></h5>

                    <canvas id="overview-<?= $q ?>"></canvas>
                    <!-- <div class="text-right mt-5">
                        <button class="btn btn-sm" onclick="loadModal('components/controlling-approved', {q: '<?= $d['quarter'] ?>', y: '<?= $d['year'] ?>'})">
                            <i class="fas fa-search-plus"></i> <?= lang('Activities') ?>
                        </button>
                    </div> -->

                    <script>
                        var ctx = document.getElementById('overview-<?= $q ?>')
                        var raw_data = JSON.parse('<?= json_encode($d['activities']) ?>')
                        console.log(raw_data);
                        var myChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                // labels: ['<?= lang("Approved", "Bestätigt") ?>', '<?= lang("Approval missing", "Bestätigung fehlt") ?>'],
                                labels: Object.keys(raw_data),
                                datasets: [{
                                    data: Object.values(raw_data),
                                    backgroundColor: [
                                        "#006EB795",
                                        "#B61F2995",
                                        "#ECAF0095",
                                        "#1FA13895",
                                        "#00000095",
                                        "#5F272A95",
                                        "#9a499c95",
                                        "#57575695",
                                    ],
                                    borderColor: '#464646', //'',
                                    borderWidth: 1,
                                    borderRadius: 4
                                }, ]
                            },
                            options: {
                                maintainAspectRatio: false,
                                layout: {
                                    padding: {
                                        bottom: 30
                                    }
                                },
                                responsive: true,
                                scales: {
                                    x: {
                                        stacked: true,
                                    },
                                    y: {
                                        stacked: true,
                                        min: 0,
                                        max: <?= $max_quarter_act ?>,
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        display: false,
                                    },
                                    title: {
                                        display: false,
                                    }
                                }
                            }
                        });
                    </script>

                </div>
            </div>
        </div>
    <?php }


    foreach ($quarters as $q => $d) {

        $n_scientists = $osiris->users->count(["is_scientist" => true, "is_active" => true]);
        $n_approved = $osiris->users->count(["is_scientist" => true, "is_active" => true, "approved" => $d['year'] . "Q" . $d['quarter']]);

    ?>
        <div class="col-md-3">
            <div class="box">
                <div class="chart content">
                    <h5 class="title text-center"><?= $q ?></h5>

                    <canvas id="approved-<?= $q ?>"></canvas>
                    <div class="text-right mt-5">
                        <button class="btn btn-sm" onclick="loadModal('components/controlling-approved', {q: '<?= $d['quarter'] ?>', y: '<?= $d['year'] ?>'})">
                            <i class="fas fa-search-plus"></i> <?= lang('Details') ?>
                        </button>
                    </div>

                    <script>
                        var ctx = document.getElementById('approved-<?= $q ?>')
                        var myChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: ['<?= lang("Approved", "Bestätigt") ?>', '<?= lang("Approval missing", "Bestätigung fehlt") ?>'],
                                datasets: [{
                                    label: '# of Scientists',
                                    data: [<?= $n_approved ?>, <?= $n_scientists - $n_approved ?>],
                                    backgroundColor: [
                                        '#ECAF0095',
                                        '#B61F2995',
                                    ],
                                    borderColor: '#464646', //'',
                                    borderWidth: 1,
                                }]
                            },
                            plugins: [ChartDataLabels],
                            options: {
                                responsive: true,
                                plugins: {
                                    datalabels: {
                                        color: 'black',
                                        // anchor: 'end',
                                        // align: 'end',
                                        // offset: 10,
                                        font: {
                                            size: 20
                                        }
                                    },
                                    legend: {
                                        position: 'bottom',
                                        display: false,
                                    },
                                    title: {
                                        display: false,
                                        text: 'Scientists approvation'
                                    }
                                }
                            }
                        });
                    </script>

                </div>
            </div>
        </div>
    <?php }
    ?>
</div>


<?php

$Format = new Format(true);
?>

<h2><?=lang('Newly added activities', 'Zuletzt hinzugefügte Aktivitäten')?></h2>
<div class="mt-20">

    <table class="table dataTable" id="activity-table">
        <thead>
            <tr>
                <th><?= lang('Added', 'Hinzugefügt') ?></th>
                <th><?= lang('By', 'Von') ?></th>
                <th><?= lang('Type', 'Typ') ?></th>
                <th><?= lang('Activity', 'Aktivität') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $filter = ['created' => ['$exists' => true]];
            $options = ['sort' => ["created" => -1]];
            $cursor = $osiris->activities->find($filter, $options);

            if (empty($cursor)) {
                echo "<tr class='row-danger'><td colspan='3'>" . lang('No activities found.', 'Keine Publikationen gefunden.') . "</td></tr>";
            } else foreach ($cursor as $i => $doc) {
                $id = $doc['_id'];
                if ($i >= 30) break;
            ?>
                <tr class="" id="<?= $id ?>">
                    <td class="">
                        <span class="hidden"><?= $doc['created'] ?></span>
                        <?php
                        $date = date_create($doc['created']);
                        echo date_format($date, "d.m.Y");
                        ?>
                    </td>
                    <td>
                        <a href="<?= ROOTPATH ?>/profile/<?= $doc['created_by'] ?? '' ?>">
                            <?= $doc['created_by'] ?? '' ?>
                        </a>
                    </td>
                    <td class="text-center ">
                        <?php
                        echo activity_icon($doc);
                        ?>
                    </td>
                    <td>
                        <?php echo $Format->format($doc); ?>
                    </td>
                    <td class="unbreakable">
                        <a class="btn btn-link btn-square" href="<?= ROOTPATH . "/activities/view/" . $id ?>">
                            <i class="icon-activity-search"></i>
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>

    </table>
</div>

<script src="<?= ROOTPATH ?>/js/jquery.dataTables.min.js"></script>

<script>
    $.extend($.fn.DataTable.ext.classes, {
        sPaging: "pagination mt-10 ",
        sPageFirst: "direction ",
        sPageLast: "direction ",
        sPagePrevious: "direction ",
        sPageNext: "direction ",
        sPageButtonActive: "active ",
        sFilterInput: "form-control form-control-sm d-inline w-auto ml-10 ",
        sLengthSelect: "form-control form-control-sm d-inline w-auto",
        sInfo: "float-right text-muted",
        sLength: "float-right"
    });
    var dataTable;
    $(document).ready(function() {
        dataTable = $('#activity-table').DataTable({
            "order": [
                [0, 'desc'],
            ],
            "pageLength": 5
        });
    });
</script>