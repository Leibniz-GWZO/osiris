<?php

/**
 * Page to see all projects
 * 
 * This file is part of the OSIRIS package.
 * Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * 
 * @link        /projects
 *
 * @package     OSIRIS
 * @since       1.2.2
 * 
 * @copyright	Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * @author		Julia Koblitz <julia.koblitz@osiris-solutions.de>
 * @license     MIT
 */

//  TODO: rowGroup nach Mittelgeber bzw. status

require_once BASEPATH . "/php/Project.php";
$Project = new Project();

// $Format = new Document(true);
$form = $form ?? array();

function val($index, $default = '')
{
    $val = $GLOBALS['form'][$index] ?? $default;
    if (is_string($val)) {
        return htmlspecialchars($val);
    }
    return $val;
}

$pagetitle = lang('Projects', 'Projekte');
$filter = [];
if (!$Settings->hasPermission('projects.view')) {
    $filter = [
        '$or' => [
            ['persons.user' => $_SESSION['username']],
            ['created_by' => $_SESSION['username']],
            ['contact' => $_SESSION['username']],
            ['supervisor' => $_SESSION['username']],
            ['status' => ['$nin' => ['applied', 'rejected', 'expired']]]
        ]
    ];
    $pagetitle = lang('My projects', 'Meine Projekte');
}

?>

<link rel="stylesheet" href="<?= ROOTPATH ?>/css/projecttable.css">

<style>
    .index {
        /* color: transparent; */
        height: 1rem;
        width: 1rem;
        background-color: transparent;
        border-radius: 50%;
        display: inline-block;
        margin-left: .5rem;
    }

    .index.active {
        background-color: var(--secondary-color);
        box-shadow: 0 0 3px 0.2rem rgba(238, 114, 3, 0.6);
    }

    table.dataTable td.dt-control:before {
        display: inline-block;
        box-sizing: border-box;
        content: "";
        border-top: 5px solid transparent;
        border-left: 10px solid rgba(0, 0, 0, 0.5);
        border-bottom: 5px solid transparent;
        border-right: 0px solid transparent;
    }
</style>

<div class="btn-toolbar float-right">
    <a href="<?= ROOTPATH ?>/visualize/map" class="btn secondary">
        <i class="ph ph-map-trifold"></i>
        <?= lang('Show on map', 'Zeige auf Karte') ?>
    </a>
    <!-- <a href="#<?= ROOTPATH ?>/visualize/projects" class="btn secondary" onclick="todo()">
        <i class="ph ph-chart-line-up"></i>
        <?= lang('Show metrics', 'Zeige Metriken') ?>
    </a> -->
</div>

<h1 class="mt-0">
    <i class="ph ph-tree-structure text-osiris"></i>
    <?= $pagetitle ?>
</h1>


<?php if ($Settings->hasPermission('projects.add')) { ?>
    <a href="<?= ROOTPATH ?>/projects/new" class="mb-10 d-inline-block">
        <i class="ph ph-plus"></i>
        <?= lang('Add new project', 'Neues Projekt anlegen') ?>
    </a>
<?php } ?>


<button class="btn primary float-right" onclick="$('.filter-wrapper').slideToggle()">Filter <i class="ph ph-caret-down"></i></button>


<div class="row row-eq-spacing">
    <div class="col order-last order-sm-first">

        <table class="table cards w-full" id="project-table">
            <thead>
                <th>Project</th>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">
                        <i class="ph ph-spinner-third text-muted"></i>
                        <?= lang('Loading projects', 'Lade Projekte') ?>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>

    <div class="col-3 filter-wrapper">

        <div class="filters content" id="filters">
            <!-- <div class="content" > -->
            <div class="title">Filter</div>

            <!-- <div id="searchpanes"></div> -->

            <div id="active-filters"></div>


            <h6>
                <?= lang('By status', 'Nach Status') ?>
                <a class="float-right" onclick="filterProjects('#filter-status .active', null, 6)"><i class="ph ph-x"></i></a>
            </h6>
            <div class="filter">
                <table id="filter-status" class="table small simple">
                    <tr style="--highlight-color: var(--success-color)">
                        <td> <a onclick="filterProjects(this, 'approved', 6)" class="item text-success"><?= lang('approved', 'bewilligt') ?></a></td>
                    </tr>
                    <tr style="--highlight-color: var(--success-color)">
                        <td> <a onclick="filterProjects(this, 'finished', 6)" class="item text-success"><?= lang('finished', 'abgeschlossen') ?></a></td>
                    </tr>
                    <tr style="--highlight-color: var(--signal-color)">
                        <td> <a onclick="filterProjects(this, 'applied', 6)" class="item text-signal"><?= lang('applied', 'beantragt') ?></a></td>
                    </tr>
                    <tr style="--highlight-color: var(--danger-color)">
                        <td> <a onclick="filterProjects(this, 'rejected', 6)" class="item text-danger"><?= lang('rejected', 'abgelehnt') ?></a></td>
                    </tr>
                </table>
            </div>

            <h6>
                <?= lang('By role', 'Nach Rolle') ?>
                <a class="float-right" onclick="filterProjects('#filter-role .active', null, 4)"><i class="ph ph-x"></i></a>
            </h6>
            <div class="filter">
                <table id="filter-role" class="table small simple">
                    <tr style="--highlight-color: var(--signal-color)">
                        <td>
                            <a onclick="filterProjects(this, 'Coordinator', 4)" class="item colorless"><i class="ph ph-crown text-signal"></i> <?= lang('Coordinator', 'Koordinator') ?></a>
                        </td>
                    </tr>
                    <tr style="--highlight-color: var(--muted-color)">
                        <td>
                            <a onclick="filterProjects(this, 'Partner', 4)" class="item colorless"><i class="ph ph-handshake text-muted"></i> <?= lang('Partner') ?></a>
                        </td>
                    </tr>
                </table>
            </div>

            <h6>
                <?= lang('By organisational unit', 'Nach Organisationseinheit') ?>
                <a class="float-right" onclick="filterProjects('#filter-unit .active', null, 7)"><i class="ph ph-x"></i></a>
            </h6>
            <div class="filter">
                <table id="filter-unit" class="table small simple">
                    <?php foreach ($Departments as $id => $dept) { ?>
                        <tr <?= $Groups->cssVar($id) ?>>
                            <td>
                                <a data-type="<?= $id ?>" onclick="filterProjects(this, '<?= $id ?>', 7)" class="item d-block colorless" id="<?= $id ?>-btn">
                                    <span><?= $dept ?></span>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </div>

            <!-- <h6>
                <?= lang('By time', 'Nach Zeitraum') ?>
                <a class="float-right" onclick="resetTime()"><i class="ph ph-x"></i></a>
            </h6>

            <div class="input-group">
                <div class="input-group-prepend">
                    <label for="filter-from" class="input-group-text w-50"><?= lang('From', 'Von') ?></label>
                </div>
                <input type="date" name="from" id="filter-from" class="form-control">
            </div>
            <div class="input-group mt-10">
                <div class="input-group-prepend">
                    <label for="filter-from" class="input-group-text w-50"><?= lang('To', 'Bis') ?></label>
                </div>
                <input type="date" name="to" id="filter-to" class="form-control">
            </div> -->

            <?php if ($Settings->featureEnabled('topics')) { ?>
                <h6><?= lang('Research Topics', 'Forschungsbereiche') ?></h6>

                <div class="filter">
                    <table id="filter-type" class="table small simple">
                        <?php foreach ($osiris->topics->find([], ['sort' => ['order' => 1]]) as $a) {
                            $id = $a['id'];
                        ?>
                            <tr style="--highlight-color:  <?= $a['color'] ?>;">
                                <td>
                                    <a data-type="<?= $id ?>" onclick="filterProjects(this, '<?= $id ?>', 8)" class="item" id="<?= $id ?>-btn">
                                        <span style="color: var(--highlight-color)">
                                            <?= lang($a['name'], $a['name_en'] ?? null) ?>
                                        </span>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>

                </div>
            <?php } ?>

        </div>
    </div>
</div>


<script src="<?= ROOTPATH ?>/js/datatables/jszip.min.js"></script>
<script src="<?= ROOTPATH ?>/js/datatables/dataTables.buttons.min.js"></script>
<script src="<?= ROOTPATH ?>/js/datatables/buttons.html5.min.js"></script>

<script>
    var dataTable;

    // // Formatting function for row details - modify as you need
    // function format(d) {
    //     // `d` is the original data object for the row
    //     return (
    //         `
    //         <dl>
    //         <dt>Full name:</dt>
    //         <dd>${d.title}</dd>
    //         <dt>Funding numbers:</dt>
    //         <dd>${d.funding_numbers}</dd>
    //         <dt>Partners:</dt>
    //         <dd>${d.collaborators ? d.collaborators.length : 0}</dd>
    //         </dl>
    //         `
    //     );
    // }

    const minEl = document.querySelector('#filter-from');
    const maxEl = document.querySelector('#filter-to');

    const activeFilters = $('#active-filters')
    const headers = [{
            title: lang('Project', 'Projekt'),
            key: 'name'
        },
        {
            title: lang('Type', 'Typ'),
            key: 'type'
        },
        {
            title: lang('Funder', 'Mittelgeber'),
            key: 'funder'
        },
        {
            title: lang('Date range', 'Zeitraum'),
            key: 'date_range'
        },
        {
            title: lang('Role', 'Rolle'),
            key: 'role'
        },
        {
            title: lang('Applicant', 'Antragsteller'),
            key: 'applicant'
        },
        {
            title: lang('Status', 'Status'),
            key: 'status'
        },
        {
            title: lang('Units', 'Einheiten'),
            key: 'unit'
        },
        {
            title: lang('Topics', 'Themen'),
            key: 'topics'
        },
        {
            title: lang('Persons', 'Personen'),
            key: 'persons'
        }
    ]

    function renderType(data) {
        if (data == 'Eigenfinanziert') {
            return `<span class="badge text-signal">
                        <i class="ph ph-piggy-bank"></i>&nbsp;${lang('Self-funded', 'Eigenfinanziert')}
                        </span>`
        }
        if (data == 'Stipendium') {
            return `<span class="badge text-success no-wrap">
                        <i class="ph ph-tip-jar"></i>&nbsp;${lang('Stipendiate', 'Stipendium')}
                        </span>`
        }
        if (data == 'Drittmittel') {
            return `<span class="badge text-danger">
                        <i class="ph ph-hand-coins"></i>&nbsp;${lang('Third-party funded', 'Drittmittel')}
                        </span>`
        }
        if (data == 'Teilprojekt') {
            return `<span class="badge text-danger">
                        <i class="ph ph-hand-coins"></i>&nbsp;${lang('Subproject', 'Teilprojekt')}
                        </span>`
        } else {
            return data;
        }
    }

    function renderFunder(row) {
        if (!row.funder && row.scholarship) return row.scholarship;
        return row.funder;
    }

    function renderRole(data) {
        if (data == 'coordinator') {
            return `<span class="badge text-signal">
        <i class="ph ph-crown"></i>
        ${lang('Coordinator', 'Koordinator')}
        </span>`
        }
        return `<span class="badge text-muted">
        <i class="ph ph-handshake"></i>
        ${lang('Partner')}
        </span>`
    }

    function renderContact(row) {
        if (!row.contact && row.supervisor)
            return `<a href="<?= ROOTPATH ?>/profile/${row.supervisor}">${row.applicant}</a>`;
        if (!row.contact)
            return row.applicant;
        return `<a href="<?= ROOTPATH ?>/profile/${row.contact}">${row.applicant}</a>`
    }

    function renderStatus(data) {
        switch (data) {
            case 'approved':
                return `<span class='badge success'>${lang('approved', 'bewilligt')}</span>`;
            case 'finished':
                return `<span class='badge success'>${lang('finished', 'abgeschlossen')}</span>`;
            case 'applied':
                return `<span class='badge signal'>${lang('applied', 'beantragt')}</span>`;
            case 'rejected':
                return `<span class='badge danger'>${lang('rejected', 'abgelehnt')}</span>`;
            case 'expired':
                return `<span class='badge dark'>${lang('expired', 'abgelaufen')}</span>`;
        }
    }

    function renderTopic(data) {
        let topics = '';
        if (data && data.length > 0) {
            topics = '<span class="float-right topic-icons">'
            data.forEach(function(topic) {
                topics += `<a href="<?= ROOTPATH ?>/topics/view/${topic}" class="topic-icon topic-${topic}"></a> `
            })
            topics += '</span>'
        }
        return topics;
    }

    // function renderPersons(data) {
    //     let persons = '';
    //     // if (data && data.length > 0) {
    //     //     persons = '<span class="float-right">'
    //     //     data.forEach(function(person) {
    //     //         persons += `<a href="<?= ROOTPATH ?>/profile/${person}" class="badge text-muted">${person}</a> `
    //     //     })
    //     //     persons += '</span>'
    //     // }
    //     console.log(data);
    //     return persons;
    // }

    $(document).ready(function() {
        dataTable = new DataTable('#project-table', {
            ajax: {
                url: '<?= ROOTPATH ?>/api/projects',
                // add data to the request
                data: {
                    json: '<?= json_encode($filter) ?>',
                    formatted: true
                },
            },
            type: 'GET',
            deferRender: true,
            responsive: true,
            language: {
                url: lang(null, ROOTPATH + '/js/datatables/de-DE.json')
            },
            buttons: [
                // {
                //     extend: 'copyHtml5',
                //     exportOptions: {
                //         columns: [4]
                //     },
                //     className: 'btn small'
                // },
                {
                    extend: 'excelHtml5',
                    exportOptions: {
                        columns: [0, 4, 5, 6]
                    },
                    className: 'btn small',
                    title: function() {
                        var filters = []
                        activeFilters.find('.badge').find('span').each(function(i, el) {
                            filters.push(el.innerHTML)
                        })
                        console.log(filters);
                        if (filters.length == 0) return "OSIRIS All Projects";
                        return 'OSIRIS Projects ' + filters.join('_')
                    }
                },
                {
                    extend: 'csvHtml5',
                    exportOptions: {
                        // columns: ':visible'
                        columns: [0, 5, 6]
                    },
                    className: 'btn small',
                    title: function() {
                        var filters = []
                        activeFilters.find('.badge').find('span').each(function(i, el) {
                            filters.push(el.innerHTML)
                        })
                        console.log(filters);
                        if (filters.length == 0) return "OSIRIS All Projects";
                        return 'OSIRIS Projects ' + filters.join('_')
                    }
                },
            ],
            dom: 'fBrtip',
            columnDefs: [{
                    target: 0,
                    data: 'name',
                    render: function(data, type, row) {
                        return `
                        ${renderTopic(row.topics)}
                        <div class="d-flex flex-column h-full">
                        <h4 class="m-0">
                            <a href="<?= ROOTPATH ?>/projects/view/${row.id}">${data}</a>
                        </h4>
                       
                        <div class="flex-grow-1">
                         <p class="text-muted mt-0">${row.date_range}</p>
                        
                        ${row.persons.join(', ')}

                        </div>
                        <hr />
                        
                        <div class="d-flex justify-content-between">
                            ${renderType(row.type)}
                            ${renderStatus(row.status)}
                        </div>
                    </div>
                        `
                        // ${renderFunder(row)}
                        // ${renderContact(row)}
                        // ${renderRole(row.role)}
                    }
                },
                {
                    target: 1,
                    data: 'type',
                    searchable: true,
                    visible: false
                },
                {
                    target: 2,
                    data: 'funder',
                    searchable: true,
                    visible: false
                },
                {
                    target: 3,
                    data: 'date_range',
                    searchable: true,
                    visible: false
                },
                {
                    target: 4,
                    data: 'role',
                    searchable: true,
                    visible: false
                },
                {
                    target: 5,
                    data: 'applicant',
                    searchable: true,
                    visible: false
                },
                {
                    target: 6,
                    data: 'status',
                    searchable: true,
                    visible: false
                },
                {
                    target: 7,
                    data: 'units',
                    searchable: true,
                    visible: false
                },
                {
                    target: 8,
                    data: 'topics',
                    searchable: true,
                    visible: false
                },
            ],
            order: [
                [0, 'desc']
            ],
            paging: true,
            autoWidth: true,
            pageLength: 9,
        });

        // $('#project-table_wrapper').prepend($('.filters'))


        var initializing = true;
        dataTable.on('init', function() {

            var hash = readHash();
            console.log(hash);
            if (hash.status !== undefined) {
                filterProjects(document.getElementById(hash.status + '-btn'), hash.status, 6)
            }
            if (hash.unit !== undefined) {
                filterProjects(document.getElementById(hash.unit + '-btn'), hash.unit, 7)
            }
            if (hash.role !== undefined) {
                filterProjects(document.getElementById(hash.role + '-btn'), hash.role, 4)
            }
            if (hash.topics !== undefined) {
                filterProjects(document.getElementById(hash.topics + '-btn'), hash.topics, 8)
            }

            if (hash.search !== undefined) {
                dataTable.search(hash.search).draw();
            }
            if (hash.page !== undefined) {
                dataTable.page(parseInt(hash.page) - 1).draw('page');
            }
            initializing = false;
        });


        dataTable.on('draw', function(e, settings) {
            if (initializing) return;
            var info = dataTable.page.info();
            console.log(settings.oPreviousSearch.sSearch);
            writeHash({
                page: info.page + 1,
                search: settings.oPreviousSearch.sSearch
            })
        });

    });


    function filterProjects(btn, activity = null, column = 1) {
        var tr = $(btn).closest('tr')
        var table = tr.closest('table')
        $('#filter-' + column).remove()
        const field = headers[column]
        const hash = {}
        hash[field.key] = activity

        if (tr.hasClass('active') || activity === null) {
            hash[field.key] = null
            table.find('.active').removeClass('active')
            dataTable.columns(column).search("", true, false, true).draw();

        } else {

            table.find('.active').removeClass('active')
            tr.addClass('active')
            dataTable.columns(column).search(activity, true, false, true).draw();
            // indicator
            const filterBtn = $('<span class="badge" id="filter-' + column + '">')
            filterBtn.html(`<b>${field.title}:</b> <span>${activity}</span>`)
            const a = $('<a>')
            a.html('&times;')
            a.on('click', function() {
                filterProjects(btn, null, column);
            })
            filterBtn.append(a)
            activeFilters.append(filterBtn)
        }
        writeHash(hash)

    }
</script>