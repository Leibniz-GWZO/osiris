<?php

/**
 * Header component
 * 
 * This file is part of the OSIRIS package.
 * Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 *
 * @package     OSIRIS
 * @since       1.0.0
 * 
 * @copyright	Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * @author		Julia Koblitz <julia.koblitz@osiris-solutions.de>
 * @license     MIT
 */

include_once BASEPATH . "/php/init.php";

$breadcrumb = $breadcrumb ?? [];
$pagetitle = array('OSIRIS');
foreach ($breadcrumb as $crumb) {
    array_push($pagetitle, $crumb['name']);
}
$pagetitle = implode(' | ', array_reverse($pagetitle));

$uri = $_SERVER['REQUEST_URI'];
// $uri = str_replace(ROOTPATH."/", '', $uri, 1);
$uri = substr_replace($uri, '', 0, strlen(ROOTPATH . "/"));
$lasturl = explode("/", $uri);
// dump($lasturl);
$page =  $page ?? $lasturl[0]; //end($lasturl);

$pageactive = function ($p) use ($page) {
    if ($page == $p) return "active";
    $uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
    if ((ROOTPATH . "/" . $p) == $uri) return 'active';
    return "";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Meta tags -->
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport" />
    <meta name="description" content="OSIRIS ist ein modernes Forschungsinformationssystem, das besonderen Schwerpunkt auf Open Source und Nutzerfreundlichkeit legt." />

    <!-- Favicon and title -->
    <link rel="icon" href="img/favicon.png">
    <title><?= $pagetitle ?? 'OSIRIS-App' ?></title>
    <link rel="manifest" href="<?= ROOTPATH ?>/manifest.json">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="OSIRIS - the open, smart and intuitive research information system" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://osiris-app.de" />
    <meta property="og:description" content="OSIRIS ist ein modernes Forschungsinformationssystem, das besonderen Schwerpunkt auf Open Source und Nutzerfreundlichkeit legt.." />
    <meta property="og:image" content="<?= ROOTPATH ?>/img/apple-touch-icon.png" />

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://osiris-app.de">
    <meta property="twitter:title" content="OSIRIS - the open, smart and intuitive research information system">
    <meta property="twitter:description" content="OSIRIS ist ein modernes Forschungsinformationssystem, das besonderen Schwerpunkt auf Open Source und Nutzerfreundlichkeit legt..">
    <meta property="twitter:image" content="<?= ROOTPATH ?>/img/apple-touch-icon.png">

    <!-- Apple -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ROOTPATH ?>/img/apple-touch-icon.png">
    <link rel="mask-icon" href="<?= ROOTPATH ?>/img/mask-icon.svg" color="#dd590e">

    <!-- Favicon and title -->
    <link rel="icon" href="<?= ROOTPATH ?>/img/favicon.png">
    <title><?= $pagetitle ?? 'OSIRIS' ?></title>

    <!-- Icon font -->
    <link href="<?= ROOTPATH ?>/css/phosphoricons/regular/style.css?v=<?= CSS_JS_VERSION ?>" rel="stylesheet" />
    <link href="<?= ROOTPATH ?>/css/phosphoricons/fill/style.css?v=<?= CSS_JS_VERSION ?>" rel="stylesheet" />
    <!-- for open access icons -->
    <link href="<?= ROOTPATH ?>/css/fontello/css/osiris.css?v=<?= CSS_JS_VERSION ?>" rel="stylesheet" />

    <link rel="stylesheet" href="<?= ROOTPATH ?>/css/main.css?<?= filemtime(BASEPATH . '/css/main.css') ?>">
    <?php
    echo $Settings->generateStyleSheet();
    ?>
    <style>
        :root {
            --affiliation: "<?= $Settings->get('affiliation') ?>";
        }
    </style>

    <script>
        const ROOTPATH = "<?= ROOTPATH ?>";
        const AFFILIATION = "<?= $Settings->get('affiliation') ?>";
    </script>

    <script src="<?= ROOTPATH ?>/js/jquery-3.3.1.min.js?v=<?= CSS_JS_VERSION ?>"></script>
    <script src="<?= ROOTPATH ?>/js/datatables/jquery.dataTables.min.js?v=<?= CSS_JS_VERSION ?>"></script>
    <script src="<?= ROOTPATH ?>/js/datatables/dataTables.responsive.min.js?v=<?= CSS_JS_VERSION ?>"></script>
    <script>
        $.extend($.fn.DataTable.ext.classes, {
            sPaging: "pagination mt-10 ",
            sPageFirst: "direction ",
            sPageLast: "direction ",
            sPagePrevious: "direction ",
            sPageNext: "direction ",
            sPageButtonActive: "active ",
            sFilterInput: "form-control small d-inline w-auto ml-10 ",
            sLengthSelect: "form-control small d-inline w-auto",
            sInfo: "float-right text-muted",
            sLength: "float-right"
        });
    </script>
    <script src="<?= ROOTPATH ?>/js/osiris.js?<?= filemtime(BASEPATH . '/js/osiris.js') ?>"></script>
    <script src="<?= ROOTPATH ?>/js/script.js?<?= filemtime(BASEPATH . '/js/script.js') ?>"></script>

    <?php if (isset($additionalHead)) {
        echo $additionalHead;
    } ?>

</head>

<body>
    <div class="loader">
        <span></span>
    </div>

    <!-- Page wrapper start -->
    <div class="page-wrapper 
        <?= $_COOKIE['D3-accessibility-contrast'] ?? '' ?>
        <?= $_COOKIE['D3-accessibility-transitions'] ?? '' ?>
        <?= $_COOKIE['D3-accessibility-dyslexia'] ?? '' ?>
    ">
        <div class="sticky-alerts"></div>


        <div class="modal" id="the-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <a data-dismiss="modal" class="btn float-right" role="button" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </a>
                    <h5 class="modal-title" id="modal-title"></h5>
                    <div id="modal-content"></div>
                </div>
            </div>
        </div>

        <!-- Sidebar overlay -->
        <div class="sidebar-overlay" onclick="osirisJS.toggleSidebar()"></div>

        <!-- Navbar start -->
        <div class="navbar navbar-top">
            <a href="<?= ROOTPATH ?>/" class="navbar-brand ml-20">
                <img src="<?= ROOTPATH ?>/img/logo.svg" alt="OSIRIS">
                <?php if (defined('LIVE') && LIVE === false) { ?>
                    <span class=" position-absolute bottom-0 left-0 secondary" style="font-size: 1rem;z-index:1">TESTSYSTEM</span>
                <?php } ?>
            </a>

            <a href="<?= $Settings->get('affiliation_details')['link'] ?? '#' ?>" class="navbar-brand ml-auto" target="_blank">
                <?= $Settings->printLogo("") ?>
            </a>
        </div>
        <nav class="navbar navbar-bottom">
            <!-- Button to toggle sidebar -->
            <button class="btn btn-action active" type="button" onclick="osirisJS.toggleSidebar(this);" aria-label="Toggle sidebar"></button>
            <ul class="navbar-nav">
                <?php if (defined('MAINTENANCE') && MAINTENANCE) { ?>
                    <!-- set to true during maintenance -->
                    <div class="alert danger">
                        <b><?= lang('System maintenance', 'Wartungsarbeiten') ?>.</b>
                        <?= lang('Please do not add, edit or remove data. Changes might be overwritten.', 'Bitte keine Daten hinzufügen, bearbeiten oder löschen. Änderungen werden evtl. überschrieben.') ?>
                    </div>
                <?php } else { ?>
                    <nav aria-label="breadcrumbs">
                        <ul class="breadcrumb">
                            <?php
                            $breadcrumb = $breadcrumb ?? [];
                            if (!empty($breadcrumb)) {
                                echo '<li class=""><a href="' . ROOTPATH . '/"><i class="ph ph-house" aria-label="Home"></i></a></li>';
                                foreach ($breadcrumb as $crumb) {
                                    $displayName = shortenName($crumb['name'] ?? '');
                                    if (!isset($crumb['path'])) {
                                        echo '<li class="active" aria-current="page"><a href="#">' . $displayName . '</a></li>';
                                    } else {
                                        echo '<li class=""><a href="' . ROOTPATH . $crumb['path'] . '">' . $displayName . '</a></li>';
                                    }
                                }
                            }
                            ?>
                        </ul>
                    </nav>
                <?php } ?>

            </ul>

            <!-- Accessibility menu -->
            <div class="dropdown d-none d-md-block">
                <button class="btn text-primary border-primary square mr-5" data-toggle="dropdown" type="button" id="accessibility-menu" aria-haspopup="true" aria-expanded="false">
                    <i class="ph ph-person-arms-spread ph-person-simple-circle"></i>
                    <span class="sr-only"><?= lang('Accessibility Options', 'Accessibility-Optionen') ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-center w-300" aria-labelledby="accessibility-menu">
                    <h6 class="header text-primary">Accessibility</h6>
                    <form action="<?= ROOTPATH ?>/set-preferences" method="get" class="content">
                        <input type="hidden" name="accessibility[check]">
                        <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">

                        <div class="form-group">
                            <div class="custom-checkbox">
                                <input type="checkbox" id="set-contrast" name="accessibility[contrast]" value="high-contrast" <?= !empty($_COOKIE['D3-accessibility-contrast'] ?? '') ? 'checked' : '' ?>>
                                <label for="set-contrast"><?= lang('High contrast', 'Erhöhter Kontrast') ?></label><br>
                                <small class="text-muted">
                                    <?= lang('Enhance the contrast of the web page for better readability.', 'Erhöht den Kontrast für bessere Lesbarkeit.') ?>
                                </small>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-checkbox">
                                <input type="checkbox" id="set-transitions" name="accessibility[transitions]" value="without-transitions" <?= !empty($_COOKIE['D3-accessibility-transitions'] ?? '') ? 'checked' : '' ?>>
                                <label for="set-transitions"><?= lang('Reduce motion', 'Verringerte Bewegung') ?></label><br>
                                <small class="text-muted">
                                    <?= lang('Reduce motion and animations on the page.', 'Verringert Animationen und Bewegungen auf der Seite.') ?>
                                </small>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-checkbox">
                                <input type="checkbox" id="set-dyslexia" name="accessibility[dyslexia]" value="dyslexia" <?= !empty($_COOKIE['D3-accessibility-dyslexia'] ?? '') ? 'checked' : '' ?>>
                                <label for="set-dyslexia"><?= lang('Dyslexia mode', 'Dyslexie-Modus') ?></label><br>
                                <small class="text-muted">
                                    <?= lang('Use a special font to increase readability for users with dyslexia.', 'OSIRIS nutzt eine spezielle Schriftart, die von manchen Menschen mit Dyslexie besser gelesen werden kann.') ?>
                                </small>
                            </div>
                        </div>
                        <button class="btn primary">Apply</button>
                    </form>
                </div>
            </div>

            <form action="<?= ROOTPATH ?>/set-preferences" method="get">
                <input type="hidden" name="language" value="<?= lang('de', 'en') ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <button type="submit" class="btn text-primary border-primary mr-5">
                    <i class="ph ph-translate" aria-hidden="true"></i>
                    <span class="sr-only"><?= lang('Change language', 'Sprache ändern') ?></span>
                    <?= lang('DE', 'EN') ?>
                </button>
            </form>

            <?php if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['username'])) { ?>
                <a href="<?= ROOTPATH ?>/" class="btn text-primary border-primary mr-5">
                    <i class="ph ph-sign-in" aria-hidden="true"></i>
                    <?= lang('Log in', 'Anmelden') ?>
                </a>
                <?php } else {
                $realusername = $_SESSION['realuser'] ?? $_SESSION['username'];
                $maintain = $osiris->persons->find(['maintenance' => $realusername, 'username' => ['$exists' => true]], ['projection' => ['displayname' => 1, 'username' => 1]])->toArray();
                if (!empty($maintain)) { ?>
                    <form action="" class="nav-search" id="navbar-search">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text border-primary text-primary"><i class="ph ph-user"></i></span>
                            </div>

                            <select name="OSIRIS-SELECT-MAINTENANCE-USER" id="osiris-select-maintenance-user" class="form-control border-primary bg-white" onchange="$(this).closest('form').submit()">
                                <option value="" disabled>
                                    <?= lang('Switch user', 'Benutzer wechseln') ?>
                                </option>
                                <option value="<?= $realusername ?>"><?= $DB->getNameFromId($realusername) ?></option>
                                <?php
                                foreach ($maintain as $d) { ?>
                                    <option value="<?= $d['username'] ?>" <?= $d['username'] ==  $_SESSION['username'] ? 'selected' : '' ?>><?= $DB->getNameFromId($d['username']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </form>
                <?php } else { ?>
                    <form id="navbar-search" action="<?= ROOTPATH ?>/activities" method="get" class="nav-search">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control border-primary" autocomplete="off" placeholder="<?= lang('Search in activities', 'Suche in Aktivitäten') ?>">
                            <div class="input-group-append">
                                <button class="btn primary filled"><i class="ph ph-magnifying-glass"></i></button>
                            </div>
                        </div>
                    </form>
            <?php }
            } ?>


        </nav>
        <!-- Sidebar start -->
        <div class="sidebar">
            <div class="sidebar-menu">

                <!-- Sidebar links and titles -->
                <?php if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] === false) { ?>

                    <a href="<?= ROOTPATH ?>/" class="cta with-icon <?= $pageactive('add-activity') ?>">
                        <i class="ph ph-sign-in mr-10" aria-hidden="true"></i>
                        <?= lang('Log in', 'Anmelden') ?>
                    </a>

                    <?php if (strtoupper(USER_MANAGEMENT) === 'AUTH') { ?>
                        <a href="<?= ROOTPATH ?>/auth/new-user" class="with-icon <?= $pageactive('auth/new-user') ?>">
                            <i class="ph ph-user-plus" aria-hidden="true"></i>
                            <?= lang('Register', 'Registrieren') ?>
                        </a>
                    <?php } ?>


                <?php } else { ?>

                    <!-- search bar -->
                    <div class="content" style="margin-top:-4rem">
                        <input type="text" class="form-control small border-primary" autocomplete="off" placeholder="<?= lang('Search', 'Suche') ?>" oninput="searchNavBar(this.value)">
                    </div>

                    <nav id="sidebar-add">
                        <a href="<?= ROOTPATH ?>/add-activity" class="cta with-icon <?= $pageactive('add-activity') ?>">
                            <i class="ph ph-plus-circle mr-10" aria-hidden="true"></i>
                            <?= lang('Add activity', 'Aktivität hinzuf.') ?>
                        </a>

                        <div id="sidebar-add-navigation">

                            <?php if ($Settings->featureEnabled('projects') && $Settings->hasPermission('projects.add')) { ?>
                                <?php if ($DB->canProposalsBeCreated()) { ?>
                                    <a href="<?= ROOTPATH ?>/proposals/new" class="">
                                        <i class="ph ph-tree-structure"></i>
                                        <?= lang('Add project proposal', 'Projektantrag hinzuf.') ?>
                                    </a>
                                <?php } ?>

                                <?php if ($DB->canProjectsBeCreated()) { ?>
                                    <a href="<?= ROOTPATH ?>/projects/new" class="">
                                        <i class="ph ph-tree-structure"></i>
                                        <?= lang('Add project', 'Projekt hinzuf.') ?>
                                    </a>
                                <?php } ?>

                            <?php } ?>
                            <?php if ($Settings->hasPermission('conferences.edit')) { ?>
                                <a href="<?= ROOTPATH ?>/conferences#add-conference">
                                    <i class="ph ph-calendar-plus"></i>
                                    <?= lang('Add event', 'Event hinzufügen') ?>
                                </a>
                            <?php } ?>
                            <?php if ($Settings->featureEnabled('infrastructures') && $Settings->hasPermission('infrastructures.edit')) { ?>
                                <a href="<?= ROOTPATH ?>/infrastructures/new">
                                    <i class="ph ph-cube-transparent"></i>
                                    <?= lang('Add infrastructure', 'Infrastruktur hinzuf.') ?>
                                </a>
                            <?php } ?>
                        </div>
                    </nav>



                    <script>
                        function searchNavBar(value) {
                            var links = $('.sidebar a');
                            links.each(function() {
                                if ($(this).text().toLowerCase().includes(value.toLowerCase())) {
                                    $(this).css('display', 'flex');
                                } else {
                                    $(this).css('display', 'none');
                                }
                            });

                            // hide empty header
                            var titles = $('.sidebar .title');
                            titles.each(function() {
                                var nav = $(this).next();
                                var visible = false;
                                nav.children().each(function() {
                                    if ($(this).css('display') == 'flex') {
                                        visible = true;
                                    }
                                });
                                if (visible) {
                                    $(this).css('display', 'block');
                                } else {
                                    $(this).css('display', 'none');
                                }
                            });
                        }
                    </script>

                    <div class="title collapse open" onclick="toggleSidebar(this);" id="sidebar-user">
                        <?= lang('My area', 'Mein Bereich') ?>
                    </div>

                    <nav>
                        <a href="<?= ROOTPATH ?>/profile/<?= $_SESSION['username'] ?>" class="with-icon <?= $pageactive('profile/' . $_SESSION['username']) ?>">
                            <i class="ph ph-user" aria-hidden="true"></i>
                            <?= $USER["displayname"] ?? 'User' ?>
                        </a>

                        <?php if ($Settings->hasPermission('scientist')) { ?>
                            <a href="<?= ROOTPATH ?>/my-year" class="with-icon <?= $pageactive('my-year') ?>">
                                <i class="ph ph-calendar" aria-hidden="true"></i>
                                <?= lang('My year', 'Mein Jahr') ?>
                            </a>
                            <a href="<?= ROOTPATH ?>/calendar" class="with-icon <?= $pageactive('calendar') ?>">
                                <i class="ph ph-calendar-dots" aria-hidden="true"></i>
                                <?= lang('Calendar', 'Kalender') ?>
                            </a>

                            <a href="<?= ROOTPATH ?>/my-activities" class="with-icon <?= $pageactive('my-activities') ?>">
                                <i class="ph ph-folder-user" aria-hidden="true"></i>
                                <?= lang('My activities', 'Meine Aktivitäten') ?>
                            </a>
                        <?php } ?>


                        <a href="<?= ROOTPATH ?>/user/logout" class=" with-icon" style="--primary-color:var(--danger-color);--primary-color-20:var(--danger-color-20);">
                            <i class="ph ph-sign-out" aria-hidden="true"></i>
                            Logout
                        </a>

                    </nav>

                    <div class="title collapse open" onclick="toggleSidebar(this);" id="sidebar-activities">
                        <?= lang('Data', 'Daten') ?>
                    </div>

                    <nav>

                        <style>
                            .sidebar-menu>a.inline-btn,
                            .sidebar-menu nav>a.inline-btn {
                                position: absolute;
                                right: 2rem;
                                margin: .5rem;
                                background: white;
                                padding: .5rem;
                                width: 3rem;
                                height: 3rem;
                                font-size: 1.4rem;
                                border-radius: var(--border-radius);
                            }

                            .sidebar-menu>a.inline-btn:not(.active),
                            .sidebar-menu nav>a.inline-btn:not(.active) {
                                display: none;
                            }

                            .sidebar-menu:hover>a.inline-btn:not(.active),
                            .sidebar-menu:hover nav>a.inline-btn:not(.active) {
                                display: block;
                            }
                        </style>

                        <a href="<?= ROOTPATH ?>/activities/search" class="inline-btn <?= $pageactive('activities') ?>" title="<?= lang('Advanced Search', 'Erweiterte Suche') ?>">
                            <i class="ph ph-magnifying-glass-plus"></i>
                        </a>
                        <a href="<?= ROOTPATH ?>/activities" class="with-icon <?= $pageactive('activities') ?>">
                            <i class="ph ph-folders" aria-hidden="true"></i>
                            <?= lang('All activities', 'Alle Aktivitäten') ?>
                        </a>

                        <?php if ($Settings->featureEnabled('projects')) { ?>
                            <?php if ($DB->canProposalsBeCreated()) { ?>
                                <a href="<?= ROOTPATH ?>/proposals" class="with-icon <?= $pageactive('proposals') ?>">
                                    <i class="ph ph-tree-structure" aria-hidden="true"></i>
                                    <?= lang('Proposals', 'Anträge') ?>
                                </a>
                            <?php } ?>


                            <a href="<?= ROOTPATH ?>/projects/search" class="inline-btn mt-10 <?= $pageactive('projects') ?>" title="<?= lang('Advanced Search', 'Erweiterte Suche') ?>">
                                <i class="ph ph-magnifying-glass-plus"></i>
                            </a>
                            <a href="<?= ROOTPATH ?>/projects" class="with-icon <?= $pageactive('projects') ?>">
                                <i class="ph ph-tree-structure" aria-hidden="true"></i>
                                <?= lang('Projects', 'Projekte') ?>
                            </a>

                            <?php if ($Settings->featureEnabled('nagoya') && $Settings->hasPermission('nagoya.view')) { ?>
                                <a href="<?= ROOTPATH ?>/nagoya" class="with-icon <?= $pageactive('nagoya') ?>">
                                    <i class="ph ph-scales" aria-hidden="true"></i>
                                    <?= lang('Nagoya Protocol', 'Nagoya-Protokoll') ?>
                                </a>
                            <?php } ?>

                        <?php } ?>


                        <a href="<?= ROOTPATH ?>/journal" class="with-icon <?= $pageactive('journal') ?>">
                            <i class="ph ph-stack" aria-hidden="true"></i>
                            <?= lang('Journals', 'Journale') ?>
                        </a>

                        <a href="<?= ROOTPATH ?>/conferences" class="with-icon <?= $pageactive('conferences') ?>">
                            <i class="ph ph-calendar-dots" aria-hidden="true"></i>
                            <?= lang('Events') ?>
                        </a>

                        <a href="<?= ROOTPATH ?>/teaching" class="with-icon <?= $pageactive('teaching') ?>">
                            <i class="ph ph-chalkboard-simple" aria-hidden="true"></i>
                            <?= lang('Teaching modules', 'Lehrmodule') ?>
                        </a>

                        <?php if ($Settings->featureEnabled('topics')) { ?>
                            <a href="<?= ROOTPATH ?>/topics" class="with-icon <?= $pageactive('topics') ?>">
                                <i class="ph ph-puzzle-piece" aria-hidden="true"></i>
                                <?= $Settings->topicLabel() ?>
                            </a>
                        <?php } ?>

                        <?php if ($Settings->featureEnabled('infrastructures')) { ?>
                            <a href="<?= ROOTPATH ?>/infrastructures" class="with-icon <?= $pageactive('infrastructures') ?>">
                                <i class="ph ph-cube-transparent" aria-hidden="true"></i>
                                <?= lang('Infrastructures', 'Infrastrukturen') ?>
                            </a>
                        <?php } ?>

                        <!-- <a href="<?= ROOTPATH ?>/tags" class="with-icon <?= $pageactive('tags') ?>">
                            <i class="ph ph-circles-three-plus" aria-hidden="true"></i>
                            <?= lang('Tags', 'Schlagwörter') ?>
                        </a> -->

                        <?php if ($Settings->featureEnabled('concepts')) { ?>
                            <a href="<?= ROOTPATH ?>/concepts" class="with-icon <?= $pageactive('concepts') ?>">
                                <i class="ph ph-lightbulb" aria-hidden="true"></i>
                                <?= lang('Concepts', 'Konzepte') ?>
                            </a>
                        <?php } ?>

                    </nav>


                    <div class="title collapse open" onclick="toggleSidebar(this);" id="sidebar-users">
                        <?= lang('Users', 'Personen') ?>
                    </div>

                    <nav>
                        <?php
                        $active =  $pageactive('user/browse');
                        if (empty($active) && !str_contains($uri, "profile/" . $_SESSION['username'])) {
                            $active = $pageactive('profile');
                        }
                        ?>

                        <a href="<?= ROOTPATH ?>/user/browse" class="with-icon <?= $active ?>">
                            <i class="ph ph-users" aria-hidden="true"></i>
                            <?= lang('Users', 'Personen') ?>
                        </a>
                        <a href="<?= ROOTPATH ?>/groups" class="with-icon <?= $pageactive('groups') ?>">
                            <i class="ph ph-users-three" aria-hidden="true"></i>
                            <?= lang('Organisational Units', 'Einheiten') ?>
                        </a>

                        <a href="<?= ROOTPATH ?>/organizations" class="with-icon <?= $pageactive('organizations') ?>">
                            <i class="ph ph-building-office" aria-hidden="true"></i>
                            <?= lang('Organizations', 'Organisationen') ?>
                        </a>

                        <!-- <a href="<?= ROOTPATH ?>/expertise" class="with-icon <?= $pageactive('expertise') ?>">
                            <i class="ph ph-barbell" aria-hidden="true"></i>
                            <?= lang('Expertise search', 'Expertise-Suche') ?>
                        </a> -->

                        <?php if ($Settings->featureEnabled('guests')) { ?>
                            <a href="<?= ROOTPATH ?>/guests" class="with-icon <?= $pageactive('guests') ?>">
                                <i class="ph ph-user-switch" aria-hidden="true"></i>
                                <?= lang('Guests', 'Gäste') ?>
                            </a>
                        <?php } ?>

                    </nav>

                    <div class="title collapse open" onclick="toggleSidebar(this);" id="sidebar-tools">
                        <?= lang('Visualization', 'Visualisierung') ?>
                    </div>
                    <nav>
                        <!-- <a href="<?= ROOTPATH ?>/activities/search" class="with-icon <?= $pageactive('activities/search') ?>">
                            <i class="ph ph-magnifying-glass-plus" aria-hidden="true"></i>
                            <?= lang('Advanced search', 'Erweiterte Suche') ?>
                        </a> -->

                        <a href="<?= ROOTPATH ?>/dashboard" class="with-icon <?= $pageactive('dashboard') ?>">
                            <i class="ph ph-chart-line" aria-hidden="true"></i>
                            <?= lang('Dashboard') ?>
                        </a>

                        <a href="<?= ROOTPATH ?>/visualize" class="with-icon <?= $pageactive('visualize') ?>">
                            <i class="ph ph-graph" aria-hidden="true"></i>
                            <?= lang('Visualizations', 'Visualisierung') ?>
                        </a>

                        <a href="<?= ROOTPATH ?>/pivot" class="with-icon <?= $pageactive('pivot') ?>">
                            <i class="ph ph-table" aria-hidden="true"></i>
                            <?= lang('Pivot table', 'Pivot-Tabelle') ?>
                        </a>
                    </nav>


                    <div class="title collapse open" onclick="toggleSidebar(this);" id="sidebar-export">
                        <?= lang('Export &amp; Import') ?>
                    </div>
                    <nav>

                        <a href="<?= ROOTPATH ?>/download" class="with-icon <?= $pageactive('download') ?>">
                            <i class="ph ph-download" aria-hidden="true"></i>
                            Export <?= lang('Activities', 'Aktivitäten') ?>
                        </a>

                        <a href="<?= ROOTPATH ?>/cart" class="with-icon <?= $pageactive('cart') ?>">
                            <i class="ph ph-shopping-cart" aria-hidden="true"></i>
                            <?= lang('Cart', 'Einkaufswagen') ?>
                            <?php
                            $cart = readCart();
                            if (!empty($cart)) { ?>
                                <span class="badge secondary badge-pill ml-10" id="cart-counter">
                                    <?= count($cart) ?>
                                </span>
                            <?php } else { ?>
                                <span class="badge secondary badge-pill ml-10 hidden" id="cart-counter">
                                    0
                                </span>
                            <?php } ?>
                        </a>
                        <a href="<?= ROOTPATH ?>/import" class="with-icon <?= $pageactive('import') ?>">
                            <i class="ph ph-upload" aria-hidden="true"></i>
                            <?= lang('Import') ?>
                        </a>


                        <?php if ($Settings->hasPermission('report.queue')) { ?>
                            <?php
                            $n_queue = $osiris->queue->count(['declined' => ['$ne' => true]]);
                            ?>

                            <a href="<?= ROOTPATH ?>/queue/editor" class="sidebar-link with-icon sidebar-link-osiris <?= $pageactive('queue/editor') ?>">
                                <i class="ph ph-queue" aria-hidden="true"></i>
                                <?= lang('Queue', 'Warteschlange') ?>
                                <span class="badge secondary badge-pill ml-10" id="cart-counter">
                                    <?= $n_queue ?>
                                </span>
                            </a>
                        <?php } ?>


                        <?php if ($Settings->hasPermission('report.generate')) { ?>

                            <a href="<?= ROOTPATH ?>/reports" class="with-icon <?= $pageactive('reports') ?>">
                                <i class="ph ph-printer" aria-hidden="true"></i>

                                <?= lang('Reports', 'Berichte') ?>
                            </a>

                            <?php if ($Settings->featureEnabled('ida')) { ?>
                                <a href="<?= ROOTPATH ?>/ida/dashboard" class="with-icon <?= $pageactive('ida') ?>">
                                    <i class="ph ph-clipboard-text" aria-hidden="true"></i>
                                    <?= lang('IDA-Integration') ?>
                                </a>
                            <?php } ?>

                        <?php } ?>

                    </nav>

                <?php } ?>



                <?php if ($Settings->hasPermission('admin.see') || $Settings->hasPermission('report.templates') || $Settings->hasPermission('user.synchronize')) { ?>
                    <div class="title collapse open" onclick="toggleSidebar(this);" id="sidebar-admin">
                        ADMIN
                    </div>
                    <nav>
                        <?php if ($Settings->hasPermission('admin.see')) { ?>
                            <a href="<?= ROOTPATH ?>/admin/general" class="with-icon <?= $pageactive('admin/general') ?>">
                                <i class="ph ph-gear" aria-hidden="true"></i>
                                <?= lang('General settings', 'Einstellungen') ?>
                            </a>
                            <a href="<?= ROOTPATH ?>/admin/roles" class="with-icon <?= $pageactive('admin/roles') ?>">
                                <i class="ph ph-shield-check" aria-hidden="true"></i>
                                <?= lang('Roles &amp; Rights', 'Rollen &amp; Rechte') ?>
                            </a>

                            <a href="<?= ROOTPATH ?>/admin" class="with-icon <?= $pageactive('admin') ?>">
                                <i class="ph ph-treasure-chest" aria-hidden="true"></i>
                                <?= lang('Contents', 'Inhalte') ?>
                            </a>

                            <a href="<?= ROOTPATH ?>/admin/features" class="with-icon <?= $pageactive('admin/features') ?>">
                                <i class="ph ph-wrench" aria-hidden="true"></i>
                                <?= lang('Features', 'Funktionen') ?>
                            </a>
                        <?php } ?>


                        <?php if ($Settings->hasPermission('report.templates')) { ?>
                            <a href="<?= ROOTPATH ?>/admin/reports" class="with-icon <?= $pageactive('admin/reports') ?>">
                                <i class="ph ph-clipboard-text"></i>
                                <?= lang('Report templates', 'Berichte-Vorlagen') ?>
                            </a>
                        <?php } ?>
                        <?php if ($Settings->hasPermission('user.synchronize')) { ?>
                            <a href="<?= ROOTPATH ?>/admin/users" class="with-icon <?= $pageactive('admin/users') ?>">
                                <i class="ph ph-users"></i>
                                <?= lang('Users', 'Nutzer') ?>
                            </a>
                        <?php } ?>
                    </nav>
                <?php } ?>



            </div>
        </div>

        <script>
            function toggleSidebar(el) {
                el = $(el)
                let id = el.attr('id')
                let hide = el.hasClass('open')

                el.next().slideToggle();
                el.toggleClass('open');

                window.sessionStorage.setItem(id, hide);
            }

            $(function() {
                $('.title.collapse').each(function(n, el) {
                    var hide = window.sessionStorage.getItem($(el).attr('id'));
                    if (hide == 'true') {
                        $(el).removeClass('open')
                        $(el).next().hide()
                    }
                })
            });
        </script>

        <!-- Content wrapper start -->
        <div class="content-wrapper">
            <?php if ($pageactive('preview')) { ?>
                <div class="title-bar text-danger text-center font-weight-bold d-block font-size-20">
                    <b>PREVIEW</b>
                </div>
            <?php } ?>


            <div class="content-container">
                <?php
                if (function_exists('printMsg') && (isset($_GET['msg']) || isset($_GET['error'])) || isset($_SESSION['msg'])) {
                    printMsg();
                }

                if ($Settings->hasPermission('admin.give-right') && isset($Settings->errors) && !empty($Settings->errors)) {
                ?>
                    <div class="alert danger mb-20">
                        <h3 class="title">There are errors in your settings:</h3>
                        <?= implode('<br>', $Settings->errors) ?>
                        <br>
                        Default settings are used. Go to the <a href="<?= ROOTPATH ?>/admin/general">Admin Panel</a> to fix this.
                    </div>
                <?php
                }
                ?>