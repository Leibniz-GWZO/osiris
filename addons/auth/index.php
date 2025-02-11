<?php

Route::get('/auth/new-user', function () {
    include_once BASEPATH . "/php/init.php";
    include BASEPATH . "/header.php";
    include BASEPATH . "/addons/auth/add-user.php";
    include BASEPATH . "/footer.php";
});


Route::get('/auth/forgot-password', function () {
    include_once BASEPATH . "/php/init.php";
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true  && isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        header("Location: " . ROOTPATH . "/profile/$_SESSION[username]");
        die;
    }
    $breadcrumb = [
        ['name' => lang('Forgot password', 'Passwort vergessen')]
    ];
    include BASEPATH . "/header.php";

    include BASEPATH . "/addons/auth/forgot-password.php";
    include BASEPATH . "/footer.php";
});

Route::post('/auth/forgot-password', function () {
    include_once BASEPATH . "/php/init.php";
    include_once BASEPATH . "/php/MailSender.php";
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true  && isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        header("Location: " . ROOTPATH . "/profile/$_SESSION[username]");
        die;
    }

    if (isset($_POST['mail'])) {
        $user = $osiris->persons->findOne(['mail' => $_POST['mail']]);
        if (empty($user)) {
            $_SESSION['msg'] = lang('If the mail address is correct, you will receive an email with further instructions.', 'Wenn die Mail-Adresse korrekt ist, erhältst du eine E-Mail mit weiteren Anweisungen.');
            header("Location: " . ROOTPATH . "/user/login");
            die;
        }

        // check if user has recently requested a password reset
        $account = $osiris->accounts->findOne(['username' => $user['username']]);
        if (!empty($account) && isset($account['reset']) && $account['reset'] > time() - 10 * 60) {
            $_SESSION['msg'] = lang('You have recently requested a password reset. Please wait a few minutes.', 'Du hast vor kurzem ein Passwort zurücksetzen angefordert. Bitte warte ein paar Minuten.');
            header("Location: " . ROOTPATH . "/auth/forgot-password");
            die;
        }

        // generate hash for password reset
        $hash = md5($user['username'] . time());
        $osiris->accounts->updateOne(
            ['username' => $user['username']],
            ['$set' => ['reset' => time(), 'hash' => $hash]]
        );


        $link = $_SERVER['HTTP_HOST'] . ROOTPATH . "/auth/reset-password?hash=$hash";
        // send mail
        sendMail(
            $user['mail'],
            lang('Password reset', 'Passwort zurücksetzen'),
            lang(
                'You have requested a password reset from OSIRIS. Please click the following link to reset your password:', 
            'Du hast ein in OSIRIS Passwort zurücksetzen angefordert. Bitte klicke auf den folgenden Link, um dein Passwort zurückzusetzen:') . 
            "<br><a href='" . $link . "'>$link</a><br>" .
            lang('If you did not request a password reset, please ignore this email.', 'Wenn du kein Passwort zurücksetzen angefordert hast, ignoriere diese E-Mail.')
        );

        $_SESSION['msg'] = lang('If the mail address is correct, you will receive an email with further instructions.', 'Wenn die Mail-Adresse korrekt ist, erhältst du eine E-Mail mit weiteren Anweisungen.');
        header("Location: " . ROOTPATH . "/user/login");
    }
});

Route::get('/auth/reset-password', function(){
    include_once BASEPATH . "/php/init.php";

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true  && isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        header("Location: " . ROOTPATH . "/profile/$_SESSION[username]");
        die;
    }

    // check if hash is valid
    $hash = $_GET['hash'];
    $account = $osiris->accounts->findOne(['hash' => $hash]);
    if (empty($account)) {
        $_SESSION['msg'] = lang('The link is not valid. Please request a new password reset.', 'Der Link ist nicht gültig. Bitte fordere einen neuen Passwort zurücksetzen an.');
        header("Location: " . ROOTPATH . "/auth/forgot-password");
        die;
    }

    // check if reset is still valid
    if ($account['reset'] < time() - 24 * 60 * 60) {
        // remove hash
        $osiris->accounts->updateOne(
            ['hash' => $hash],
            ['$unset' => ['hash' => '']]
        );
        $_SESSION['msg'] = lang('The link has expired. Please request a new password reset.', 'Der Link ist abgelaufen. Bitte fordere einen neuen Passwort zurücksetzen an.');
        header("Location: " . ROOTPATH . "/auth/forgot-password");
        die;
    }

    $user = $osiris->persons->findOne(['username' => $account['username']]);
    $breadcrumb = [
        ['name' => lang('Reset password', 'Passwort zurücksetzen')]
    ];
    include BASEPATH . "/header.php";
    ?>
     <form action="#" method="post">
        <input type="hidden" name="hash" value="<?= $hash ?>">
        <div class="form-group">
            <label class="required" for="password"><?= lang('New password', 'Neues Password') ?></label>
            <input class="form-control" type="password" id="password" name="password" required>
        </div>
        <button class="btn"><?= lang('Reset password', 'Passwort zurücksetzen') ?></button>
    </form>
    <?php
    include BASEPATH . "/footer.php";
});

Route::post('/auth/reset-password', function(){
    include_once BASEPATH . "/php/init.php";

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true  && isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        header("Location: " . ROOTPATH . "/profile/$_SESSION[username]");
        die;
    }

    // check if hash and password are set
    if (!isset($_POST['hash']) || !isset($_POST['password'])) {
        $_SESSION['msg'] = lang('The link is not valid. Please request a new password reset.', 'Der Link ist nicht gültig. Bitte fordere einen neuen Passwort zurücksetzen an.');
        header("Location: " . ROOTPATH . "/auth/forgot-password");
        die;
    }

    // check everything again, just to be sure
    $hash = $_POST['hash'];
    $account = $osiris->accounts->findOne(['hash' => $hash]);
    if (empty($account)) {
        $_SESSION['msg'] = lang('The link is not valid. Please request a new password reset.', 'Der Link ist nicht gültig. Bitte fordere einen neuen Passwort zurücksetzen an.');
        header("Location: " . ROOTPATH . "/auth/forgot-password");
        die;
    }

    // check if reset is still valid
    if ($account['reset'] < time() - 24 * 60 * 60) {
        // remove hash
        $osiris->accounts->updateOne(
            ['hash' => $hash],
            ['$unset' => ['hash' => '']]
        );
        $_SESSION['msg'] = lang('The link has expired. Please request a new password reset.', 'Der Link ist abgelaufen. Bitte fordere einen neuen Passwort zurücksetzen an.');
        header("Location: " . ROOTPATH . "/auth/forgot-password");
        die;
    }

    // reset password
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $osiris->accounts->updateOne(
        ['hash' => $hash],
        ['$set' => ['password' => $password], '$unset' => ['hash' => '']]
    );
    $_SESSION['msg'] = lang('Password reset successfully. Please login with your new password.', 'Passwort erfolgreich zurückgesetzt. Bitte logge dich mit deinem neuen Passwort ein.');
    header("Location: " . ROOTPATH . "/user/login");
    die;
});

Route::post('/auth/new-user', function () {
    include_once BASEPATH . "/php/init.php";

    if ($osiris->persons->count(['username' => $_POST['username']]) > 0) {
        $msg = lang("The username is already taken. Please try again.", "Der Nutzername ist bereits vergeben. Versuche es erneut.");
        include BASEPATH . "/header.php";
        printMsg($msg, 'error');
        include BASEPATH . "/addons/auth/add-user.php";
        include BASEPATH . "/footer.php";
        die;
    }

    $person = $_POST['values'];

    $username = $_POST['username'];
    $password = $_POST['password'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    // move to a new collection
    $osiris->accounts->insertOne([
        'username' => $username,
        'password' => $hash
    ]);

    $person['username'] = $username;
    $person['displayname'] = "$person[first] $person[last]";
    $person['formalname'] = "$person[last], $person[first]";
    $person['first_abbr'] = "";
    foreach (explode(" ", $person['first']) as $name) {
        $person['first_abbr'] .= " " . $name[0] . ".";
    }
    $person['created'] = date('d.m.Y');
    $person['roles'] = [];
    if (boolval($person['is_scientist'] ?? false)) $person['roles'][] = 'scientist';

    $person['is_active'] = true;
    $osiris->persons->insertOne($person);

    header("Location: " . ROOTPATH . "/user/login?msg=account-created");
});
