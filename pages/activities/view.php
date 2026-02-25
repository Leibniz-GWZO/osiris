<style>
    .tab-box {
        margin-top: -1px;
    }

    ul.authors {
        list-style: none;
        padding: 0;
        font-size: 1.6rem;
    }

    ul.authors>li {
        display: inline-block;
        margin-right: .5rem;
        margin-bottom: .2rem;
    }

    ul.authors>li::after {
        content: ",";
    }

    ul.authors>li:last-child::after {
        content: "";
    }

    ul.authors>li.more-authors {
        font-style: italic;
    }


    .cards {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .card {
        width: 100%;
        margin: 0.5rem 0;
        border: var(--border-width) solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        background: var(--box-bg-color);
        /* display: flex;
        flex-direction: column;
        align-items: center; */
        padding: 1rem 1.4rem;
    }

    .card h5 a {
        color: var(--link-color) !important;
    }

    .card div {
        border: 0;
        box-shadow: none;
        /* width: 100%; */
        /* height: 100%; */
        display: block;
    }

    .card small,
    .card p {
        display: block;
        margin: 0;
    }

    /* two columns on larger screens */
    @media (min-width: 768px) {
        .card {
            width: calc(50% - 0.5rem);
        }
    }


    .identifier {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        margin-right: 1rem;
        border: 1px solid var(--blue-color);
        border-radius: 5px;
        padding: 0 0.5rem 0 0;
        background: white;
        color: var(--blue-color);
    }

    .identifier .label {
        background: var(--blue-color);
        color: white;
        padding: 0.3rem 0.4rem;
        border-radius: 4px;
        border-bottom-right-radius: 0;
        font-size: 1.2rem;
        text-transform: uppercase;
        border-top-right-radius: 0;
    }

    .tabs {
        margin-bottom: 0;
        margin-left: 1rem;
        margin-right: 1rem;
        z-index: 10;
        position: relative;
    }

    .tabs .btn.active {
        border: var(--border-width) solid var(--border-color);
        background-color: white;
        color: var(--primary-color);
        font-weight: bold;
        border-bottom-color: white;
    }

    .tabs .btn {
        height: 3.2rem;
        margin-bottom: 0;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
        border: var(--border-width) solid var(--border-color);
        box-shadow: none !important;
        color: var(--primary-color);
        background-color: var(--grey-color-light);
    }

    .new-pills {
        background-color: white;
        border-top-left-radius: var(--border-radius);
        border-top-right-radius: var(--border-radius);
        display: inline-block;
        position: relative;
        z-index: 8;
        margin-left: 1rem;
        border: var(--border-width) solid var(--border-color);
        border-bottom: none;
        /* box-shadow: var(--box-shadow); */
    }

    .new-pills .btn {
        margin: 0;
        border-radius: 0;
        border: none;
        color: var(--muted-color);
        border-bottom: 3px solid var(--gray-color);
        box-shadow: none !important;
        background-color: transparent;
        height: 4rem;
        line-height: 4rem;
        padding: 0 2rem;
    }

    .new-pills .btn.active,
    .new-pills .btn:active {
        color: var(--primary-color);
        border-color: var(--primary-color) !important;
        font-weight: bold;
    }

    .new-pills .btn:hover {
        color: var(--primary-color);
        background-color: transparent;
        border-bottom-color: var(--primary-color-30);
    }

    .no-borders {
        border: none;
        box-shadow: none;
    }

    .no-borders * {
        border: none !important;
    }

    /* --- Author chips / pills --- */
    .author-name {
        font-weight: bold;
    }

    .author-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 10px;
        align-items: center;
    }

    .author-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 12px;
        line-height: 1.4;
        white-space: nowrap;
        background: var(--gray-100, #f2f2f2);
        color: var(--gray-800, #333);
    }

    /* .author-chip i {
                        font-size: 14px;
                        opacity: 0.8;
                    } */

    .author-chip.success {
        background: var(--success-color-20);
        color: var(--success-color-dark);
    }

    .author-chip.neutral {
        background: #eee;
        color: #555;
    }

    .author-units {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        font-weight: bold;
    }

    .author-unit {
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 100px;
        background: #f7f7f7;
        border: 1px solid #e0e0e0;
        color: #444;
    }

    .author-unit:hover {
        background: #ececec;
        text-decoration: none;
    }

    /* Optional: only show claim on hover (less visual noise) */
    .author-row .claim-action {
        opacity: 0;
        position: absolute;
        right: 0;
        top: .75rem;
        transition: opacity 0.15s ease;
    }

    .author-row td {
        position: relative;
    }

    .author-row:hover .claim-action {
        opacity: 1;
    }

    .table.author-table th,
    .table.author-table td {
        padding: 1rem 0;
    }
</style>

<?php
// check if this is an ongoing activity type
$ongoing = false;
$sws = false;
$supervisorThesis = false;

$typeArr = $Format->typeArr;
$upload_possible = $typeArr['upload'] ?? true;
$subtypeArr = $Format->subtypeArr;
$typeModules = DB::doc2Arr($subtypeArr['modules'] ?? array());
$typeFields = $Modules->getFields();
$fields = array_keys($typeFields);

foreach ($fields as $m) {
    // if (str_ends_with($m, '*')) $m = str_replace('*', '', $m);
    if ($m == 'date-range-ongoing') $ongoing = true;
    if ($m == 'supervisor') $sws = true;
    if ($m == 'supervisor-thesis') $supervisorThesis = true;
}

$projects = [];
if (isset($activity['projects']) && count($activity['projects']) > 0) {
    $projects = $osiris->projects->find(
        ['_id' => ['$in' => $activity['projects']]],
        ['projection' => ['_id' => 1, 'acronym' => 1, 'name' => 1, 'start' => 1, 'end' => 1, 'title' => 1, 'funder' => 1]]
    )->toArray();
}

$guests_involved = boolval($subtypeArr['guests'] ?? false);
$guests = $doc['guests'] ?? [];
// if ($guests_involved)
//     $guests = $osiris->guests->find(['activity' => $id])->toArray();

$edit_perm = ($user_activity || $Settings->hasPermission('activities.edit'));
$tagName = '';
if ($Settings->featureEnabled('tags')) {
    $tagName = $Settings->tagLabel();
}

$connected_activities = $osiris->activitiesConnections->find(
    ['$or' => [['source_id' => $id], ['target_id' => $id]]]
)->toArray();

// Nimm deinen bestehenden User-Kontext
$user_units = DB::doc2Arr($USER['units'] ?? []);
if (!empty($user_units)) {
    $user_units = array_column($user_units, 'unit');
}

$warnings = [];
if ((!isset($doc['editors']) || empty($doc['editors'])) && (!isset($doc['supervisors']) || empty($doc['supervisors']))) {
    $warnings[] = 'no_persons';
}
if (!isset($doc['year']) || empty($doc['year']) || !isset($doc['month']) || empty($doc['month'])) {
    $warnings[] = 'no_date';
}

$documents = $osiris->uploads->find(['type' => 'activities', 'id' => strval($id)])->toArray();

if ($Settings->featureEnabled('quality-workflow', false) && ($user_activity || $Settings->hasPermission('workflows.view'))) {
    include_once BASEPATH . '/pages/activities/activity-workflow.php';
}

$visible_subtypes = $Settings->getActivitiesPortfolio(true);


$departments = [];
if (!empty($doc['units'])) {
    foreach ($doc['units'] as $d) {
        $dept = $Groups->getGroup($d);
        if ($dept['level'] !== 1) continue;
        $departments[$d] = [
            'en' => $dept['name'],
            'de' => $dept['name_de']
        ];
    }
}


$hidden_fields = ['authors', "editors", "supervisors", "semester-select", 'abstract', 'depts', 'projects', 'title'];
$empty_fields = [];
$sections = [];
foreach ($fields as $field_id) {
    if (!array_key_exists($field_id, $typeFields)) {
        $section = 'others';
    } else {
        $section = $Modules->all_modules[$field_id]['section'] ?? '';
    }
    if (empty($section)) continue; // if no section is defined, do not show the field
    if (in_array($field_id, $hidden_fields)) continue;

    if ($field_id == 'teaching-course' && isset($doc['module_id'])) :
        $module = $DB->getConnected('teaching', $doc['module_id']);
        $field = [
            'key_en' => 'Teaching Module',
            'key_de' => 'Lehrveranstaltung',
            'value' => $module['module']
        ];
    elseif ($field_id == 'journal' && isset($doc['journal_id'])) :
        $journal = $DB->getConnected('journal', $doc['journal_id']);
        $field = [
            'key_en' => 'Journal',
            'key_de' => 'Journal',
            'value' => $journal['journal']
        ];
    else :
        $names = $Modules->all_modules[$field_id] ?? [];
        $field = [
            'key_en' => $names['name'] ?? ucfirst($field_id),
            'key_de' => $names['name_de'] ?? ucfirst($field_id),
            'value' => $Format->get_field($field_id)
        ];
    endif;

    if (empty($field['value']) || $field['value'] == '-') {
        $empty_fields[] = $field_id;
        continue;
    }
    $sections[$section][] = $field;
}

$author_keys = [
    "authors",
    "editors",
    "supervisors",
];
$count_authors = 0;
foreach ($author_keys as $k) {
    if (isset($doc[$k]) && is_array($doc[$k])) {
        $count_authors += count($doc[$k]);
    }
}
?>

<script>
    const ACTIVITY_ID = '<?= $id ?>';
    const TYPE = '<?= $doc['type'] ?>';
</script>

<script src="<?= ROOTPATH ?>/js/d3.v4.min.js"></script>

<script src="<?= ROOTPATH ?>/js/chart.min.js"></script>
<script src="<?= ROOTPATH ?>/js/chartjs-plugin-datalabels.min.js"></script>
<script src="<?= ROOTPATH ?>/js/activity.js?v=<?= OSIRIS_BUILD ?>"></script>


<div class="content-container">
    <div class="container-lg">

        <div id="altmetric-container" class="position-absolute" style="top: 1rem; right: 1rem; z-index: 20;">
            <?php if ($Settings->featureEnabled('altmetrics')) {
                $displayAltmetric = true;
                $details = [
                    'data-badge-type' => 'medium-donut',
                    'data-badge-popover' => 'left',
                    'data-link-target' => '_blank'
                ];
                if (isset($doc['doi']) && !empty($doc['doi'])) {
                    $details['data-doi'] = $doc['doi'];
                } elseif (isset($doc['isbn']) && !empty($doc['isbn'])) {
                    $details['data-isbn'] = $doc['isbn'];
                } elseif (isset($doc['pubmed']) && !empty($doc['pubmed'])) {
                    $details['data-pmid'] = $doc['pubmed'];
                } else {
                    $displayAltmetric = false;
                }
                if ($displayAltmetric) {
                    $detailsAttr = '';
                    foreach ($details as $k => $v) {
                        $detailsAttr .= " $k='$v' ";
                    }
            ?>
                    <script type='text/javascript' src='https://embed.altmetric.com/assets/embed.js'></script>
                    <div class='altmetric-embed' <?= $detailsAttr ?>></div>
            <?php }
            } ?>
        </div>



        <div class="btn-toolbar mb-20 ml-10">
            <?php if (($edit_perm) && (!$locked || $Settings->hasPermission('activities.edit-locked'))) { ?>
                <a href="<?= ROOTPATH ?>/activities/edit/<?= $id ?>" class="btn secondary filled">
                    <i class="ph ph-pencil-simple-line mr-5"></i>
                    <?= lang('Edit', 'Bearbeiten') ?>
                </a>
            <?php } ?>
            <?php if ($Settings->featureEnabled('portal')) { ?>
                <a class="btn secondary outline" href="<?= ROOTPATH ?>/preview/activity/<?= $id ?>">
                    <i class="ph ph-eye mr-5"></i>
                    <?= lang('Preview', 'Vorschau') ?>
                </a>
            <?php } ?>

            <?php if ($user_activity && $locked && empty($doc['end'] ?? null) && $ongoing) { ?>
                <div class="dropdown">
                    <button class="btn secondary outline" data-toggle="dropdown" type="button" id="update-end-date" aria-haspopup="true" aria-expanded="false">
                        <i class="ph ph-calendar-check"></i>
                        <?= lang('End activity', 'Beenden') ?> <i class="ph ph-caret-down ml-5" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-center w-200" aria-labelledby="update-end-date">
                        <form action="<?= ROOTPATH . "/crud/activities/update/" . $id ?>" method="POST" class="content">
                            <input type="hidden" class="hidden" name="redirect" value="<?= ROOTPATH . "/activities/view/" . $id ?>">
                            <div class="form-group">
                                <label for="date_end"><?= lang('Activity ended at:', 'Aktivität beendet am:') ?></label>
                                <input type="date" class="form-control" name="values[end]" id="date_end" value="<?= valueFromDateArray($doc['end'] ?? null) ?>" required>
                            </div>
                            <button class="btn btn-block" type="submit"><?= lang('Save', 'Speichern') ?></button>
                        </form>
                    </div>
                </div>
            <?php } ?>

            <div class="dropdown">
                <button class="btn" data-toggle="dropdown" type="button" id="dropdown-1" aria-haspopup="true" aria-expanded="false">
                    <?= lang('Download', 'Herunterladen') ?> <i class="ph ph-dots-three-outline ml-5" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdown-1">
                    <div class="content">
                        <button class="btn block primary outline" onclick="addToCart(this, '<?= $id ?>')">
                            <i class="<?= (in_array($id, $cart)) ? 'ph ph-duotone ph-basket ph-basket-plus text-success' : 'ph ph-basket ph-basket-plus' ?>"></i>
                            <?= lang('Collect', 'Sammeln') ?>
                        </button>
                    </div>
                    <div class="divider"></div>
                    <form action="<?= ROOTPATH ?>/download" method="post" class="content">
                        <strong>
                            <?= lang('Download', 'Herunterladen') ?>
                        </strong>
                        <input type="hidden" name="filter[id]" value="<?= $id ?>">

                        <div class="form-group">

                            <?= lang('Highlight:', 'Hervorheben:') ?>

                            <div class="custom-radio ml-10">
                                <input type="radio" name="highlight" id="highlight-user" value="user" checked="checked">
                                <label for="highlight-user"><?= lang('Me', 'Mich') ?></label>
                            </div>

                            <div class="custom-radio ml-10">
                                <input type="radio" name="highlight" id="highlight-aoi" value="aoi">
                                <label for="highlight-aoi"><?= $Settings->get('affiliation') ?><?= lang(' Authors', '-Autoren') ?></label>
                            </div>

                            <div class="custom-radio ml-10">
                                <input type="radio" name="highlight" id="highlight-none" value="">
                                <label for="highlight-none"><?= lang('None', 'Nichts') ?></label>
                            </div>

                        </div>


                        <div class="form-group">

                            <?= lang('File format:', 'Dateiformat:') ?>

                            <div class="custom-radio ml-10">
                                <input type="radio" name="format" id="format-word" value="word" checked="checked">
                                <label for="format-word">Word</label>
                            </div>

                            <div class="custom-radio ml-10">
                                <input type="radio" name="format" id="format-bibtex" value="bibtex">
                                <label for="format-bibtex">BibTex</label>
                            </div>

                        </div>
                        <button class="btn block primary outline">
                            <i class="ph ph-download mr-5"></i>
                            Download</button>
                    </form>
                </div>
            </div>

            <div class="dropdown">
                <button class="btn" data-toggle="dropdown" type="button" id="dropdown-1" aria-haspopup="true" aria-expanded="false">
                    <?= lang('More Actions', 'Weitere Aktionen') ?> <i class="ph ph-dots-three-outline ml-5" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdown-1">
                    <!-- <h6 class="header">Header</h6> -->
                    <div class="content">
                        <a href="?view=old" class="btn block primary outline">
                            <i class="ph ph-lightning-slash m-0"></i>
                            <?= lang('Old View', 'Alte Ansicht') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- <h1 ><?= $doc['title']; ?></h1> -->

        <nav id="navigation" class="new-pills mt-20">
            <a onclick="navigate('general')" id="btn-general" class="btn active">
                <!-- <i class="ph ph-info" aria-hidden="true"></i> -->
                <?= lang('Overview', 'Übersicht') ?>
            </a>

            <?php if ($guests_involved) { ?>
                <a onclick="navigate('guests')" id="btn-guests" class="btn">
                    <!-- <i class="ph ph-user-plus" aria-hidden="true"></i> -->
                    <?= lang('Guests', 'Gäste') ?>
                    <span class="index"><?= count($guests) ?></span>
                </a>
            <?php } ?>


            <?php if ($count_authors > 1) { ?>
                <a onclick="navigate('coauthors')" id="btn-coauthors" class="btn">
                    <!-- <i class="ph ph-users" aria-hidden="true"></i> -->
                    <?= lang('Contributors', 'Mitwirkende') ?>
                    <span class="index"><?= $count_authors ?></span>
                </a>
            <?php } ?>

            <?php
            $count_history = count($doc['history'] ?? []);
            if ($count_history) :
            ?>
                <a onclick="navigate('history')" id="btn-history" class="btn">
                    <!-- <i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i> -->
                    <?= lang('History', 'Historie') ?>
                    <span class="index"><?= $count_history ?></span>
                </a>
            <?php endif; ?>

            <?php if ($Settings->hasPermission('raw-data') || isset($_GET['verbose'])) { ?>
                <a onclick="navigate('raw')" id="btn-raw" class="btn">
                    <!-- <i class="ph ph-code" aria-hidden="true"></i> -->
                    <?= lang('Raw data', 'Rohdaten')  ?>
                </a>
            <?php } ?>
        </nav>



        <section id="raw" style="display:none" class="box padded tab-box">

            <h2 class="title">
                <?= lang('Raw data', 'Rohdaten') ?>
            </h2>

            <?= lang('Raw data as they are stored in the database.', 'Die Rohdaten, wie sie in der Datenbank gespeichert werden.') ?>

            <div class="overflow-x-scroll">
                <pre><?= e(json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>

        </section>

        <section id="general">
            <div class="row row-eq-spacing my-0">
                <div class="col-md-8">

                    <div class="box padded tab-box">

                        <ul class="breadcrumb category" style="--highlight-color:<?= $Format->typeArr['color'] ?? '' ?>">
                            <li><?= $Format->activity_type() ?></li>
                            <li><?= $Format->activity_subtype() ?></li>
                        </ul>


                        <h2> <?= $Format->getTitle('web') ?></h2>
                        <p class="lead"><?= $Format->getSubtitle('web') ?></p>

                        <!-- <?php if (!empty($doc['authors'])): ?>
                    <ul class="authors">
                        <?php foreach ($doc['authors'] as $i => $author): ?>
                            <li style="<?= $i > 9 ? 'display:none;' : '' ?>">
                                <?php if (!empty($author['user'])): ?>
                                    <a href="<?= ROOTPATH ?>/profile/<?= $author['user'] ?>">
                                        <?= $author['first'] ?> <?= $author['last'] ?>
                                    </a>
                                <?php else: ?>
                                    <?= $author['first'] ?> <?= $author['last'] ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (count($doc['authors']) > 10): ?>
                            <li class="more-authors">
                                <a href="#" onclick="$(this).closest('ul').find('li').show(); $(this).parent().remove();">
                                    <?= lang("and " . (count($doc['authors']) - 10) . " more", "und " . (count($doc['authors']) - 10) . " weitere"); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?> -->



                        <div class="font-size-16 mt-10 mb-20">
                            <?php if (!empty($doc['doi'])): ?>
                                <a href="https://doi.org/<?= $doc['doi']; ?>" target="_blank" class="identifier">
                                    <span class="label"><?= lang("DOI"); ?></span> <?= $doc['doi']; ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($doc['pubmed'])): ?>
                                <a href="https://pubmed.ncbi.nlm.nih.gov/<?= $doc['pubmed']; ?>" target="_blank" class="identifier">
                                    <span class="label"><?= lang("PubMed"); ?></span> <?= $doc['pubmed']; ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($doc['isbn'])): ?>
                                <span class="identifier">
                                    <span class="label"><?= lang("ISBN"); ?></span> <?= $doc['isbn']; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($departments)): ?>
                            <h3 class="title"><?= lang("Departments", "Abteilungen") ?><sup>*</sup></h3>
                            <p>
                                <?php foreach ($departments as $deptId => $d): ?>
                                    <a href="<?= ROOTPATH ?>/group/<?= $deptId; ?>" class="badge primary mr-5 mb-5">
                                        <?= lang($d['en'], $d['de'] ?? null); ?>
                                    </a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($doc['abstract'])): ?>
                            <h3 class="title"><?= lang("Abstract", "Zusammenfassung"); ?></h3>
                            <div id="abstract">
                                <?php
                                // show only first 300 characters of abstract if it is longer, with option to show more
                                if (strlen($doc['abstract']) > 300) {
                                    echo '<div id="short-abstract">' . get_preview($doc['abstract'], 300) . '</div>';
                                    echo '<a id="show-more-abstract" class="">' . lang('Read more', 'Mehr lesen') . '</a>';
                                    echo '<div id="full-abstract" style="display:none;">' . $doc['abstract'] . '</div>';
                                } else {
                                    echo $doc['abstract'];
                                }
                                ?>
                            </div>
                            <script>
                                $('#show-more-abstract').click(function() {
                                    $('#short-abstract').hide();
                                    $('#full-abstract').show();
                                    $(this).hide();
                                });
                            </script>
                        <?php endif; ?>

                        <?php if ($upload_possible): ?>
                            <h3><?= lang("Files", "Dateien"); ?></h3>

                            <table class="table">
                                <tbody>
                                    <?php
                                    if (empty($documents)) {
                                        echo '<tr><td>' . lang('No documents available.', 'Keine Dokumente verfügbar.') . '</td></tr>';
                                    } else {
                                        foreach ($documents as $doc) {
                                            $file_url = ROOTPATH . '/uploads/' . $doc['_id'] . '.' . $doc['extension'];
                                    ?>
                                            <tr>
                                                <td class="font-size-18 text-center text-muted" style="width: 50px;">
                                                    <i class='ph ph-file ph-<?= getFileIcon($doc['extension'] ?? '') ?>'></i>
                                                </td>
                                                <td>
                                                    <?php if ($edit_perm) : ?>
                                                        <div class="float-right">
                                                            <div class="dropdown">
                                                                <button class="btn link" data-toggle="dropdown" type="button" id="edit-doc-<?= $doc['_id'] ?>" aria-haspopup="true" aria-expanded="false">
                                                                    <i class="ph ph-edit text-primary"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="edit-doc-<?= $doc['_id'] ?>">
                                                                    <div class="content">
                                                                        <form action="<?= ROOTPATH ?>/data/document/update" method="post">
                                                                            <div class="form-group floating-form">
                                                                                <select class="form-control" name="name" placeholder="Name" required>
                                                                                    <?php
                                                                                    $vocab = $Vocabulary->getValues('activity-document-types');
                                                                                    foreach ($vocab as $v) { ?>
                                                                                        <option value="<?= $v['id'] ?>" <?= ($doc['name'] == $v['id'] ? 'selected' : '') ?>><?= lang($v['en'], $v['de'] ?? null) ?></option>
                                                                                    <?php } ?>
                                                                                </select>
                                                                                <label for="name" class="required"><?= lang('Document type', 'Dokumenttyp') ?></label>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="description"><?= lang('Description', 'Beschreibung') ?></label>
                                                                                <textarea class="form-control" name="description" placeholder="<?= lang('Description', 'Beschreibung') ?>"><?= $doc['description'] ?? '' ?></textarea>
                                                                            </div>
                                                                            <input type="hidden" name="id" value="<?= $doc['_id'] ?>">
                                                                            <button class="btn btn-block primary" type="submit"><?= lang('Save changes', 'Änderungen speichern') ?></button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="dropdown">
                                                                <button class="btn link" data-toggle="dropdown" type="button" id="delete-doc-<?= $doc['_id'] ?>" aria-haspopup="true" aria-expanded="false">
                                                                    <i class="ph ph-trash text-danger"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="delete-doc-<?= $doc['_id'] ?>">
                                                                    <div class="content">
                                                                        <form action="<?= ROOTPATH ?>/data/delete" method="post">
                                                                            <span class="text-danger"><?= lang('Do you want to delete this document?', 'Möchtest du dieses Dokument wirklich löschen?') ?></span>
                                                                            <input type="hidden" name="id" value="<?= $doc['_id'] ?>">
                                                                            <button class="btn btn-block danger" type="submit"><?= lang('Delete', 'Löschen') ?></button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <h6 class="m-0">
                                                        <a href="<?= $file_url ?>" target="_blank" rel="noopener">
                                                            <?= $Vocabulary->getValue('activity-document-types', $doc['name'] ?? '', lang('Other', 'Sonstiges')); ?>
                                                            <i class="ph ph-download"></i>
                                                        </a>
                                                    </h6>
                                                    <?= $doc['description'] ?? '' ?>
                                                    <br>
                                                    <div class="font-size-12 text-muted d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <?= $doc['filename'] ?> (<?= $doc['size'] ?> Bytes)
                                                            <br>
                                                            <?= lang('Uploaded by', 'Hochgeladen von') ?> <?= $DB->getNameFromId($doc['uploaded_by']) ?>
                                                            <?= lang('on', 'am') ?> <?= date('d.m.Y', strtotime($doc['uploaded'])) ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        <?php endif; ?>



                        <h3><?= lang("Projects", "Projekte"); ?></h3>
                        <?php if (!empty($projects)): ?>
                            <div class="cards">
                                <?php foreach ($projects as $project): ?>
                                    <div class="card">
                                        <div>
                                            <h5 class="my-0">
                                                <a href="<?= ROOTPATH ?>/project/<?= $project['_id']; ?>"> <?= $project['name']; ?> </a>
                                            </h5>
                                            <small class="text-muted"><?= $project['title'] ?? '' ?></small>
                                            <hr />
                                            <b> <?= $project['funding_organization'] ?? $project['funder'] ?? $project['scholarship'] ?? "" ?> </b> &nbsp;
                                            <p><?= fromToDate($project['start'], $project['end']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?= lang("No associated projects found.", "Keine assoziierten Projekte gefunden."); ?></p>
                        <?php endif; ?>


                        <h3>
                            <?= lang("Infrastructures", "Infrastrukturen"); ?>
                        </h3>
                        <?php if (!empty($infrastructures)): ?>
                            <div class="cards">
                                <?php foreach ($infrastructures as $infrastructure): ?>
                                    <div class="card">
                                        <div>
                                            <h5 class="my-0">
                                                <a href="<?= ROOTPATH ?>/infrastructure/<?= $infrastructure['id']; ?>"> <?= $infrastructure['name']; ?> </a>
                                            </h5>
                                            <small class="text-muted"><?= $infrastructure['subtitle'] ?? '' ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?= lang("No associated infrastructures found.", "Keine assoziierten Infrastrukturen gefunden."); ?></p>
                        <?php endif; ?>


                        <h3><?= lang("Related Activities", "Verknüpfte Aktivitäten"); ?></h3>

                        <?php if (!empty($connected_activities)) : ?>
                            <table class="table">
                                <tbody>
                                    <?php foreach ($connected_activities as $con) { ?>
                                        <?php
                                        // check if activity is target or source
                                        $reverse = ($con['target_id'] == $id);
                                        $activity = $osiris->activities->findOne(['_id' => $reverse ? $con['source_id'] : $con['target_id']], ['projection' => [
                                            'rendered' => 1,
                                        ]]);
                                        $conLabel = $Format->getRelationshipLabel($con['relationship'], $reverse);
                                        ?>
                                        <tr>
                                            <td>
                                                <h5 class="m-0">
                                                    <?= lang($conLabel['en'], $conLabel['de']) ?>
                                                </h5>
                                                <div><?= $activity['rendered']['web'] ?? '' ?></div>
                                            </td>
                                            <?php if ($edit_perm) { ?>
                                                <td>
                                                    <form action="<?= ROOTPATH ?>/crud/activities/disconnect" method="post" class="d-inline-block ml-auto">
                                                        <input type="hidden" name="connection_id" value="<?= $con['_id'] ?>">
                                                        <input type="hidden" name="redirect" value="<?= ROOTPATH . "/activities/view/" . $id ?>#section-activities">
                                                        <button type="submit" class="btn small danger" data-toggle="tooltip" data-title="<?= lang('Disconnect activity', 'Aktivität trennen') ?>">
                                                            <i class="ph ph-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?= lang("No associated activities found.", "Keine assoziierten Aktivitäten gefunden."); ?></p>
                        <?php endif; ?>


                    </div>

                </div>



                <div class="col-md-4">

                    <!-- <h3 ><?= lang("Information", "Informationen"); ?></h3> -->
                    <table class="table" id="info-table">
                        <tbody>
                            <!-- topics -->
                            <?php if ($Settings->featureEnabled('topics')) { ?>
                                <tr>
                                    <td>
                                        <span class="key"><?= $Settings->topicLabel() ?></span>
                                        <?= $Settings->printTopics($doc['topics'] ?? []) ?: '-' ?>
                                    </td>
                                </tr>
                            <?php } ?>

                            <tr>
                                <td>
                                    <span class="key"><?= lang('Date', 'Datum') ?>: </span>
                                    <?= $Format->format_date($doc) ?>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <span class="key"><?= $Settings->get('affiliation') ?>: </span>
                                    <?php

                                    if ($doc['affiliated'] ?? true) { ?>
                                        <div class="badge success" data-toggle="tooltip" data-title="<?= lang('At least on author of this activity has an affiliation with the institute.', 'Mindestens ein Autor dieser Aktivität ist mit dem Institut affiliiert.') ?>">
                                            <!-- <i class="ph ph-handshake m-0"></i> -->
                                            <?= lang('Affiliated', 'Affiliiert') ?>
                                        </div>
                                    <?php } else { ?>
                                        <div class="badge danger" data-toggle="tooltip" data-title="<?= lang('None of the authors has an affiliation to the Institute.', 'Keiner der Autoren ist mit dem Institut affiliiert.') ?>">
                                            <!-- <i class="ph ph-hand-x m-0"></i> -->
                                            <?= lang('Not affiliated', 'Nicht affiliiert') ?>
                                        </div>
                                    <?php } ?>
                                </td>
                            </tr>

                            <!-- cooperative -->
                            <tr>
                                <td>
                                    <span class="key"><?= lang('Cooperation', 'Zusammenarbeit') ?>: </span>
                                    <?php
                                    switch ($doc['cooperative'] ?? '-') {
                                        case 'individual': ?>
                                            <span class="badge block" data-toggle="tooltip" data-title="<?= lang('Only one author', 'Nur ein Autor/eine Autorin') ?>">
                                                <?= lang('Individual', 'Einzelarbeit') ?>
                                            </span>
                                        <?php
                                            break;
                                        case 'departmental': ?>
                                            <span class="badge block" data-toggle="tooltip" data-title="<?= lang('Authors from the same department* of this institute', 'Autoren aus der gleichen Abteilung* des Instituts') ?>">
                                                <?= lang('Departmental', 'Abteilungsübergreifend') ?>
                                            </span>
                                        <?php
                                            break;
                                        case 'institutional': ?>
                                            <span class="badge block" data-toggle="tooltip" data-title="<?= lang('Authors from different departments* but all from this institute', 'Autoren aus verschiedenen Abteilungen*, aber alle vom Institut') ?>">
                                                <?= lang('Institutional', 'Institutionell') ?>
                                            </span>
                                        <?php
                                            break;
                                        case 'contributing': ?>
                                            <span class="badge block" data-toggle="tooltip" data-title="<?= lang('Authors from different institutes with us being middle authors', 'Autoren aus unterschiedlichen Instituten mit uns als Mittelautoren') ?>">
                                                <?= lang('Cooperative (Contributing)', 'Kooperativ (Beitragend)') ?>
                                            </span>
                                        <?php
                                            break;
                                        case 'leading': ?>
                                            <span class="badge block" data-toggle="tooltip" data-title="<?= lang('Authors from different institutes with us being leading authors', 'Autoren aus unterschiedlichen Instituten mit uns als führenden Autoren') ?>">
                                                <?= lang('Cooperative (Leading)', 'Kooperativ (Führend)') ?>
                                            </span>
                                        <?php
                                            break;
                                        default: ?>
                                            <span class="badge block" data-toggle="tooltip" data-title="<?= lang('No author affiliated', 'Autor:innen sind nicht affiliiert') ?>">
                                                <?= lang('None', 'Keine') ?>
                                            </span>
                                    <?php
                                            break;
                                    }
                                    ?>

                                </td>
                            </tr>

                            <?php if ($doc['impact'] ?? false || (isset($openalex) && isset($openalex['cited_by_count'])) ?? false) { ?>
                                <tr>
                                    <td>
                                        <div class="d-flex justify-content-between">
                                            <?php if ($doc['impact'] ?? false) { ?>
                                                <div>
                                                    <span class="key"><?= lang('Impact', 'Impact') ?>: </span>
                                                    <span class="badge"><?= $doc['impact'] ?></span>
                                                </div>
                                            <?php } ?>

                                            <?php if (!empty($openalex) && isset($openalex['cited_by_count'])) {
                                                $fetched_at = isset($openalex['fetched_at']) ? date('d.m.Y', strtotime($openalex['fetched_at'])) : '-';
                                            ?>
                                                <div>
                                                    <span class="key"><?= lang('Citations', 'Zitationen') ?>: </span>
                                                    <span class="badge" data-toggle="tooltip" data-title="<?= lang('Last updated', 'Zuletzt aktualisiert') ?>: <?= $fetched_at ?>"><?= $openalex['cited_by_count'] ?></span>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if ($doc['quartile'] ?? false) { ?>
                                <tr>
                                    <td>
                                        <span class="key"><?= lang('Quartile', 'Quartil') ?>: </span>
                                        <span class="quartile <?= $doc['quartile'] ?>"><?= $doc['quartile'] ?></span>
                                    </td>
                                </tr>
                            <?php } ?>

                            <?php if ($Settings->featureEnabled('portal')) {
                                $doc['hide'] = $doc['hide'] ?? false;
                                $visible_subtypes = $Settings->getActivitiesPortfolio(true);
                            ?>
                                <tr>
                                    <td>
                                        <span class="key"><?= lang('Online Visibility', 'Online-Sichtbarkeit') ?>: </span>
                                        <?php if (!in_array($doc['subtype'], $visible_subtypes)) { ?>
                                            <span class="badge warning" data-toggle="tooltip" data-title="<?= lang('This activity subtype is not visible on the portal due to general settings of your institute.', 'Dieser Aktivitätstyp ist aufgrund genereller Instituts-Einstellungen im Portal nicht sichtbar.') ?>">
                                                <i class="ph ph-eye-slash m-0"></i>
                                                <?= lang('Activity type not visible', 'Aktivitätstyp nicht sichtbar') ?>
                                            </span>
                                        <?php } else if ($edit_perm) { ?>
                                            <div class="custom-switch">
                                                <input type="checkbox" id="hide" <?= $doc['hide'] ? 'checked' : '' ?> name="values[hide]" onchange="hide()">
                                                <label for="hide" id="hide-label">
                                                    <?= $doc['hide'] ? lang('Hidden', 'Versteckt') :  lang('Visible', 'Sichtbar')  ?>
                                                </label>
                                            </div>

                                            <script>
                                                function hide() {
                                                    $.ajax({
                                                        type: "POST",
                                                        url: ROOTPATH + "/crud/activities/hide",
                                                        data: {
                                                            activity: ACTIVITY_ID
                                                        },
                                                        success: function(response) {
                                                            var hide = $('#hide').prop('checked');

                                                            $('#hide-label').text(hide ? '<?= lang('Hidden', 'Versteckt') ?>' : '<?= lang('Visible', 'Sichtbar') ?>');
                                                            $('#highlight').prop('disabled', hide);
                                                            if (hide) {
                                                                $('#highlight').prop('checked', false);
                                                                $('#highlight-label').text('<?= lang('Normal', 'Normal') ?>');
                                                            }
                                                            toastSuccess(lang('Visibility status changed', 'Sichtbarkeitsstatus geändert'))
                                                        },
                                                        error: function(response) {
                                                            console.log(response);
                                                        }
                                                    });
                                                }
                                            </script>


                                        <?php } else { ?>
                                            <?php if ($doc['hide']) { ?>
                                                <span class="badge danger" data-toggle="tooltip" data-title="<?= lang('This activity is hidden on the portal.', 'Diese Aktivität ist auf dem Portal versteckt.') ?>">
                                                    <i class="ph ph-eye-slash"></i>
                                                    <?= lang('Hidden', 'Versteckt') ?>
                                                </span>
                                            <?php } else { ?>
                                                <span class="badge success" data-toggle="tooltip" data-title="<?= lang('This activity is visible on the portal.', 'Diese Aktivität ist auf dem Portal sichtbar.') ?>">
                                                    <i class="ph ph-eye"></i>
                                                    <?= lang('Visible', 'Sichtbar') ?>
                                                </span>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>

                            <?php if ($DB->isUserActivity($doc, $_SESSION['username'], false)) {
                                $disabled = $doc['hide'] ?? false;
                                if ($disabled) {
                                    $highlighted = false;
                                } else {
                                    $highlights = DB::doc2Arr($USER['highlighted'] ?? []);
                                    $highlighted = in_array($id, $highlights);
                                }
                            ?>
                                <tr>
                                    <td>
                                        <span class="key"><?= lang('Displayed in your profile', 'Darstellung in deinem Profil') ?>: </span>
                                        <div class="custom-switch">
                                            <input type="checkbox" id="highlight" <?= ($highlighted) ? 'checked' : '' ?> name="values[highlight]" onchange="fav()" <?= $disabled ? 'disabled' : '' ?>>
                                            <label for="highlight" id="highlight-label">
                                                <?= $highlighted ? lang('Highlighted', 'Hervorgehoben') : lang('Normal', 'Normal') ?>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <script>
                                    function fav() {
                                        $.ajax({
                                            type: "POST",
                                            url: ROOTPATH + "/crud/activities/fav",
                                            data: {
                                                activity: ACTIVITY_ID
                                            },
                                            dataType: "json",
                                            success: function(response) {
                                                var highlight = $('#highlight').prop('checked');
                                                $('#highlight-label').text(highlight ? '<?= lang('Highlighted', 'Hervorgehoben') ?>' : '<?= lang('Normal', 'Normal') ?>');
                                                toastSuccess(lang('Highlight status changed', 'Hervorhebungsstatus geändert'))
                                            },
                                            error: function(response) {
                                                console.log(response);
                                            }
                                        });
                                    }
                                </script>
                            <?php } ?>
                        </tbody>
                    </table>

                    <?php
                    $Format->usecase = "list";
                    foreach (
                        [
                            'bibliography' => lang('Bibliography', 'Bibliographie'),
                            'locations' => lang('Locations', 'Orte'),
                            'events' => lang('Events', 'Veranstaltungen'),
                            'people' => lang('People and Organizations', 'Personen und Organisationen'),
                            'software' => lang('Software', 'Software'),
                            'others' => lang('Others', 'Andere')
                        ] as $section => $section_label
                    ) {
                        if (array_key_exists($section, $sections) && !empty($sections[$section])) { ?>
                            <h4 class="table-title"><?= $section_label ?></h4>
                            <table class="table">
                                <tbody>
                                    <?php foreach ($sections[$section] as $field) {
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="key"><?= lang($field['key_en'], $field['key_de']); ?></span>
                                                <span><?= $field['value']; ?></span>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                    <?php }
                    }
                    ?>

                    <?php if (count($empty_fields) > 0) { ?>

                        <p class="text-muted">
                            <small>
                                <?= lang("The following fields are empty: ", "Die folgenden Felder sind leer: ") ?>
                            </small>
                            <?= implode(", ", array_map(function ($f) use ($Modules) {
                                $names = $Modules->all_modules[$f] ?? [];
                                return lang($names['name_en'] ?? ucfirst($f), $names['name_de'] ?? ucfirst($f));
                            }, $empty_fields)) ?>
                        </p>
                    <?php } ?>
                    </table>

                </div>
            </div>
        </section>

        <section id="coauthors" style="display:none" class="box tab-box">
            <div class="content">

                <div class="row row-eq-spacing">
                    <div class="col-md-6 align-self-auto">

                        <?php
                        // --- Minimal helper: central role mapping (business logic) ---
                        function author_role_from_field(string $field_id): ?string
                        {
                            return match ($field_id) {
                                'supervisor', 'supervisor-thesis' => 'supervisors',
                                'editor' => 'editors',
                                'authors', 'author-table', 'scientist' => 'authors',
                                default => null,
                            };
                        }

                        $authorModules = ['authors', 'author-table', 'scientist', 'supervisor', 'supervisor-thesis', 'editor'];
                        $authorTypes = [];
                        foreach ($typeFields as $field_id => $props) {
                            if (!in_array($field_id, $authorModules, true)) continue;
                            $role = author_role_from_field($field_id);
                            if ($role === null) continue;
                            $authorTypes[] = $role;
                            $authors = $doc[$role] ?? [];
                            $canEdit = ($edit_perm) && (!$locked || $Settings->hasPermission('activities.edit-locked'));
                            // --- Configure optional third column (avoid duplicated if/elseif in thead + tbody) ---
                            $thirdCol = null;
                            if ($sws) {
                                $thirdCol = [
                                    'label' => 'SWS',
                                    'value' => fn($a) => (int)($a['sws'] ?? 0),
                                ];
                            } elseif ($supervisorThesis) {
                                $thirdCol = [
                                    'label' => lang('Role', 'Rolle'),
                                    'value' => fn($a) => $Format->getSupervisorRole($a['role'] ?? 'other'),
                                ];
                            } elseif ($role === 'authors') {
                                $thirdCol = [
                                    'label' => lang('Position', 'Position'),
                                    'value' => fn($a) => $Format->getPosition($a['position'] ?? ''),
                                ];
                            }
                        ?>
                            <div class="d-flex align-items-center gap-10 mb-10">
                                <h3 class="mt-0 mb-0"><?= $Modules->get_name($field_id) ?></h3>
                                <?php if ($canEdit): ?>
                                    <a href="<?= ROOTPATH ?>/activities/edit/<?= $id ?>/<?= $role ?>" class="">
                                        <i class="ph ph-edit"></i>
                                        <span class="sr-only"><?= lang("Edit", "Bearbeiten") ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <table class="table simple author-table mb-20">
                                <tbody id="<?= e($role) ?>">
                                    <?php foreach ($authors as $i => $author):
                                        // --- Name "Last, First" (inline; used once) ---
                                        $name = $author['last'] ?? '';
                                        if (!empty($author['first'])) $name .= ', ' . $author['first'];
                                        $name = trim($name);

                                        $hasUser = !empty($author['user']);
                                        $isAffiliated = (($author['aoi'] ?? 0) == 1);

                                        // Unique dropdown id per row (prevents collisions)
                                        $dropdownId = 'claim-dd-' . $role . '-' . $i;
                                    ?>
                                        <tr class="author-row">
                                            <td class="text-nowrap">
                                                <div class="author-name">
                                                    <?php if ($hasUser): ?>
                                                        <a href="<?= ROOTPATH ?>/profile/<?= e($author['user']) ?>">
                                                            <?= e($name) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?= e($name) ?>
                                                    <?php endif; ?>

                                                    <?php if (!empty($author['orcid'])): ?>
                                                        <a href="https://orcid.org/<?= e($author['orcid']) ?>"
                                                            target="_blank" rel="noopener"
                                                            data-toggle="tooltip"
                                                            data-title="ORCID: <?= e($author['orcid']) ?>">
                                                            <img loading="lazy" decoding="async" width="16" height="16"
                                                                class="orcid-img" style="width:16px;"
                                                                src="<?= ROOTPATH ?>/img/orcid.svg" alt="ORCID">
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="author-chips font-size-12 text-muted">

                                                    <?php if ($isAffiliated): ?>
                                                        <span class="author-chip success"
                                                            data-toggle="tooltip"
                                                            data-title="<?= lang('Author of the institution', 'Autor:in der Einrichtung') ?>">
                                                            <i class="ph ph-handshake"></i>
                                                            <?= lang('Affiliated', 'Affiliiert') ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($hasUser): ?>
                                                        <?php if ($author['approved']) { ?>
                                                            <span class="author-chip neutral"
                                                                data-toggle="tooltip"
                                                                data-title="<?= lang('Author approved this activity', 'Autor hat die Aktivität bestätigt') ?>">
                                                                <?= bool_icon(true) ?>
                                                                <?= lang('Approved', 'Bestätigt') ?>
                                                            </span>
                                                        <?php } else { ?>
                                                            <span class="author-chip neutral"
                                                                data-toggle="tooltip"
                                                                data-title="<?= lang('Author has not yet approved this activity', 'Autor hat die Aktivität noch nicht bestätigt') ?>">
                                                                <?= bool_icon(false) ?>
                                                                <?= lang('Pending', 'Ausstehend') ?>
                                                            </span>
                                                        <?php } ?>
                                                    <?php endif; ?>

                                                    <?php if (!empty($author['units'])): ?>
                                                        <div class="author-chip author-units">
                                                            <span class=""
                                                                data-toggle="tooltip"
                                                                data-title="<?= lang('Participating units', 'Beteiligte Einheiten') ?>">
                                                                <i class="ph ph-users-three"></i>
                                                            </span>
                                                            <?php foreach ($author['units'] as $unit):
                                                                $u = e((string)$unit);
                                                                $unit = $Groups->getGroup($u);
                                                                $p = $Groups->getUnitParent($u, 1);
                                                                // white or black text depending on brightness of background color
                                                                $bgColor = $p['color'];
                                                                $brightness = (hexdec(substr($bgColor, 1, 2)) * 0.299 + hexdec(substr($bgColor, 3, 2)) * 0.587 + hexdec(substr($bgColor, 5, 2)) * 0.114);
                                                                $textColor = ($brightness > 186) ? '#000000' : '#FFFFFF';
                                                                $title = lang($unit['name'] ?? '', $unit['name_de'] ?? null);
                                                            ?>
                                                                <a class="author-unit" href="<?= ROOTPATH ?>/groups/view/<?= $u ?>" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;"
                                                                    data-toggle="tooltip"
                                                                    data-title="<?= $title ?>">
                                                                    <?= $u ?>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($thirdCol)): ?>
                                                        <div>
                                                            <span class="author-chip neutral" data-toggle="tooltip" data-title="<?= $thirdCol['label'] ?>">
                                                                <?= $thirdCol['value']($author) ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!$hasUser && !$user_activity): ?>
                                                    <span class="claim-action">
                                                        <div class="dropdown d-inline-block">
                                                            <button class="btn small" data-toggle="dropdown" type="button"
                                                                id="<?= $dropdownId ?>" aria-haspopup="true" aria-expanded="false">
                                                                <?= lang('Claim', 'Beanspruchen') ?>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-right w-300" aria-labelledby="<?= $dropdownId ?>">
                                                                <div class="content font-size-12 text-danger mb-10" style="white-space: normal;">
                                                                    <?= lang(
                                                                        'You claim that you are this author.<br> This activity will be added to your list and the author name will be added to your list of alternative names.',
                                                                        'Du beanspruchst, dass du diese Person bist.<br> Du fügst diese Aktivität deiner Liste hinzu und den Namen zur Liste deiner alternativen Namen.'
                                                                    ) ?>
                                                                    <form action="<?= ROOTPATH ?>/crud/activities/claim/<?= $id ?>" method="post">
                                                                        <input type="hidden" name="role" value="<?= e($role) ?>">
                                                                        <input type="hidden" name="index" value="<?= (int)$i ?>">
                                                                        <input type="hidden" name="redirect" value="<?= ROOTPATH . "/activities/view/$id" ?>">
                                                                        <button class="btn block small" type="submit">
                                                                            <?= lang('Claim', 'Beanspruchen') ?>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                        <?php } ?>

                    </div>
                    <div class="col-md-6 flex-grow-0 d-flex flex-column align-items-center align-self-auto" style="max-width: 40rem">
                        <h3 class="mt-0">
                            <?= lang('Affiliation to units', 'Zuordnung zu Einheiten') ?>
                        </h3>
                        <?php if (count($authorTypes) > 1) { ?>
                            <div class="pills small no-borders mb-20" id="collab-type-filters">
                                <button class="btn active" onclick="showCollaboratorChart('collaborators', this)"><?= lang('All', 'Alle') ?></button>
                                <?php if (in_array('authors', $authorTypes)) { ?>
                                    <button class="btn" onclick="showCollaboratorChart('authors', this)"><?= lang('Authors', 'Autoren') ?></button>
                                <?php } ?>
                                <?php if (in_array('supervisors', $authorTypes)) { ?>
                                    <button class="btn" onclick="showCollaboratorChart('supervisors', this)"><?= lang('Supervisors', 'Betreuer:innen') ?></button>
                                <?php } ?>
                                <?php if (in_array('editors', $authorTypes)) { ?>
                                    <button class="btn" onclick="showCollaboratorChart('editors', this)"><?= lang('Editors', 'Herausgeber:innen') ?></button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <div id="chart-collaborators" class="collab-chart" style="max-width: 40rem;">
                            <canvas id="chart-collaborators-canvas"></canvas>
                        </div>
                        <div id="chart-authors" class="collab-chart" style="max-width: 40rem;">
                            <canvas id="chart-authors-canvas"></canvas>
                        </div>
                        <div id="chart-editors" class="collab-chart" style="max-width: 40rem;">
                            <canvas id="chart-editors-canvas"></canvas>
                        </div>
                        <div id="chart-supervisors" class="collab-chart" style="max-width: 40rem;">
                            <canvas id="chart-supervisors-canvas"></canvas>
                        </div>

                        <div id="dept-note" class="mt-20">
                            <small class="text-muted">
                                <?= lang('Departments* are determined based on the organizational units of the authors. If an author is affiliated with multiple units, they will be added to more than one department.', 'Die Abteilungen* werden basierend auf den Organisationseinheiten der Autoren bestimmt. Wenn ein Autor mehreren Einheiten zugeordnet ist, wird er zu mehr als einer Abteilung hinzugefügt.') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <hr>
            <div class="content">

                <div class="row row-eq-spacing">
                    <div class="col-md-6">

                        <h3 class="mt-0">
                            <?= lang('Affiliated positions', 'Affiliierte Positionen') ?>
                        </h3>

                        <?php
                        $positions = [
                            'first' => lang('First author', 'Erstautor:in'),
                            'last' => lang('Last author', 'Letztautor:in'),
                            'first_and_last' => lang('First and last author', 'Erst- und Letztautor:in'),
                            'first_or_last' => lang('First or last author', 'Erst- oder Letztautor:in'),
                            'middle' => lang('Middle author', 'Mittelautor:in'),
                            'single' => lang('One single affiliated author', 'Ein einzelner affiliierter Autor'),
                            'none' => lang('No author affiliated', 'Kein:e Autor:in affiliiert'),
                            'all' => lang('All authors affiliated', 'Alle Autoren affiliiert'),
                            'corresponding' => lang('Corresponding author', 'Korrespondierender Autor:in'),
                            'not_first' => lang('Not first author', 'Nicht Erstautor:in'),
                            'not_last' => lang('Not last author', 'Nicht letzter Autor:in'),
                            'not_middle' => lang('Not middle author', 'Nicht Mittelautor:in'),
                            'not_corresponding' => lang('Not corresponding author', 'Nicht korrespondierender Autor:in'),
                            'not_first_or_last' => lang('Not first or last author', 'Nicht Erst- oder Letztautor:in'),
                            'not_first_and_last' => lang('Not first and last author', 'Nicht Erst- und Letztautor:in'),
                            'unspecified' => lang('Unspecified (no position specified)', 'Unspezifiziert (keine Positionsangabe)'),
                        ];
                        ?>


                        <?php foreach ($doc['affiliated_positions'] ?? [] as $key) { ?>
                            <span class="badge mr-5 mb-5"><?= $positions[$key] ?? $key ?></span>
                        <?php } ?>
                        <br>
                        <small class="text-muted">
                            <?= lang('Automatically calculated', 'Automatisch berechnet') ?>
                        </small>

                    </div>
                    <div class="col-md-6">

                        <h3 class="mt-0">
                            <?= lang('Participating units', 'Beteiligte Einheiten') ?>
                        </h3>
                        <table class="table unit-table w-full no-borders">
                            <tbody>
                                <?php
                                if (!empty($doc['units'] ?? [])) {
                                    $units = $doc['units'];
                                    $hierarchy = $Groups->getPersonHierarchyTree($units);
                                    $tree = $Groups->readableHierarchy($hierarchy);

                                    foreach ($tree as $row) {
                                        $dept = $Groups->getGroup($row['id']);
                                ?>
                                        <tr>
                                            <td class="indent-<?= $row['indent'] ?>">
                                                <a href="<?= ROOTPATH ?>/groups/view/<?= $row['id'] ?>">
                                                    <?= lang($row['name_en'], $row['name_de'] ?? null) ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php
                                    }
                                } else { ?>
                                    <tr>
                                        <td>
                                            <?= lang('No organisational unit connected', 'Keine Organisationseinheit verknüpft') ?>
                                        </td>
                                    </tr>
                                <?php }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>



        <!-- new section with history -->
        <section id="history" style="display: none;" class="box padded tab-box">
            <h2 class="mt-0">
                <?= lang('History', 'Historie') ?>
            </h2>
            <p>
                <?= lang('History of changes to this activity.', 'Historie der Änderungen an dieser Aktivität.') ?>
            </p>

            <?php
            if (empty($doc['history'] ?? [])) {
                echo lang('No history available.', 'Keine Historie verfügbar.');
            } else {
                // require BASEPATH . "/php/TextDiff/TextDiff.php";
                // $latest = '';
            ?>
                <div class="history-list ">
                    <?php foreach (($doc['history'] ?? []) as $h) {
                        if (!is_array($h)) continue;
                    ?>
                        <div class="">
                            <small class="text-primary"><?= date('d.m.Y', strtotime($h['date'])) ?></small>
                            <h5 class="m-0">
                                <?php
                                echo Settings::getHistoryType($h['type']);
                                echo ' ';
                                if (isset($h['user']) && !empty($h['user'])) {
                                    echo '<a href="' . ROOTPATH . '/profile/' . $h['user'] . '">' . $DB->getNameFromId($h['user']) . '</a>';
                                } else {
                                    echo "System";
                                }
                                ?>
                            </h5>

                            <?php
                            if (isset($h['comment']) && !empty($h['comment'])) { ?>
                                <code><?= $h['comment'] ?></code>
                            <?php
                            }
                            if (isset($h['changes']) && !empty($h['changes'])) {
                                echo '<div class="font-weight-bold mt-10">' .
                                    lang('Changes to the activity:', 'Änderungen an der Aktivität:') .
                                    '</div>';
                                echo '<table class="table simple w-auto small">';
                                foreach ($h['changes'] as $key => $change) {
                                    $before = $change['before'] ?? '<em>empty</em>';
                                    $after = $change['after'] ?? '<em>empty</em>';
                                    if ($before == $after) continue;
                                    if (empty($before)) $before = '<em>empty</em>';
                                    if (empty($after)) $after = '<em>empty</em>';
                                    echo '<tr>
                                <td class="">
                                    <span class="key">' . $Modules->get_name($key) . '</span> 
                                    <span class="del">' . $before . '</span>
                                    <i class="ph ph-arrow-right mx-10"></i>
                                    <span class="ins">' . $after . '</span>
                                </td>
                            </tr>';
                                }
                                echo '</table>';
                            } else  if (isset($h['data']) && !empty($h['data'])) {
                                echo '<div class="font-weight-bold mt-10">' .
                                    lang('Status at this time point:', 'Status zu diesem Zeitpunkt:') .
                                    '</div>';

                                echo '<table class="table simple w-auto small">';
                                foreach ($h['data'] as $key => $datum) {
                                    echo '<tr>
                                <td class="">
                                    <span class="key">' . $Modules->get_name($key) . '</span> 
                                    ' . $datum . ' 
                                </td>
                            </tr>';
                                }
                                echo '</table>';
                            } else if ($h['type'] == 'edited') {
                                echo lang('No changes tracked.', 'Es wurden keine Änderungen verfolgt.');
                            }
                            ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>


        <?php
        $print = $doc['rendered']['print'];
        $bibtex = $Format->bibtex();
        $ris = $Format->ris();
        ?>

        <h3><?= lang("Cite this activity", "Zitiere diese Aktivität"); ?></h3>
        <nav class="tabs">
            <a class="btn active" onclick="nav('citation')">Citation</a>
            <?php if (!empty($bibtex)): ?>
                <a class="btn" onclick="nav('bibtex')">BibTeX</a>
            <?php endif; ?>
            <?php if (!empty($ris)): ?>
                <a class="btn" onclick="nav('ris')">RIS</a>
            <?php endif; ?>
        </nav>

        <div id="tabs">
            <div class="box padded tab-box" id="citation-box">
                <button class="btn small float-right" onclick="copyToClipboard('#citation')" data-toggle="tooltip" data-title="<?= lang('Copy to clipboard', 'In die Zwischenablage kopieren') ?>">
                    <i class="ph ph-clipboard" aria-label="Copy to clipboard"></i>
                </button>
                <span id="citation"><?= $print ?></span>
            </div>
            <div class="box padded tab-box" id="bibtex-box" style="display: none;">
                <button class="btn small float-right" onclick="copyToClipboard('#bibtex')" data-toggle="tooltip" data-title="<?= lang('Copy to clipboard', 'In die Zwischenablage kopieren') ?>">
                    <i class="ph ph-clipboard" aria-label="Copy to clipboard"></i>
                </button>
                <div class="overflow-x-scroll">
                    <pre id="bibtex"><?= $bibtex ?? '' ?></pre>
                </div>
            </div>
            <div class="box padded tab-box" id="ris-box" style="display: none;">
                <button class="btn small float-right" onclick="copyToClipboard('#ris')" data-toggle="tooltip" data-title="<?= lang('Copy to clipboard', 'In die Zwischenablage kopieren') ?>">
                    <i class="ph ph-clipboard" aria-label="Copy to clipboard"></i>
                </button>
                <div class="overflow-x-scroll">
                    <pre id="ris"><?= $ris ?? '' ?></pre>
                </div>
            </div>
        </div>
    </div>


    <p class="text-muted">
        *<?= lang('We use the term "department" here to refer to the level of organizational units directly below the top-level unit (e.g. faculty or institution). The exact term may vary depending on the organizational structure of your institution.', 'Wir verwenden hier den Begriff "Abteilung" für die Ebene der Organisationseinheiten direkt unterhalb der obersten Einheit (z.B. Fakultät oder Einrichtung). Der genaue Begriff kann je nach Organisationsstruktur deiner Einrichtung variieren.') ?>
    </p>

</div>




<script>
    function nav(id) {
        document.querySelectorAll('.tabs .btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(id + '-box').style.display = 'block';
        document.querySelector('.tabs .btn[onclick="nav(\'' + id + '\')"]').classList.add('active');
        ['citation', 'bibtex', 'ris'].forEach(box => {
            if (box !== id) {
                document.getElementById(box + '-box').style.display = 'none';
            }
        });
    }

    function copyToClipboard(selector) {
        // check if navigator.clipboard is available
        if (!navigator.clipboard) {
            toastError(lang('This browser does not support copying to clipboard.', 'Dieser Browser unterstützt das Kopieren in die Zwischenablage nicht.'));
            return;
        }
        var text = $(selector).text()
        navigator.clipboard.writeText(text)
        toastSuccess(lang('Query copied to clipboard.', 'Abfrage in die Zwischenablage kopiert.'))
    }
</script>