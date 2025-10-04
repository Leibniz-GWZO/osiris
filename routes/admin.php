<?php

/**
 * Routing file for admin settings
 * 
 * This file is part of the OSIRIS package.
 * Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 *
 * @package     OSIRIS
 * @since       1.3.0
 * 
 * @copyright	Copyright (c) 2024 Julia Koblitz, OSIRIS Solutions GmbH
 * @author		Julia Koblitz <julia.koblitz@osiris-solutions.de>
 * @license     MIT
 */

Route::get('/admin', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang("Manage Content", "Inhalte verwalten")],
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/content.php";
    include BASEPATH . "/footer.php";
}, 'login');


include_once BASEPATH . "/routes/admin.fields.php";


Route::get('/admin/users', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('user.synchronize')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Users", "Nutzer:innen")]
    ];
    $page = 'users';
    include BASEPATH . "/header.php";
    if (strtoupper(USER_MANAGEMENT) == 'LDAP') {
        include BASEPATH . "/pages/synchronize-users.php";
    } else {
        include BASEPATH . "/pages/admin/users.php";
    }

    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/(general|roles|features)', function ($page) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Admin'), 'path' => '/admin'],
        ['name' => ucfirst($page)]
    ];

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/$page.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/roles/distribute', function () {
    include_once BASEPATH . "/php/init.php";
    $page = 'admin/roles';
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Roles", "Rollen"), 'path' => '/admin/roles'],
        ['name' => lang("Distribute roles", "Rollen verteilen")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/distribute-roles.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/templates', function () {
    include_once BASEPATH . "/php/init.php";
    $breadcrumb = [
        ['name' => lang("Activities", "Aktivitäts-Typen"), 'path' => "/admin/categories"],
    ];

    $type = null;
    $template = '';
    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $type = $_GET['type'];
        $typeArr = $osiris->adminTypes->findOne(['id' => $type]);
        if (!empty($typeArr)) {
            $breadcrumb[] = ['name' => lang($typeArr['name'], $typeArr['name_de']), 'path' => "/admin/types/$type"];

            $templates = $typeArr['template'];
            $template = $templates['print'];
        }
    }
    $breadcrumb[] = ['name' => lang("Templates", "Vorlagen")];

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/template-builder.php";
    include BASEPATH . "/footer.php";
}, 'login');

Route::get('/admin/module-helper', function () {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Modules.php";
    $breadcrumb = [
        ['name' => lang("Activities", "Aktivitäten"), 'path' => "/admin/categories"],
        ['name' => lang("New", "Neu")],
        ['name' => lang("Data fields", "Datenfelder")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/module-helper.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/types/(.*)/fields', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $type = $osiris->adminTypes->findOne(['id' => $id]);
    if (empty($type)) {
        header("Location: " . ROOTPATH . "/admin/categories?msg=not-found");
        die;
    }
    $name = lang($type['name'], $type['name_de']);

    $t = $type['parent'];
    $parent = $osiris->adminCategories->findOne(['id' => $t]);
    $color = $parent['color'] ?? '#000000';
    $st = $type['id'];
    $submember = $osiris->activities->count(['type' => $t, 'subtype' => $st]);

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang($parent['name'], $parent['name_de']), 'path' => "/admin/categories/" . $t],
        ['name' => $name, 'path' => "/admin/types/" . $id],
        ['name' => lang("Data fields", "Datenfelder")]
    ];

    global $form;
    $form = DB::doc2Arr($type);

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/form-builder.php";
    include BASEPATH . "/footer.php";
}, 'login');

Route::get('/admin/categories', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $user = $_SESSION['username'];
    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Activities", "Aktivitäten")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/categories.php";
    include BASEPATH . "/footer.php";
}, 'login');

Route::get('/admin/categories/new', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $user = $_SESSION['username'];
    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Activities", "Aktivitäten"), 'path' => "/admin/categories"],
        ['name' => lang("New", "Neu")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/category.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/categories/(.*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $user = $_SESSION['username'];

    $category = $osiris->adminCategories->findOne(['id' => $id]);
    if (empty($category) && is_numeric($id)) {
        // try if it id is saved as integer
        $category = $osiris->adminCategories->findOne(['id' => intval($id)]);
    }
    if (empty($category)) {
        header("Location: " . ROOTPATH . "/admin/categories?msg=not-found");
        die;
    }
    $name = lang($category['name'], $category['name_de']);
    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Activities", "Aktivitäten"), 'path' => "/admin/categories"],
        ['name' => $name]
    ];

    global $form;
    $form = DB::doc2Arr($category);

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/category.php";
    include BASEPATH . "/footer.php";
}, 'login');



Route::get('/admin/types/new', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $user = $_SESSION['username'];

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Activities", "Aktivitäten"), 'path' => "/admin/categories"],
        ['name' => lang("New Type", "Neuer Typ")]
    ];
    $t = $_GET['parent'] ?? '';
    $st = $t;
    $type = [
        "id" => '',
        "icon" => $type['icon'] ?? 'placeholder',
        "name" => '',
        "name_de" => '',
        "new" => true,
        "modules" => [
            "title",
            "authors",
            "date"
        ],
        "template" => [
            "print" => "{authors} ({year}) {title}.",
            "title" => "{title}",
            "subtitle" => "{authors}, {date}"
        ],
        "coins" => 0,
        "parent" => $t

    ];

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/category-type.php";
    include BASEPATH . "/footer.php";
}, 'login');



Route::get('/admin/types/(.*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $user = $_SESSION['username'];

    $type = $osiris->adminTypes->findOne(['id' => $id]);
    if (empty($type)) {
        header("Location: " . ROOTPATH . "/admin/categories?msg=not-found");
        die;
    }
    $name = lang($type['name'], $type['name_de']);

    $t = $type['parent'];
    $parent = $osiris->adminCategories->findOne(['id' => $t]);
    $color = $parent['color'] ?? '#000000';
    $st = $type['id'];
    $submember = $osiris->activities->count(['type' => $t, 'subtype' => $st]);

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Activities", "Aktivitäten"), 'path' => "/admin/categories"],
        ['name' => lang($parent['name'], $parent['name_de']), 'path' => "/admin/categories/" . $t],
        ['name' => $name]
    ];

    global $form;
    $form = DB::doc2Arr($type);

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/category-type.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/settings/activities', function () {
    include_once BASEPATH . "/php/init.php";

    $t = $_GET['type'];
    $type = $osiris->adminTypes->findOne(['id' => $t]);
    if (empty($type)) {
        // try if it is a category with otherwise named children
        $type = $osiris->adminTypes->findOne(['parent' => $t]);
    }
    if (empty($type)) {
        header("Location: " . ROOTPATH . "/admin/categories?msg=not-found");
        die;
    }
    $parent = $osiris->adminCategories->findone(['id' => $type['parent']]);
    echo return_rest([
        'category' => DB::doc2Arr($parent),
        'type' => DB::doc2Arr($type)
    ]);
});


Route::get('/admin/vocabulary', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Vocabulary", "Vokabular")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/vocabulary.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/settings/modules', function () {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Modules.php";

    $form = array();
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $mongoid = $DB->to_ObjectID($_GET['id']);
        $form = $osiris->activities->findOne(['_id' => $mongoid]);
    }
    $Modules = new Modules($form, $_GET['copy'] ?? false, $_GET['conference'] ?? false);

    if (isset($_GET['type']) && !empty($_GET['type'])) {
        // new in 1.5.1
        $Modules->print_form($_GET['type']);
    } else if (isset($_GET['modules'])) {
        $Modules->print_modules($_GET['modules']);
    }
});



Route::get('/admin/persons', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Persons", "Personen")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/persons.php";
    include BASEPATH . "/footer.php";
}, 'login');

Route::get('/admin/infrastructures', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Infrastructures", "Infrastrukturen")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/infrastructures.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/projects', function () {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Project.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Projects", "Projekte")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/projects.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/projects/([123])/(.*)', function ($stage, $id) {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Project.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (DB::is_ObjectID($id)) {
        $project = $osiris->adminProjects->findOne(['_id' => $DB->to_ObjectID($id)]);
    } else {
        $project = $osiris->adminProjects->findOne(['id' => $id]);
    }
    if (empty($project)) {
        $project = array();
    } else {
        $project = DB::doc2Arr($project);
    }
    $type = $project['id'];

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Projects", "Projekte"), 'path' => '/admin/projects'],
        ['name' => $type . ' - ' . $stage . '/2']
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/project.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/projects/new', function () {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Project.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $stage = 1;
    $project = array();
    $type = null;

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Projects", "Projekte"), 'path' => '/admin/projects'],
        ['name' => lang('New project type', 'Neuer Projekttyp') . ' - ' . $stage . '/2']
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/project.php";
    include BASEPATH . "/footer.php";
}, 'login');


// workflows

Route::get('/admin/workflows', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Workflows", "Workflows")]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/workflows.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/admin/workflows/new', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $form = [];
    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Workflows", "Workflows"), 'path' => '/admin/workflows'],
        ['name' => lang('New workflow', 'Neuer Workflow')]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/workflow-new.php";
    include BASEPATH . "/footer.php";
}, 'login');

Route::get('/admin/workflows/(.*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $form = $osiris->adminWorkflows->findOne(['id' => $id]);
    if (empty($form)) {
        header("Location: " . ROOTPATH . "/fields?msg=not-found");
        die;
    }
    $name = $form['name'];

    $breadcrumb = [
        ['name' => lang('Content', 'Inhalte'), 'path' => '/admin'],
        ['name' => lang("Workflows", "Workflows"), 'path' => '/admin/workflows'],
        ['name' => lang('New workflow', 'Neuer Workflow')]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/admin/workflow.php";
    include BASEPATH . "/footer.php";
}, 'login');



/**
 * CRUD routes
 */

Route::post('/crud/admin/general', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $msg = 'settings-saved';
    if (isset($_POST['general'])) {
        foreach ($_POST['general'] as $key => $value) {
            dump($key);
            if ($key == 'auth-self-registration') $value = boolval($value);
            if (str_contains($key, 'keywords')) {
                $value = array_map('trim', explode(PHP_EOL, $value));
                $value = array_filter($value);
            }
            $osiris->adminGeneral->deleteOne(['key' => $key]);
            $osiris->adminGeneral->insertOne([
                'key' => $key,
                'value' => $value
            ]);
        }
    }
    if (isset($_POST['mail'])) {

        $osiris->adminGeneral->deleteOne(['key' => 'mail']);
        $osiris->adminGeneral->insertOne([
            'key' => 'mail',
            'value' => $_POST['mail']
        ]);
    }

    if (isset($_POST['footer_links'])) {
        $links = [];
        // join the name, name_de and url into an array
        if (isset($_POST['footer_links']['name']) && is_array($_POST['footer_links']['name'])) {
            $names = $_POST['footer_links']['name'];
            $names_de = $_POST['footer_links']['name_de'] ?? $names;
            $urls = $_POST['footer_links']['url'] ?? [];

            foreach ($names as $i => $name) {
                if (empty($name) || empty($urls[$i])) continue; // skip empty links
                $links[] = [
                    'name' => $name,
                    'name_de' => $names_de[$i] ?? $name,
                    'url' => $urls[$i]
                ];
            }
        }
        $osiris->adminGeneral->deleteOne(['key' => 'footer_links']);
        $osiris->adminGeneral->insertOne([
            'key' => 'footer_links',
            'value' => $links
        ]);
    }

    if (isset($_POST['staff'])) {
        $staff = [];
        if (isset($_POST['staff']['free'])) {
            $staff['free'] = boolval($_POST['staff']['free']);
        }
        if (isset($_POST['staff']['positions']) && !empty($_POST['staff']['positions'])) {
            $en = $_POST['staff']['positions'];
            $de = $_POST['staff']['positions_de'] ?? $en;

            $staff['positions'] = [];
            foreach ($en as $i => $e) {
                $staff['positions'][] = [
                    $e,
                    $de[$i] ?? $e
                ];
            }
        }
        $osiris->adminGeneral->deleteOne(['key' => 'staff']);
        $osiris->adminGeneral->insertOne([
            'key' => 'staff',
            'value' => $staff
        ]);
    }


    if (isset($_FILES["logo"])) {
        $filename = htmlspecialchars(basename($_FILES["logo"]["name"]));
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filesize = $_FILES["logo"]["size"];

        if ($_FILES['logo']['error'] != UPLOAD_ERR_OK) {
            $msg = match ($_FILES['logo']['error']) {
                1 => lang('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'Die hochgeladene Datei überschreitet die Richtlinie upload_max_filesize in php.ini'),
                2 => lang("File is too big: max 16 MB is allowed.", "Die Datei ist zu groß: maximal 16 MB sind erlaubt."),
                3 => lang('The uploaded file was only partially uploaded.', 'Die hochgeladene Datei wurde nur teilweise hochgeladen.'),
                4 => lang('No file was uploaded.', 'Es wurde keine Datei hochgeladen.'),
                6 => lang('Missing a temporary folder.', 'Der temporäre Ordner fehlt.'),
                7 => lang('Failed to write file to disk.', 'Datei konnte nicht auf die Festplatte geschrieben werden.'),
                8 => lang('A PHP extension stopped the file upload.', 'Eine PHP-Erweiterung hat den Datei-Upload gestoppt.'),
                default => lang('Something went wrong.', 'Etwas ist schiefgelaufen.') . " (" . $_FILES['file']['error'] . ")"
            };
        } else if ($filesize > 2000000) {
            $msg = lang("File is too big: max 2 MB is allowed.", "Die Datei ist zu groß: maximal 2 MB sind erlaubt.");
        } else {
            $val = new MongoDB\BSON\Binary(file_get_contents($_FILES["logo"]["tmp_name"]), MongoDB\BSON\Binary::TYPE_GENERIC);
            // first: delete logo, then: insert new one
            $osiris->adminGeneral->deleteOne(['key' => 'logo']);
            $updateResult = $osiris->adminGeneral->insertOne([
                'key' => 'logo',
                'value' => $val,
                'ext' => $filetype
            ]);
        }
    }

    if (isset($_POST['redirect'])) {
        header("Location: " . $_POST['redirect'] . "?msg=" . $msg);
        die();
    }

    header("Location: " . ROOTPATH . "/admin/general?msg=" . $msg);
}, 'login');


Route::post('/crud/admin/roles', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (isset($_POST['values'])) {
        $osiris->adminRights->deleteMany([]);
        $rights = $_POST['values'];
        foreach ($rights as $right => $roles) {
            foreach ($roles as $role => $perm) {
                $r = [
                    'role' => $role,
                    'right' => $right,
                    'value' => boolval($perm)
                ];
                $osiris->adminRights->insertOne($r);
            }
        }
    }
    if (isset($_POST['roles']) && is_array($_POST['roles']) && count($_POST['roles']) > 2) {
        // user, scientist and admin must always be there
        if (!in_array('user', $_POST['roles'])) {
            $_POST['roles'][] = 'user';
        }
        if (!in_array('scientist', $_POST['roles'])) {
            $_POST['roles'][] = 'scientist';
        }
        if (!in_array('admin', $_POST['roles'])) {
            $_POST['roles'][] = 'admin';
        }
        $osiris->adminGeneral->deleteOne(['key' => 'roles']);
        $osiris->adminGeneral->insertOne([
            'key' => 'roles',
            'value' => array_map('strtolower', $_POST['roles'])
        ]);
    }

    $msg = 'settings-saved';

    header("Location: " . ROOTPATH . "/admin/roles?msg=" . $msg);
}, 'login');


Route::post('/crud/admin/update-user-roles', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $roles = $_POST['roles'] ?? [];
    if (empty($roles) || !is_array($roles)) {
        header("Location: " . ROOTPATH . "/admin/roles/distribute?msg=no-roles");
        die;
    }
    // get all admins to not remove admin role
    $admins = $osiris->persons->find(['roles' => 'admin'])->toArray();
    $admin_users = array_map(fn($a) => $a['username'], $admins);
    foreach ($roles as $user => $r) {
        if (!is_array($r)) $r = [];
        // check if user is admin
        if (in_array($user, $admin_users) && !in_array('admin', $r)) {
            $r[] = 'admin';
        }
        $osiris->persons->updateOne(
            ['username' => $user],
            ['$set' => ['roles' => array_map('strtolower', $r)]]
        );
    }
    $_SESSION['msg'] = lang('Roles updated successfully.', 'Rollen erfolgreich aktualisiert.');
    $_SESSION['msg_type'] = 'success';
    header("Location: " . ROOTPATH . "/admin/roles/distribute");
    die;
}, 'login');


Route::post('/crud/admin/features', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');


    if (isset($_POST['values'])) {
        $features = $_POST['values'];
        foreach ($features as $feature => $enabled) {
            $osiris->adminFeatures->deleteOne(['feature' => $feature]);
            $r = [
                'feature' => $feature,
                'enabled' => boolval($enabled)
            ];
            $osiris->adminFeatures->insertOne($r);
        }
    }

    if (isset($_POST['general'])) {
        foreach ($_POST['general'] as $key => $value) {
            if (isset($value['en']) && $value['de'] == '') {
                $value['de'] = $value['en'];
            }
            $osiris->adminGeneral->deleteOne(['key' => $key]);
            $osiris->adminGeneral->insertOne([
                'key' => $key,
                'value' => $value
            ]);
        }
    }

    $msg = 'settings-saved';

    header("Location: " . ROOTPATH . "/admin/general?msg=" . $msg . '#features');
}, 'login');


Route::post('/crud/(categories|types)/create', function ($col) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (!isset($_POST['values'])) die("no values given");

    $values = validateValues($_POST['values'], $DB);

    // if (isset($values['upload'])) $values

    if ($col == 'categories') {
        $collection = $osiris->adminCategories;
    } else {
        $collection = $osiris->adminTypes;
        if (!isset($values['parent'])) {
            header("Location: " . ROOTPATH . "/types/new?msg=Type must have a parent category.");
            die();
        }
    }

    // check if category ID already exists:
    $category_exist = $collection->findOne(['id' => $values['id']]);
    if (!empty($category_exist)) {
        header("Location: " . ROOTPATH . "/$col/new?msg=Category ID does already exist.");
        die();
    }

    // add fields
    $values['modules'] = [
        "title*",
        "authors*",
        "date*"
    ];

    $insertOneResult  = $collection->insertOne($values);
    // $id = $insertOneResult->getInsertedId();
    $id = $values['id'];

    if (isset($_POST['redirect']) && !str_contains($_POST['redirect'], "//")) {
        $red = str_replace("*", $id, $_POST['redirect']);
        header("Location: " . $red . "?msg=success");
        die();
    }

    echo json_encode([
        'inserted' => $insertOneResult->getInsertedCount(),
        'id' => $id,
    ]);
});

Route::post('/crud/(categories|types)/update/([A-Za-z0-9]*)', function ($col, $id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (!isset($_POST['values'])) die("no values given");
    $values = validateValues($_POST['values'], $DB);

    // if (isset($values['upload'])) $values

    if ($col == 'categories') {
        $collection = $osiris->adminCategories;
        $key = 'type';
    } else {
        $collection = $osiris->adminTypes;
        $key = 'subtype';
        // types need a categorie a.k.a. parent
        if (!isset($values['parent'])) {
            die("Type must have a parent category.");
        }
    }


    // check if ID has changed
    if (isset($_POST['original_id']) && $_POST['original_id'] != $values['id']) {
        // update all connected activities 
        $osiris->activities->updateMany(
            [$key => $_POST['original_id']],
            ['$set' => [$key => $values['id']]]
        );
        $_POST['redirect'] = ROOTPATH . "/admin/types/" . $values['id'];

        if ($col == 'categories') {
            // update all connected types
            $osiris->adminTypes->updateMany(
                ['parent' => $_POST['original_id']],
                ['$set' => ['parent' => $values['id']]]
            );
            $_POST['redirect'] = ROOTPATH . "/admin/categories/" . $values['id'];
        }
    }

    if ($col == 'types') {
        // check if parent has changed
        if (isset($_POST['original_parent']) && $_POST['original_parent'] != $values['parent']) {
            // update all connected activities 
            $osiris->activities->updateMany(
                ['type' => $_POST['original_parent'], 'subtype' => $values['id']],
                ['$set' => ['type' => $values['parent']]]
            );
        }
        // checkbox default
        $values['disabled'] = $values['disabled'] ?? false;
    }

    // add information on updating process
    $values['updated'] = date('Y-m-d');
    $values['updated_by'] = $_SESSION['username'];

    $mongo_id = $DB->to_ObjectID($id);
    $updateResult = $collection->updateOne(
        ['_id' => $mongo_id],
        ['$set' => $values]
    );

    if (isset($_POST['redirect']) && !str_contains($_POST['redirect'], "//")) {
        header("Location: " . $_POST['redirect'] . "?msg=update-success");
        die();
    }

    echo json_encode([
        'inserted' => $updateResult->getModifiedCount(),
        'id' => $id,
    ]);
});

Route::post('/crud/(categories|types)/delete/(.*)', function ($col, $id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    // select the right collection

    if ($col == 'categories') {
        $collection = $osiris->adminCategories;
        $member = $osiris->activities->count(['type' => $id]);
    } else {
        $collection = $osiris->adminTypes;
        $member = $osiris->activities->count(['subtype' => $id]);
    }

    // check that no activities are connected
    if ($member !== 0) die('Cannot delete as long as activities are connected.');

    // prepare id
    $updateResult = $collection->deleteOne(
        ['id' => $id]
    );
    if ($col == 'categories') {
        $osiris->adminTypes->deleteMany(['parent' => $id]);
    }

    $deletedCount = $updateResult->getDeletedCount();

    // addUserActivity('delete');
    if (isset($_POST['redirect']) && !str_contains($_POST['redirect'], "//")) {
        header("Location: " . $_POST['redirect'] . "?msg=deleted-" . $deletedCount);
        die();
    }
    echo json_encode([
        'deleted' => $deletedCount
    ]);
});


Route::post('/crud/(categories|types)/update-order', function ($col) {
    include_once BASEPATH . "/php/init.php";
    // select the right collection
    if ($col == 'categories') {
        $collection = $osiris->adminCategories;
    } else {
        $collection = $osiris->adminTypes;
    }

    foreach ($_POST['order'] as $i => $id) {
        $collection->updateOne(
            ['id' => $id],
            ['$set' => ['order' => $i]]
        );
    }

    $_SESSION['msg'] = lang("Order updated", "Reihenfolge aktualisiert");
    if (isset($_POST['redirect']) && !str_contains($_POST['redirect'], "//")) {
        header("Location: " . $_POST['redirect']);
        die();
    }
});

Route::post('/crud/admin/activity-fields', function () {
    include_once BASEPATH . "/php/init.php";

    $type = $_POST['activityType'] ?? null;
    if (empty($type)) {
        die("No activity type given.");
    }
    $schema = $_POST['schema'] ?? null;
    if (empty($schema)) {
        die("No schema given.");
    }

    $schema = json_decode($schema, true);
    $fields = $schema['items'];
    if (empty($fields)) {
        die("No fields given.");
    }
    $modules = [];
    foreach ($fields as $field) {
        if ($field['type'] != 'field' && $field['type'] != 'custom') {
            // skip non-field types
            continue;
        }
        $f = $field['id'];
        if (isset($field['overrides']) && isset($field['overrides']['required']) && $field['overrides']['required']) {
            $f .= '*';
        }
        $modules[] = $f;
    }

    $osiris->adminTypes->updateOne(
        ['id' => $type],
        ['$set' => [
            'modules' => $modules,
            'fields' => $fields
        ]]
    );
    // redirect back
    $_SESSION['msg'] = lang("Activity form has been updated", "Aktivitätsformular wurde aktualisiert");
    $_SESSION['msg_type'] = 'success';
    header("Location: " . ROOTPATH . "/admin/types/$type/fields");
    die();
});

// <!-- Test Email Settings by sending a test mail -->
// // /crud/admin/mail-test

Route::post('/crud/admin/mail-test', function () {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/MailSender.php";

    // include_once BASEPATH . "/php/mail.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $to = $_POST['email'];

    sendMail($to, 'OSIRIS Test Mail', 'This is a test mail from the OSIRIS system. If you received this mail, everything is set up correctly.');

    header("Location: " . ROOTPATH . "/admin/general?msg=" . $msg);
}, 'login');


// crud/admin/add-user

Route::post('/crud/admin/add-user', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('user.synchronize')) die('You have no permission to be here.');


    if ($osiris->persons->count(['username' => $_POST['username']]) > 0) {
        $msg = lang("The username is already taken. Please try again.", "Der Nutzername ist bereits vergeben. Versuche es erneut.");
        include BASEPATH . "/header.php";
        printMsg($msg, 'error');
        $form = $_POST;
        include BASEPATH . "/pages/admin/users.php";
        include BASEPATH . "/footer.php";
        die;
    }

    $person = $_POST['values'];
    $person['username'] = $_POST['username'];

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $osiris->accounts->insertOne([
        'username' => $person['username'],
        'password' => $password
    ]);

    $person['displayname'] = "$person[first] $person[last]";
    $person['formalname'] = "$person[last], $person[first]";
    $person['first_abbr'] = "";
    foreach (explode(" ", $person['first']) as $name) {
        $person['first_abbr'] .= " " . $name[0] . ".";
    }
    $person['created'] = date('d.m.Y');
    $person['roles'] = array_keys($person['roles'] ?? []);

    $person['new'] = true;
    $person['is_active'] = true;

    $osiris->persons->insertOne($person);

    header("Location: " . ROOTPATH . "/admin/users?success=" . $person['username']);
}, 'login');


Route::post('/crud/admin/projects/create', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (!isset($_POST['values'])) die("no values given");

    $values = validateValues($_POST['values'], $DB);

    $collection = $osiris->adminProjects;

    // check if category ID already exists:
    $category_exist = $collection->findOne(['id' => $values['id']]);
    if (!empty($category_exist)) {
        header("Location: " . ROOTPATH . "/admin/projects?msg=ID does already exist.");
        die();
    }

    $insertOneResult  = $collection->insertOne($values);
    // $id = $insertOneResult->getInsertedId();
    $id = $values['id'];

    $_SESSION['msg'] = lang("Project <q>$id</q> successfully created.", "Projekt <q>$id</q> erfolgreich erstellt.");
    header("Location: " . ROOTPATH . "/admin/projects/2/$id");
    die();
});

Route::post('/crud/admin/projects/update/([A-Za-z0-9]*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    include_once BASEPATH . "/php/Project.php";
    $Project = new Project();

    $collection = $osiris->adminProjects;
    $mongo_id = $DB->to_ObjectID($id);

    $original = $collection->findOne(['_id' => $mongo_id]);
    if (empty($original)) {
        header("Location: " . ROOTPATH . "/admin/projects?msg=not-found");
        die;
    }
    $name = lang($original['name'] ?? $original['id'], $original['name_de'] ?? null);

    $stage = $_POST['stage'] ?? 1;

    $values = validateValues($_POST['values'] ?? [], $DB);
    $values['updated'] = date('Y-m-d');
    $values['updated_by'] = $_SESSION['username'];
    $values['stage'] = $stage;

    if ($stage == 1) {
        // first stage: update basic information
        if ($original['id'] != $values['id']) {
            // update all connected projects with this type 
            $osiris->projects->updateMany(
                ['type' => $original['id']],
                ['$set' => ['type' => $values['id']]]
            );
        }

        $values['disabled'] = boolval($values['disabled'] ?? false);
        $values['notification_changed_email'] = boolval($values['notification_changed_email'] ?? false);
        $values['notification_created_email'] = boolval($values['notification_created_email'] ?? false);

        $updateResult = $collection->updateOne(
            ['_id' => $mongo_id],
            ['$set' => $values]
        );

        if (isset($values['disabled']) && $values['disabled']) {
            $_SESSION['msg'] = lang("Deactivated project <q>$name</q> successfully saved.", "Deaktiviertes projekt <q>$name</q> erfolgreich gespeichert.");
            header("Location: " . ROOTPATH . "/admin/projects");
            die;
        }

        header("Location: " . ROOTPATH . "/admin/projects/2/$id");
        die;
    } elseif ($stage == 2) {

        if (!isset($_POST['phase'])) {
            // save empty phases
            $values['phases'] = [];
            $updateResult = $collection->updateOne(
                ['_id' => $mongo_id],
                ['$set' => $values]
            );
            $_SESSION['msg'] = lang("Project <q>$name</q> successfully saved.", "Projekt <q>$name</q> erfolgreich gespeichert.");
            header("Location: " . ROOTPATH . "/admin/projects");
            die;
        }

        $phases = $_POST['phase'];

        $values['phases'] = [];
        foreach ($Project::PHASES as $phase) {
            $phase_id = $phase['id'];
            // if projects are created directly, skip proposal phase
            if ($original['process'] == 'project' && $phase['type'] == 'proposal') {
                continue;
            }
            // check if pahse was not selected, if so create it with empty modules
            if (!isset($phases[$phase_id])) {
                $phases[$phase_id] = [
                    'modules' => [],
                ];
            }
            // add modules to phase
            $modules = [];
            foreach ($phases[$phase_id]['modules'] ?? [] as $m) {
                if (str_ends_with($m, '*')) {
                    $m = substr($m, 0, -1);
                    $modules[] = [
                        'module' => $m,
                        'required' => true
                    ];
                } else {
                    $modules[] = [
                        'module' => $m,
                        'required' => false
                    ];
                }
            }
            // add phase to values
            $values['phases'][] = [
                'id' => $phase_id,
                'name' => $phase['name'],
                'name_de' => $phase['name_de'],
                'color' => $phase['color'] ?? 'muted',
                'modules' => $modules
            ];
        }

        $updateResult = $collection->updateOne(
            ['_id' => $mongo_id],
            ['$set' => $values]
        );


        $_SESSION['msg'] = lang("Project <q>$name</q> successfully saved.", "Projekt <q>$name</q> erfolgreich gespeichert.");
        header("Location: " . ROOTPATH . "/admin/projects");
        // header("Location: " . ROOTPATH . "/admin/projects/3/$id");
        die;
    }
    dump($values, true);
    die;
});



Route::post('/crud/admin/projects/delete/([A-Za-z0-9]*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $collection = $osiris->adminProjects;
    $mongo_id = $DB->to_ObjectID($id);

    // check if ID is in use
    $project = $collection->findOne(['_id' => $mongo_id]);
    if (empty($project)) {
        $_SESSION['msg'] = lang("Project <q>$id</q> could not be deleted as it does not exist.", "Projekt <q>$id</q> konnte nicht gelöscht werden, da es nicht existiert.");
        header("Location: " . ROOTPATH . "/admin/projects");
        die();
    }
    $project_id = $project['id'];

    if ($osiris->projects->count(['type' => $project_id]) > 0) {
        $_SESSION['msg'] = lang("Project <q>$project_id</q> could not be deleted, projects are still associated to this type.", "Projekt <q>$project_id</q> konnte nicht gelöscht werden, da Projekte noch mit diesem Typ verbunden sind.");
        header("Location: " . ROOTPATH . "/admin/projects");
        die();
    }

    $deleted = $collection->deleteOne(['_id' => $mongo_id]);
    if ($deleted->getDeletedCount() == 0) {
        $_SESSION['msg'] = lang("Project <q>$project_id</q> could not be deleted.", "Projekt <q>$project_id</q> konnte nicht gelöscht werden.");
        header("Location: " . ROOTPATH . "/admin/projects");
        die();
    }

    $_SESSION['msg'] = lang("Project <q>$project_id</q> successfully deleted.", "Projekt <q>$project_id</q> erfolgreich gelöscht.");
    header("Location: " . ROOTPATH . "/admin/projects");
    die();
});

Route::post('/crud/admin/vocabularies/([a-z\-]*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (!isset($_POST['values'])) die("no values given");
    $doc = [
        'id' => $id,
        'values' => $_POST['values']
    ];

    // delete old vocabulary
    $osiris->adminVocabularies->deleteOne(['id' => $id]);
    // insert new vocabulary
    $osiris->adminVocabularies->insertOne($doc);

    $_SESSION['msg'] = lang(
        "Vocabulary <q>$id</q> successfully saved.",
        "Vokabular <q>$id</q> erfolgreich gespeichert."
    );

    $red = ROOTPATH . "/admin/vocabulary#vocabulary-$id";
    header("Location: " . $red);
});



Route::post('/crud/workflows/create', function () {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (!isset($_POST['values'])) die("no values given");

    $values = validateValues($_POST['values'], $DB);

    // check if category ID already exists:
    $workflow_exist = $osiris->adminWorkflows->findOne(['id' => $values['id']]);
    if (!empty($workflow_exist)) {
        $_SESSION['msg'] = lang('Workflow ID does already exist.', 'Die Workflow-ID wird bereits verwendet.');
        $_SESSION['msg_type'] = "error";
        header("Location: " . ROOTPATH . "/workflows/new");
        die();
    }

    $osiris->adminWorkflows->insertOne($values);

    header("Location: " . ROOTPATH . "/admin/workflows?msg=success");
});

Route::post('/crud/workflows/update/(.*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    if (!isset($_POST['values'])) die("no values given");
    $values = validateValues($_POST['values'], $DB);

    /**
     * Helpers
     */
    function toBool($v): bool
    {
        // Checkboxen senden nur was bei "checked"
        return !empty($v) && $v !== '0' && $v !== 0 && $v !== false;
    }

    function cleanText($s, $max = 120): string
    {
        $s = is_string($s) ? $s : '';
        $s = trim($s);
        $s = strip_tags($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return mb_substr($s, 0, $max);
    }

    function slugify($s, $max = 50): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
        $s = trim($s, '-');
        if ($s === '') $s = 'step';
        return mb_substr($s, 0, $max);
    }

    /**
     * Normalizer für Steps
     * Erwartet $rawSteps als numerisch indiziertes Array aus $_POST['values']['steps']
     * $allowedRoles = Array der erlaubten Rollen (Strings)
     */
    function normalizeWorkflowSteps(array $rawSteps, array $allowedRoles): array
    {
        $out = [];
        $seenIds = [];

        // Fallback-Role
        $defaultRole = $allowedRoles[0] ?? 'user';

        foreach ($rawSteps as $i => $s) {
            // label (required)
            $label = cleanText($s['label'] ?? '');
            if ($label === '') {
                continue;
            } // leere Zeilen überspringen

            // index (Phase)
            $index = isset($s['index']) ? intval($s['index']) : 0;
            if ($index < 0) $index = 0;

            // role
            $role = cleanText($s['role'] ?? $defaultRole, 60);
            if (!in_array($role, $allowedRoles, true)) {
                $role = $defaultRole;
            }

            // orgScope
            $scope = ($s['orgScope'] ?? 'any') === 'same_org_only' ? 'same_org_only' : 'any';

            // booleans
            $required = toBool($s['required'] ?? 0);
            $locksAfter = toBool($s['locksAfterApproval'] ?? 0);

            // id generieren (stabil, eindeutig)
            // Falls du später ein verstecktes Feld [id] einführst, dann: $id = cleanText($s['id'] ?? '', 64)
            $baseId = slugify($label);
            $id = $baseId;
            $suffix = 2;
            while (isset($seenIds[$id])) {
                $id = $baseId . '-' . $suffix++;
            }
            $seenIds[$id] = true;

            $out[] = [
                'id' => $id,
                'label' => $label,
                'index' => $index,
                'role' => $role,
                'orgScope' => $scope,                 // 'any' | 'same_org_only'
                'required' => $required,              // bool
                'locksAfterApproval' => $locksAfter,  // bool
            ];
        }

        // stabile Sortierung nach index, dann originale Reihenfolge
        usort($out, function ($a, $b) {
            if ($a['index'] === $b['index']) return 0;
            return $a['index'] <=> $b['index'];
        });

        // Reindex numerische Keys
        return array_values($out);
    }

    $req = $osiris->adminGeneral->findOne(['key' => 'roles']);
    $allowedRoles = DB::doc2Arr($req['value'] ?? ['user', 'scientist', 'admin']);

    $values = $_POST['values'] ?? [];
    $stepsNorm = normalizeWorkflowSteps($values['steps'] ?? [], $allowedRoles);

    $doc = [
        'name' => cleanText($values['name'] ?? '', 120),
        'steps' => $stepsNorm
    ];

    $osiris->adminWorkflows->updateOne(
        ['id' => $id],
        ['$set' => $doc],
        ['upsert' => false]
    );

    header("Location: " . ROOTPATH . "/admin/workflows?msg=success");
});

Route::post('/crud/workflows/delete/(.*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    $mongo_id = DB::to_ObjectID($id);
    $updateResult = $osiris->adminWorkflows->deleteOne(
        ['_id' => $mongo_id]
    );

    header("Location: " . ROOTPATH . "/admin/workflows?msg=success");
});

Route::post('/crud/workflows/apply/(.*)', function ($wfId) {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Workflows.php";
    if (!$Settings->hasPermission('admin.see')) die('You have no permission to be here.');

    // Expected POST: category, mode=attach-missing, dryrun(bool), from(YYYY-MM-DD|null), to(YYYY-MM-DD|null)
    $template = $osiris->adminWorkflows->findOne(['id' => $wfId]);
    if (!$template) return JSON::error('Workflow not found', 404);

    $template = DB::doc2Arr($template);
    if (empty($template['steps'])) return JSON::error('Workflow has no steps defined', 400);



    $category = $_POST['category'] ?? null;
    $mode = $_POST['mode'] ?? 'attach-missing';
    $dryrun = isset($_POST['dryrun']) && ($_POST['dryrun'] === '1' || $_POST['dryrun'] === 'true' || $_POST['dryrun'] === 1 || $_POST['dryrun'] === true);
    $from = $_POST['from'] ?? null;
    $to = $_POST['to'] ?? null;

    if (!$category) return JSON::error('Category required', 400);
    if ($mode !== 'attach-missing') return JSON::error('Unsupported mode', 400);

    // Filter
    // gemeinsame Where-Klauseln
    $base = ['type' => $category];
    if ($from) $base['created']['$gte'] = $from;
    if ($to)   $base['created']['$lte'] = $to;

    // update filter (attach missing only)
    $filter = [
        '$and' => [$base, ['$or' => [['workflow' => ['$exists' => false]], ['workflow' => null]]]]
    ];
    // counts
    $total         = $osiris->activities->countDocuments($base);
    $withWorkflow  = $osiris->activities->countDocuments($base + ['workflow' => ['$ne' => null]]);
    $missingOrNull = $osiris->activities->countDocuments($filter);
    $withoutWorkflow = $missingOrNull;


    if ($dryrun) {
        JSON::ok([
            'total' => $total,
            'withWorkflow' => $withWorkflow,
            'withoutWorkflow' => $withoutWorkflow,
            'willUpdate' => $withoutWorkflow
        ]);
        return;
    }

    // apply
    $snapshot = Workflows::buildSnapshot($template);
    $updateResult = $osiris->activities->updateMany(
        $filter + ['workflow' => ['$exists' => false]],
        ['$set' => [
            'workflow' => $snapshot
        ]]
    );

    JSON::ok([
        'updatedCount' => $updateResult->getModifiedCount(),
        'skippedCount' => $total - $updateResult->getModifiedCount(),
        'total' => $total,
        'withWorkflow' => $withWorkflow + $updateResult->getModifiedCount(),
        'withoutWorkflow' => max(0, $withoutWorkflow - $updateResult->getModifiedCount())
    ]);
});

// POST /crud/activities/workflow/approve/{id}
Route::post('/crud/activities/workflow/approve/(.*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Workflows.php";

    $stepId = $_POST['stepId'] ?? null;
    if (!$stepId) return JSON::error('stepId required', 400);

    $act = $osiris->activities->findOne(['_id' => $DB->to_ObjectID($id)]);
    if (!$act || empty($act['workflow'])) return JSON::error('No workflow', 404);
    $act = DB::doc2Arr($act);
    
    $units = DB::doc2Arr($USER['units'] ?? []);
    if (!empty($units)) {
        $units = array_column($units, 'unit');
    }
    $user = [
        'username' => $_SESSION['username'] ?? null,
        'roles'    => $Settings->roles ?? [],
        'orgIds'   => $units
    ];

    try {
        $wf = Workflows::approveStep($act, DB::doc2Arr($act['workflow']), $stepId, $user);
    } catch (RuntimeException $e) {
        return JSON::error($e->getMessage(), 403);
    }

    $osiris->activities->updateOne(['_id' => $act['_id']], ['$set' => ['workflow' => $wf]]);
    JSON::ok(['workflow_status' => $wf['status'], 'locked' => !empty($wf['isLocked'])]);
});

// POST /crud/activities/workflow/reject/{id}
Route::post('/crud/activities/workflow/reject/(.*)', function ($id) {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/Workflows.php";

    $stepId = $_POST['stepId'] ?? null;
    $comment = trim($_POST['comment'] ?? '');
    if (!$stepId) return JSON::error('stepId required', 400);

    $act = $osiris->activities->findOne(['_id' => $DB->to_ObjectID($id)]);
    if (!$act || empty($act['workflow'])) return JSON::error('No workflow', 404);

    // simple: jeder Pending-Step darf rejected werden, Permission-Check kannst du analog zu approve einbauen
    $wf = DB::doc2Arr($act['workflow']);
    foreach ($wf['steps'] as &$s) {
        if ($s['step_id'] === $stepId && ($s['state'] ?? 'pending') === 'pending') {
            $s['state'] = 'rejected';
            $s['comment'] = $comment;
            $s['approvedBy'] = $_SESSION['username'] ?? null;
            $s['approvedAt'] = Workflows::nowIso();
        }
    }
    unset($s);

    $wf = Workflows::refreshAssignments($wf);
    $wf = Workflows::recomputeStatus($wf);
    $osiris->activities->updateOne(['_id' => $act['_id']], ['$set' => ['workflow' => $wf]]);
    JSON::ok(['workflow_status' => $wf['status'], 'locked' => !empty($wf['isLocked'])]);
});
