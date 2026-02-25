spectrumTooltipExists = false;

function navigate(key) {
    $('section').hide()
    $('section#' + key).show()

    // $('.pills .btn').removeClass('active')
    // $('.pills .btn#btn-' + key).addClass('active')
    $('#navigation .btn').removeClass('active')
    $('#navigation .btn#btn-' + key).addClass('active')

    switch (key) {
        case 'coauthors':
            coauthors()
            break;

        default:
            break;
    }

    // save as hash
    window.location.hash = 'section-' + key
}

$(document).ready(function () {
    // get hash
    var hash = window.location.hash
    if (hash && hash.includes('#section-')) {
        navigate(hash.replace('#section-', ''))
    }
});


function showCollaboratorChart(collab_type, button){
    // check if chart already exists
    $('.collab-chart').hide();
    var chartContainer = $('#chart-' + collab_type);

    if (button) {
        $('#collab-type-filters .btn').removeClass('active')
        $(button).addClass('active')
    }


    chartContainer.show();
    if (chartContainer.length == 0 || chartContainer.hasClass('plotted')) return;

    $.ajax({
        type: "GET",
        url: ROOTPATH + "/api/dashboard/activity-" + collab_type,
        data: {
            activity: ACTIVITY_ID
        },
        dataType: "json",
        success: function (response) {
            console.log(response);
            var container = document.getElementById('chart-' + collab_type)
            if (response.count == 0) {
                container.classList.add('hidden')
                return;
            }
            var data = response.data;
            var ctx = document.getElementById('chart-' + collab_type + '-canvas')
            var myChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: '# of Scientists',
                        data: data.y,
                        backgroundColor: data.colors,
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
                            font: {
                                size: 20
                            }
                        }
                    },
                }
            });
            chartContainer.addClass('plotted');
        },
        error: function (response) {
            console.log(response);
        }
    });
}


function coauthors() {
    showCollaboratorChart('collaborators');
}
