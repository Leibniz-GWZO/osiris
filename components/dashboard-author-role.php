
        <div class="box h-full">
            <div class="chart content">
                <h5 class="title text-center"><?= lang('Role of ' . $Settings->get('affiliation') . ' authors', 'Rolle der ' . $Settings->get('affiliation') . '-Autoren') ?></h5>
                <canvas id="chart-authors" style="max-height: 30rem;"></canvas>
            </div>

            <script>
                var ctx = document.getElementById('chart-authors')
                var myChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['<?= lang("First or last author", "Erst- oder Letztautor") ?>', '<?= lang("Middle authors", "Mittelautor") ?>'],
                        datasets: [{
                            label: '# of Scientists',
                            data: [<?= $authors['firstorlast'] ?>, <?= $authors['middle'] ?>],
                            backgroundColor: [
                                '#006EB795',
                                '#83D0F595',
                            ],
                            borderColor: '#464646', //'',
                            borderWidth: 1,
                        }]
                    },
                    plugins: [ChartDataLabels],
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                display: true,
                            },
                            title: {
                                display: false,
                                text: 'Scientists approvation'
                            },
                            datalabels: {
                                color: 'black',
                                // anchor: 'end',
                                // align: 'end',
                                // offset: 10,
                                font: {
                                    size: 20
                                }
                            }
                        },
                    }
                });
            </script>
        </div>
    </div>