<?php

/**
 * Routes for internal tickets (feedback, bug reports, change requests)
 *
 * This file is part of the OSIRIS package.
 *
 * @package     OSIRIS
 * @license     MIT
 */

function tickets_init()
{
    global $Settings;
    if (!$Settings->featureEnabled('tickets')) {
        abortwith(404, lang('Tickets', 'Tickets'));
    }
    include_once BASEPATH . "/php/Tickets.php";
    return new Tickets($Settings);
}

function tickets_load(Tickets $T, $id)
{
    $ticket = $T->get($id);
    if (empty($ticket)) abortwith(404, lang('Ticket', 'Ticket'), ROOTPATH . '/tickets');
    if (!$T->canView($ticket)) abortwith(403, lang('Ticket', 'Ticket'), ROOTPATH . '/tickets');
    return $ticket;
}

Route::get('/tickets', function () {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();

    $views = $T->canManage() ? ['open', 'assigned', 'all', 'mine'] : ['mine'];
    if (!$T->canManage() && $T->publicEnabled()) $views[] = 'public';
    $view = in_array($_GET['view'] ?? '', $views) ? $_GET['view'] : $views[0];
    $filter = match ($view) {
        'open' => ['status' => ['$in' => Tickets::OPEN]],
        'assigned' => ['assigned_to' => $T->user, 'status' => ['$in' => Tickets::OPEN]],
        'all' => [],
        'public' => $T->publicFilter(),
        default => ['$or' => [['created_by' => $T->user], ['followers' => $T->user]]],
    };
    $tickets = $T->db->tickets->find($filter, [
        'sort' => ['updated_at' => -1],
        'projection' => ['comments' => 0, 'history' => 0, 'files' => 0, 'description' => 0],
    ])->toArray();

    $breadcrumb = [
        ['name' => lang('Tickets', 'Tickets')]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/tickets/list.php";
    include BASEPATH . "/footer.php";
});

Route::get('/tickets/new', function () {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();

    $ticketPage = Tickets::cleanPage($_GET['page'] ?? '');
    if ($ticketPage && str_starts_with($ticketPage, '/tickets')) $ticketPage = null;
    $ticketCategory = $_GET['category'] ?? null;

    $breadcrumb = [
        ['path' => '/tickets', 'name' => lang('Tickets', 'Tickets')],
        ['name' => lang('New ticket', 'Neues Ticket')]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/tickets/new.php";
    include BASEPATH . "/footer.php";
});

Route::get('/tickets/view/([0-9a-f]{24})', function ($id) {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();
    $ticket = tickets_load($T, $id);

    $breadcrumb = [
        ['path' => '/tickets', 'name' => lang('Tickets', 'Tickets')],
        ['name' => '#' . intval($ticket['number'])]
    ];
    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/tickets/view.php";
    include BASEPATH . "/footer.php";
});

Route::get('/tickets/similar', function () {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($T->similar(mb_substr((string) ($_GET['q'] ?? ''), 0, 200)));
});

Route::get('/tickets/files/([0-9a-f]{24})/([0-9a-f]{24})', function ($id, $fileId) {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();
    $ticket = tickets_load($T, $id);
    $T->sendFile($ticket, $fileId);
});


Route::post('/crud/tickets/create', function () {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();

    $values = $_POST['ticket'] ?? [];
    if (empty(trim($values['title'] ?? '')) || empty(trim($values['description'] ?? ''))) {
        $_SESSION['msg'] = lang('Please enter a title and a description.', 'Bitte gib einen Titel und eine Beschreibung ein.');
        $_SESSION['msg_type'] = 'error';
        header('Location: ' . ROOTPATH . '/tickets/new');
        die;
    }
    $ticket = $T->create($values);

    $errors = [];
    foreach (Tickets::uploadedFiles($_FILES['files'] ?? null) as $file) {
        $error = $T->addFile($ticket, $file);
        if ($error) $errors[] = $error;
    }

    $T->notify(
        $T->managers(),
        $ticket,
        'New ticket from ' . e($DB->getNameFromId($T->user)) . ': %t',
        'Neues Ticket von ' . e($DB->getNameFromId($T->user)) . ': %t',
        $ticket['description']
    );

    if (!empty($errors)) {
        $_SESSION['msg'] = implode('<br>', $errors);
        $_SESSION['msg_type'] = 'error';
    } else {
        $_SESSION['msg'] = lang('Thank you! Your ticket has been submitted. You will be notified about updates.', 'Danke! Dein Ticket wurde eingereicht. Du wirst über Neuigkeiten benachrichtigt.');
        $_SESSION['msg_type'] = 'success';
    }
    header('Location: ' . ROOTPATH . '/tickets/view/' . strval($ticket['_id']));
    die;
});

Route::post('/crud/tickets/comment/([0-9a-f]{24})', function ($id) {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();
    $ticket = tickets_load($T, $id);

    $text = trim($_POST['comment'] ?? '');
    $internal = $T->canManage() && !empty($_POST['internal']);
    $files = Tickets::uploadedFiles($_FILES['files'] ?? null);
    if ($text === '' && empty($files)) {
        header('Location: ' . ROOTPATH . '/tickets/view/' . $id);
        die;
    }
    if ($text !== '') $T->comment($ticket, $text, $internal);

    $errors = [];
    foreach ($files as $file) {
        $error = $T->addFile($ticket, $file);
        if ($error) $errors[] = $error;
    }

    if ($text === '') {
        // only files, no notification
    } else if ($internal) {
        // internal notes only go to the assignee
        if (!empty($ticket['assigned_to'])) {
            $T->notify([$ticket['assigned_to']], $ticket, 'Internal note on %t', 'Interne Notiz zu %t', $text);
        }
    } else if ($T->canManage()) {
        $T->notify($T->audience($ticket), $ticket, 'New reply on %t', 'Neue Antwort zu %t', $text);
    } else {
        $to = !empty($ticket['assigned_to']) ? [$ticket['assigned_to']] : $T->managers();
        // someone who joined the ticket comments: the reporter wants to know
        $to[] = $ticket['created_by'];
        $T->notify($to, $ticket, 'New reply on %t', 'Neue Antwort zu %t', $text);
    }

    if (!empty($errors)) {
        $_SESSION['msg'] = implode('<br>', $errors);
        $_SESSION['msg_type'] = 'error';
    }
    header('Location: ' . ROOTPATH . '/tickets/view/' . $id . '#comments');
    die;
});

Route::post('/crud/tickets/update/([0-9a-f]{24})', function ($id) {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();
    $ticket = tickets_load($T, $id);
    if (!$T->canManage()) abortwith(403, lang('Ticket', 'Ticket'));

    $changes = $T->update($ticket, $_POST['ticket'] ?? []);

    if (isset($changes['status'])) {
        $status = $changes['status'][1];
        $T->notify(
            $T->audience($ticket),
            $ticket,
            'New status of %t: ' . Tickets::statusLabel($status, 'en'),
            'Neuer Status von %t: ' . Tickets::statusLabel($status, 'de')
        );
    }
    if (!empty($changes['assigned_to'][1])) {
        $T->notify([$changes['assigned_to'][1]], $ticket, 'Ticket %t has been assigned to you', 'Das Ticket %t wurde dir zugewiesen', $ticket['description']);
    }

    $_SESSION['msg'] = empty($changes) ? lang('Nothing changed.', 'Nichts geändert.') : lang('Ticket updated.', 'Ticket aktualisiert.');
    $_SESSION['msg_type'] = empty($changes) ? 'info' : 'success';
    header('Location: ' . ROOTPATH . '/tickets/view/' . $id);
    die;
});

Route::post('/crud/tickets/follow/([0-9a-f]{24})', function ($id) {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();
    $ticket = tickets_load($T, $id);
    if (!$T->isPublic($ticket)) abortwith(403, lang('Ticket', 'Ticket'));

    $follow = !empty($_POST['follow']);
    $T->follow($ticket, $follow);
    $_SESSION['msg'] = $follow
        ? lang('You have joined this ticket and will be notified about its progress.', 'Du hast dich angeschlossen und wirst über den Fortschritt benachrichtigt.')
        : lang('You have left this ticket.', 'Du bist nicht mehr dabei.');
    $_SESSION['msg_type'] = 'success';
    header('Location: ' . ROOTPATH . '/tickets/view/' . $id);
    die;
});

Route::post('/crud/tickets/delete/([0-9a-f]{24})', function ($id) {
    include_once BASEPATH . "/php/init.php";
    $T = tickets_init();
    $ticket = tickets_load($T, $id);
    if (!$T->canManage()) abortwith(403, lang('Ticket', 'Ticket'));

    $T->delete($ticket);
    $_SESSION['msg'] = lang('Ticket deleted.', 'Ticket gelöscht.');
    $_SESSION['msg_type'] = 'success';
    header('Location: ' . ROOTPATH . '/tickets');
    die;
});
