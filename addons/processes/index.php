<?php

/**
 * Add-on "Processes" (Vorgänge): administrative processes and publication
 * planning, separate from the OSIRIS core. See README.md.
 *
 * Included by index.php for logged-in users. Routes only answer if the
 * feature "processes" is enabled.
 */

define('PROC_PATH', BASEPATH . '/addons/processes');

require_once PROC_PATH . '/php/Conditions.php';
require_once PROC_PATH . '/php/Form.php';
require_once PROC_PATH . '/php/Files.php';
require_once PROC_PATH . '/php/Notify.php';
require_once PROC_PATH . '/php/Processes.php';

// hooks for the core: sidebar sections and "My tasks"
$GLOBALS['OSIRIS_ADDON_HOOKS']['sidebar'][] = ['Processes', 'sidebarGroups'];
$GLOBALS['OSIRIS_ADDON_HOOKS']['tasks'][] = ['Processes', 'tasks'];

/** Init, check the feature and return the add-on. */
function proc_init(): Processes
{
    global $Settings;
    include_once BASEPATH . '/php/init.php';
    $P = Processes::get();
    if (!$P->enabled()) abortwith(404, lang('Page', 'Seite'));
    return $P;
}

/** Load a case and check that the user may see it. */
function proc_case(Processes $P, $id): array
{
    $case = $P->getCase($id);
    if (empty($case) || empty($P->type($case['type']))) abortwith(404, lang('Case', 'Vorgang'));
    $perm = $P->permissions($case);
    if (!$perm['view']) abortwith(403, lang('You do not have permission to see this case.', 'Du hast keine Berechtigung, diesen Vorgang zu sehen.'));
    return [$case, $perm];
}

function proc_page($page, $vars = [], $breadcrumb = [])
{
    global $osiris, $Settings, $DB, $USER, $Groups, $Departments, $Categories;
    extract($vars);
    include BASEPATH . '/header.php';
    include PROC_PATH . '/pages/' . $page . '.php';
    include BASEPATH . '/footer.php';
}

function proc_back($url, $msg = null, $type = 'success')
{
    if ($msg) {
        $_SESSION['msg'] = $msg;
        $_SESSION['msg_type'] = $type;
    }
    header('Location: ' . ROOTPATH . $url);
    die;
}

// overview of definitions and errors, for admins
Route::get('/processes/check', function () {
    $P = proc_init();
    if (!$P->Settings->hasPermission('admin.see')) abortwith(403);
    proc_page('check', ['P' => $P], [['name' => lang('Processes', 'Vorgänge')], ['name' => lang('Definitions', 'Definitionen')]]);
}, 'login');

Route::get('/processes/view/([0-9a-f]{24})', function ($id) {
    $P = proc_init();
    [$case, $perm] = proc_case($P, $id);
    $type = $P->type($case['type']);
    $area = $P->area($case['area']);
    $page = ProcessPhases::isPhases($type) ? 'view-phases' : 'view';
    proc_page($page, ['P' => $P, 'case' => $case, 'perm' => $perm, 'type' => $type, 'area' => $area], [
        ['name' => Processes::t($area), 'path' => '/processes/' . $case['area']],
        ['name' => $case['number'] ?? lang('Draft', 'Entwurf')],
    ]);
}, 'login');

Route::get('/processes/edit/([0-9a-f]{24})', function ($id) {
    $P = proc_init();
    [$case, $perm] = proc_case($P, $id);
    if (!$perm['edit']) abortwith(403, lang('You cannot edit this case.', 'Du kannst diesen Vorgang nicht bearbeiten.'));
    $type = $P->type($case['type']);
    $area = $P->area($case['area']);
    proc_page('form', ['P' => $P, 'case' => $case, 'perm' => $perm, 'type' => $type, 'area' => $area], [
        ['name' => Processes::t($area), 'path' => '/processes/' . $case['area']],
        ['name' => $case['number'] ?? lang('Draft', 'Entwurf'), 'path' => '/processes/view/' . $id],
        ['name' => lang('Edit', 'Bearbeiten')],
    ]);
}, 'login');

Route::get('/processes/([a-z0-9-]+)/new/([a-z0-9-]+)', function ($areaId, $typeId) {
    $P = proc_init();
    $type = $P->type($typeId);
    if (empty($type) || $type['area'] != $areaId) abortwith(404, lang('Process type', 'Vorgangsart'));
    if (!$P->canCreate($type)) abortwith(403, lang('You cannot create this kind of case.', 'Du kannst diese Art von Vorgang nicht anlegen.'));
    $area = $P->area($areaId);
    proc_page('form', ['P' => $P, 'case' => null, 'perm' => null, 'type' => $type, 'area' => $area], [
        ['name' => Processes::t($area), 'path' => '/processes/' . $areaId],
        ['name' => lang('New', 'Neu'), 'path' => '/processes/' . $areaId . '/new'],
        ['name' => Processes::t($type, 'name')],
    ]);
}, 'login');

Route::get('/processes/([a-z0-9-]+)/new', function ($areaId) {
    $P = proc_init();
    $area = $P->area($areaId);
    if (empty($area)) abortwith(404, lang('Area', 'Bereich'));
    $types = array_filter($P->types($areaId), function ($t) use ($P) {
        return $P->canCreate($t);
    });
    proc_page('catalog', ['P' => $P, 'area' => $area, 'types' => $types], [
        ['name' => Processes::t($area), 'path' => '/processes/' . $areaId],
        ['name' => lang('New', 'Neu')],
    ]);
}, 'login');

Route::get('/processes/([a-z0-9-]+)', function ($areaId) {
    $P = proc_init();
    $area = $P->area($areaId);
    if (empty($area)) abortwith(404, lang('Area', 'Bereich'));
    $access = $P->areaAccess($areaId);
    $view = $_GET['view'] ?? 'mine';
    if (!in_array($view, ['mine', 'todo', 'all', 'overview'])) $view = 'mine';
    if ($view == 'overview') {
        if (!$access['overview']) abortwith(403, lang('You cannot see the overview.', 'Du kannst die Übersicht nicht sehen.'));
        proc_page('overview', ['P' => $P, 'area' => $area, 'access' => $access, 'cases' => $P->listCases($areaId, 'all')], [
            ['name' => Processes::t($area), 'path' => '/processes/' . $areaId],
            ['name' => lang('Overview', 'Übersicht')],
        ]);
        return;
    }
    $cases = $P->listCases($areaId, $view);
    proc_page('list', ['P' => $P, 'area' => $area, 'access' => $access, 'view' => $view, 'cases' => $cases], [
        ['name' => Processes::t($area)],
    ]);
}, 'login');

// create or update the values of a case
Route::post('/processes/save', function () {
    $P = proc_init();
    Processes::csrfCheck();
    $post = $_POST['values'] ?? [];
    if (!is_array($post)) $post = [];
    $submit = ($_POST['submit'] ?? '') == '1';

    if (!empty($_POST['id'])) {
        [$case, $perm] = proc_case($P, $_POST['id']);
        if (!$perm['edit']) abortwith(403, lang('You cannot edit this case.', 'Du kannst diesen Vorgang nicht bearbeiten.'));
        $type = $P->type($case['type']);
        $editable = $perm['edit_fields'];
        if (ProcessPhases::isPhases($type) && !empty($_POST['section'])) {
            // only the fields of the edited section, the others stay as they are
            $editable = array_intersect($editable, array_column(ProcessPhases::sectionFields($type, (string) $_POST['section']), 'id'));
        }
        $values = ProcessForm::parse($type, $post, $editable, DB::doc2Arr($case['values'] ?? []), $P);
        $openBefore = ProcessPhases::isPhases($type) ? ProcessPhases::myOpenTasksAll($P, $case) : [];
        $P->updateValues($case, $values, trim((string) ($_POST['comment'] ?? '')) ?: null);
        $id = strval($case['_id']);
        if (ProcessPhases::isPhases($type)) {
            ProcessNotify::afterPhaseAction($P, $P->getCase($id), ['action' => 'edited'], $openBefore);
        }
    } else {
        $type = $P->type($_POST['type'] ?? '');
        if (empty($type) || !$P->canCreate($type)) abortwith(403, lang('You cannot create this kind of case.', 'Du kannst diese Art von Vorgang nicht anlegen.'));
        $editable = array_values(array_filter(ProcessForm::fieldIds($type), function ($fid) use ($type) {
            return empty(ProcessForm::field($type, $fid)['only_steps']);
        }));
        if (ProcessPhases::isPhases($type)) {
            $editable = array_intersect($editable, array_column(ProcessPhases::sectionFields($type, 'base'), 'id'));
        }
        $values = ProcessForm::parse($type, $post, $editable, [], $P);
        $id = $P->create($type, $values, $_POST['start_phase'] ?? null);
    }

    if ($submit) {
        $case = $P->getCase($id);
        $error = $P->act($case, 'submit');
        if ($error) proc_back("/processes/edit/$id", $error, 'error');
        proc_back("/processes/view/$id", lang('The case has been submitted.', 'Der Vorgang wurde eingereicht.'));
    }
    $anchor = !empty($_POST['section']) ? '#phase-' . preg_replace('/[^a-z0-9_]/', '', $_POST['section']) : '';
    proc_back("/processes/view/$id$anchor", lang('Saved.', 'Gespeichert.'));
}, 'login');

Route::post('/processes/action/([0-9a-f]{24})', function ($id) {
    $P = proc_init();
    Processes::csrfCheck();
    [$case, $perm] = proc_case($P, $id);
    $action = $_POST['action'] ?? '';
    $error = $P->act($case, $action, $_POST['comment'] ?? '', $_POST['target'] ?? null);
    if ($error) proc_back("/processes/view/$id", $error, 'error');
    if ($action == 'delete') proc_back('/processes/' . $case['area'], lang('The draft has been deleted.', 'Der Entwurf wurde gelöscht.'));
    $done = [
        'submit' => lang('The case has been submitted.', 'Der Vorgang wurde eingereicht.'),
        'approve' => lang('Approved.', 'Freigegeben.'),
        'return' => lang('The case has been returned.', 'Der Vorgang wurde zurückgegeben.'),
        'reject' => lang('The case has been rejected.', 'Der Vorgang wurde abgelehnt.'),
        'withdraw' => lang('The case has been withdrawn.', 'Der Vorgang wurde zurückgezogen.'),
        'phase' => lang('The phase has been changed.', 'Die Phase wurde geändert.'),
        'status' => lang('The status has been changed.', 'Der Status wurde geändert.'),
        'task_done' => lang('The task has been marked as done.', 'Die Aufgabe ist als erledigt markiert.'),
        'task_reopen' => lang('The task has been reopened.', 'Die Aufgabe ist wieder offen.'),
    ];
    proc_back("/processes/view/$id", $done[$action] ?? null);
}, 'login');

Route::post('/processes/files/([0-9a-f]{24})', function ($id) {
    $P = proc_init();
    Processes::csrfCheck();
    [$case, $perm] = proc_case($P, $id);
    if (!$perm['upload']) abortwith(403, lang('You cannot add files to this case.', 'Du kannst diesem Vorgang keine Dateien hinzufügen.'));
    $error = ProcessFiles::upload($P, $case, $_FILES['file'] ?? null);
    if ($error) proc_back("/processes/view/$id#files", $error, 'error');
    proc_back("/processes/view/$id#files", lang('The file has been added.', 'Die Datei wurde hinzugefügt.'));
}, 'login');

Route::post('/processes/files/([0-9a-f]{24})/([0-9a-f]{24})/delete', function ($id, $fileId) {
    $P = proc_init();
    Processes::csrfCheck();
    [$case, $perm] = proc_case($P, $id);
    $error = ProcessFiles::delete($P, $case, $fileId);
    if ($error) proc_back("/processes/view/$id#files", $error, 'error');
    proc_back("/processes/view/$id#files", lang('The file has been deleted.', 'Die Datei wurde gelöscht.'));
}, 'login');

Route::get('/processes/files/([0-9a-f]{24})/([0-9a-f]{24})', function ($id, $fileId) {
    $P = proc_init();
    [$case, $perm] = proc_case($P, $id);
    ProcessFiles::download($P, $case, $fileId);
}, 'login');
