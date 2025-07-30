var activitiesTable = false,
    publicationTable = false,
    projectsExists = false,
    coauthorsExists = false,
    conceptsExists = false,
    // collaboratorsExists = false,
    collabExists = false,
    personsExists = false,
    wordcloudExists = false;

function navigate(key) {
    console.log(key);
    $('section').hide()
    $('section#' + key).show()

    $('.pills .btn').removeClass('active')
    $('.pills .btn#btn-' + key).addClass('active')

    switch (key) {
        case 'publications':
            if (publicationTable) break;
            publicationTable = $('#publication-table').DataTable({
                "ajax": {
                    "url": ROOTPATH + '/portfolio/unit/' + DEPT + '/publications',
                    dataSrc: 'data'
                },
                "sort": false,
                "pageLength": 6,
                "lengthChange": false,
                "searching": false,
                "pagingType": "numbers",
                columnDefs: [{
                    targets: 0,
                    data: 'icon',
                    className: 'w-50'
                },
                {
                    targets: 1,
                    data: 'html',
                    render: function (data, type, row) {
                        // replace links to activities with links to the activity page
                        data = data.replace(/href='\/activity/g, "href='" + ROOTPATH + "/preview/activity");
                        return data;
                    }
                },
                ],
            });
            // impactfactors('chart-impact', 'chart-impact-canvas', { user: {'$in': USERS} })
            // authorrole('chart-authors', 'chart-authors-canvas', { user: {'$in': USERS} })
            break;

        case 'activities':
            if (activitiesTable) break;
            activitiesTable = $('#activities-table').DataTable({
                "ajax": {
                    "url": ROOTPATH + '/portfolio/unit/' + DEPT + '/activities',
                    dataSrc: 'data'
                },
                "sort": false,
                "pageLength": 6,
                "lengthChange": false,
                "searching": false,
                "pagingType": "numbers",
                columnDefs: [{
                    targets: 0,
                    data: 'icon',
                    className: 'w-50'
                },
                {
                    targets: 1,
                    data: 'html',
                    render: function (data, type, row) {
                        // replace links to activities with links to the activity page
                        data = data.replace(/href='\/activity/g, "href='" + ROOTPATH + "/preview/activity");
                        return data;
                    }
                },
                ],
            });
            break;

        case 'projects':
            if (projectsExists) break;
            projectsExists = true;
            // projectTimeline('#project-timeline', { user: {'$in': USERS} })
            $('#projects-table').DataTable({
                ajax: {
                    url: ROOTPATH + '/portfolio/unit/' + DEPT + '/projects',
                },
                type: 'GET',
                dom: 'frtipP',
                columns: [
                    {
                        data: 'name',
                        render: function (data, type, row) {
                            let teaser = lang(row.teaser_en ?? '', row.teaser_de ?? null);
                            if (teaser.length > 1) {
                                teaser = `<hr><p class="">
                    ${teaser}...
                    <span class="link">Weiterlesen</span>
                  </p>
                    `;
                            }

                            return `<a class="d-block w-full colorless" href="${ROOTPATH}/preview/project/${row.id}">
                  <div style="display: none;">${row.name}</div>
                  <span class="float-right text-primary">${lang(row.type_details.name ?? '', row.type_details.name_de ?? null)}</span>
                  <h5 class="my-0 text-primary">
                      ${lang(row.name, row.name_de ?? null)}
                  </h5>
                  <small class="text-muted">
                      ${lang(row.title, row.title_de ?? null)}
                  </small>
                  <p class="d-flex justify-content-between">
                      <span class="text-secondary">${lang(row.start_date ?? '', row.start_date_de ?? null)} - 31.12.2026</span>

                  </p>
                    ${teaser}
              </a>`;
                        }
                    },
                ]
            });
            collaboratorChart('#collaborators', {
                'dept': DEPT,
            });
            break;

        // case 'coauthors':
        //     if (coauthorsExists) break;
        //     coauthorsExists = true;
        //     coauthorNetwork('#chord', { user: {'$in': USERS} })
        //     break;

        case 'persons':
            if (personsExists) break;
            personsExists = true;
            // console.log(personsExists);
            return $('#user-table').DataTable({
                "ajax": {
                    "url": ROOTPATH + '/portfolio/unit/' + DEPT + '/staff',
                    dataSrc: 'data'
                },
                dom: 'frtipP',
                deferRender: true,
                responsive: true,
                language: {
                    url: lang(null, ROOTPATH + '/js/datatables/de-DE.json')
                },
                columnDefs: [
                    {
                        targets: 0,
                        data: 'img',
                        searchable: false,
                        sortable: false,
                        visible: true
                    },
                    {
                        targets: 1,
                        data: 'displayname',
                        className: 'flex-grow-1',
                        render: function (data, type, row) {
                            return `<div class="w-full">
                  <div style="display: none;">${row.displayname}</div>
                  <h5 class="my-0">
                      <a href="${ROOTPATH}/review/person/651cecd8b3c97f11cc28cc45">
                        ${row.academic_title ?? ''} ${row.displayname}
                      </a>
                  </h5>
                  <small>
                      ${lang(row.position ?? '', row.position_de ?? null)}
                  </small>
              </div>`;
                        }
                    }

                ],
                "order": [
                    [1, 'asc'],
                ],

                paging: true,
                autoWidth: true,
                pageLength: 18,
            });
            break;

        case 'collab':
            if (collabExists) break;
            collabExists = true;
            collabChart('#collab-chart', {
                type: 'publication',
                dept: DEPT,
            })
            break;
        // case 'collaborators':
        //     if (collaboratorsExists) break;
        //     collaboratorsExists = true;
        //     break;

        case 'concepts':
            if (conceptsExists) break;
            conceptsExists = true;
            conceptTooltip()
            break;

        case 'wordcloud':
            if (wordcloudExists) break;
            wordcloudExists = true;
            wordcloud('#wordcloud-chart', { user: { '$in': USERS } })
            break;
        default:
            break;
    }

}

// onload
$(document).ready(function () {
    if ($('#btn-general').length <= 0) {
        navigate('persons')
    }
});


function collaboratorChart(selector, data) {
    $.ajax({
        type: "GET",
        url: ROOTPATH + "/api/dashboard/collaborators",
        dataType: "json",
        data: data,
        success: function (response) {
            if (response.count <= 1) {
                $(selector).hide()
                return
            }
            console.log(response);
            var zoomlvl = 1;
            switch (response.data.scope ?? 'international') {
                case 'local':
                    zoomlvl = 5
                    break;
                case 'national':
                    zoomlvl = 4
                    break;
                case 'continental':
                    zoomlvl = 3
                    break;
                case 'international':
                    zoomlvl = 1
                    break;
                default:
                    break;
            }
            var layout = {
                mapbox: {
                    style: "open-street-map",
                    center: {
                        lat: 52,
                        lon: 10
                    },
                    zoom: zoomlvl
                },
                margin: {
                    r: 0,
                    t: 0,
                    b: 0,
                    l: 0
                },
                hoverinfo: 'text',
                // autosize:true
            };
            var data = {
                type: 'scattermapbox',
                mode: 'markers',
                hoverinfo: 'text',
                lon: [],
                lat: [],
                text: [],
                marker: {
                    size: [],
                    color: []
                }
            }

            response.data.forEach(item => {
                data.marker.size.push(item.count + 10)
                data.marker.color.push(item.color ?? 'rgba(0, 128, 131, 0.7)')
                data.lon.push(item.data.lng)
                data.lat.push(item.data.lat)
                data.text.push(`<b>${item.data.name}</b><br>${item.data.location}`)

            });
            console.log(data);

            Plotly.newPlot('map', [data], layout);
        },
        error: function (response) {
            console.log(response);
        }
    });
}

function collabChart(selector, data) {
    $.ajax({
        type: "GET",
        url: ROOTPATH + "/api/dashboard/department-network",
        data: data,
        dataType: "json",
        success: function (response) {
            console.log(response);
            // if (response.count <= 1) {
            //     $('#collab').hide()
            //     return
            // }
            var matrix = response.data.matrix;
            var data = response.data.labels;

            var labels = [];
            var colors = [];
            data = Object.values(data)
            data.forEach(element => {
                labels.push(element.id);
                colors.push(element.color)
            });


            Chords(selector, matrix, labels, colors, data, links = false, useGradient = true, highlightFirst = false, type = 'publication');
        },
        error: function (response) {
            console.log(response);
        }
    });
}
