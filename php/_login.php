
<?php
// apt-get install php-ldap

if (!isset($Settings)) {
    require_once BASEPATH . '/php/Settings.php';
    global $Settings;
    $Settings = new Settings();

}

function login($username, $password)
{
    global $Settings;
    $return = array("msg" => '', "success" => false);

    if (!isset($Settings->settings['ldap'])){
        die ("LDAP Settings are missing in settings.json");
    }
    $set = $Settings->settings['ldap'];

    $ip = $set['ip'];
    $ldap_address = "ldap://".$ip;
    $ldap_port = $set['port'];

    $dn = $username.$set['domain'];
    $base_dn = $set['basedn']; // ldap rdn oder dn

    if ($connect = ldap_connect($ldap_address . ":" . $ldap_port)) {
        // Verbindung erfolgreich
        ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
        // define(LDAP_OPT_DIAGNOSTIC_MESSAGE, 0x0032);

        // Authentifizierung des Benutzers
        $bind = ldap_bind($connect, $dn, $password);

        if ($bind) {
            $person = "$username";
            $fields = "(|(samaccountname=$person))";

            $search = ldap_search($connect, $base_dn, $fields);
            $result = ldap_get_entries($connect, $search);

            $ldap_username = $result[0]['samaccountname'][0];
            $ldap_first_name = $result[0]['givenname'][0];
            $ldap_last_name = $result[0]['sn'][0];
            
            $_SESSION['username'] = $ldap_username;
            $_SESSION['name'] = $ldap_first_name . " " . $ldap_last_name;
            $_SESSION['loggedin'] = true;

            $return["status"] = true;

            ldap_close($connect);
        } else {
            // Login fehlgeschlagen / Benutzer nicht vorhanden
            $return["msg"] = "Login failed or user not found.";
        }
    } else {
        $return["msg"] = "Connection to LDAP server failed.";
    }

    return $return;
}

function getUser($name)
{
    global $Settings;

    if (!isset($Settings->settings['ldap'])){
        die ("LDAP Settings are missing in settings.json");
    }
    $set = $Settings->settings['ldap'];

    $ip = $set['ip'];
    $ldap_address = "ldap://".$ip;
    $ldap_port = $set['port'];

    $password = $set['password']; 
    $dn = $set['user'].$set['domain'];
    $base_dn = $set['basedn']; 

    if ($connect = ldap_connect($ldap_address . ":" . $ldap_port)) {
        // Verbindung erfolgreich
        ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
        // define(LDAP_OPT_DIAGNOSTIC_MESSAGE, 0x0032);

        // Authentifizierung des Benutzers
        // if ($bind = ldap_bind( $connect, $ldaprdn, $ldappass)) { // service account
        $bind = ldap_bind($connect, $dn, $password);

        if ($bind) {
            $res = array();

            $fields = "(|(samaccountname=*$name*))";

            $search = ldap_search($connect, $base_dn, $fields);
            $result = ldap_get_entries($connect, $search);
            return $result;
            $ldap_username = $result[0]['samaccountname'][0];
            $ldap_last_name = $result[0]['cn'][0];


            foreach ($result as $entry) {
                $res[$entry['samaccountname'][0]] = $entry['cn'][0];
            }
            ldap_close($connect);
            return $res;
        } else {
            // Login fehlgeschlagen / Benutzer nicht vorhanden
            return "Login fehlgeschlagen / Benutzer nicht vorhanden";
        }
    } else {
        return "Verbindung zum Server fehlgeschlagen.";
    }
}


function getUsers()
{
    global $Settings;

    if (!isset($Settings->settings['ldap'])){
        die ("LDAP Settings are missing in settings.json");
    }
    $set = $Settings->settings['ldap'];

    $ip = $set['ip'];
    $ldap_address = "ldap://".$ip;
    $ldap_port = $set['port'];

    $password = $set['password']; 
    $dn = $set['user'].$set['domain'];
    $base_dn = $set['basedn']; 

    if ($connect = ldap_connect($ldap_address . ":" . $ldap_port)) {
        // Verbindung erfolgreich
        ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
        // define(LDAP_OPT_DIAGNOSTIC_MESSAGE, 0x0032);

        // Authentifizierung des Benutzers
        // if ($bind = ldap_bind( $connect, $ldaprdn, $ldappass)) { // service account
        $bind = ldap_bind($connect, $dn, $password);

        if ($bind) {
            $res = array();

            $fields = "(|(samaccountname=*))";

            $search = ldap_search($connect, $base_dn, $fields);
            $result = ldap_get_entries($connect, $search);
            return $result;
            $ldap_username = $result[0]['samaccountname'][0];
            $ldap_last_name = $result[0]['cn'][0];


            foreach ($result as $entry) {
                $res[$entry['samaccountname'][0]] = $entry['cn'][0];
            }
            ldap_close($connect);
            return $res;
        } else {
            // Login fehlgeschlagen / Benutzer nicht vorhanden
            return "Login fehlgeschlagen / Benutzer nicht vorhanden";
        }
    } else {
        return "Verbindung zum Server fehlgeschlagen.";
    }
}

function getGroups($v)
{
    $m = array();
    if (preg_match ('/CN=([^,]+)[,$]/', $v, $matches))
      return $matches[1];
    return false;
}



function updateUser($username){
    $keys = [
        "_id" => "samaccountname",
        "username" => "samaccountname",
        "first" => "givenname",
        "last" => "sn",
        "displayname" => "displayname",
        "formalname" => "cn",
        "department" => "department",
        "unit" => "description",
        "telephone" => "telephonenumber",
        "mail" => "mail"
    ];
    $user = getUser($username);
    // dump($user);
    if (empty($user) || $user['count'] == 0) return false;
    $value = $user[0];
    // dump($value);
    $user = array();
    foreach ($keys as $key => $name) {
        // dump($value, true);
        $user[$key] = $value[$name][0] ?? null;
    }
    // if (empty($user['last']) || str_contains($user['unit'], "Allgemeiner Account")) return array();
    $user['_id'] = strtolower(trim($user['_id']));
    $user['is_admin'] = $user['username'] == 'juk20';
    $user['dept'] = get_dept($user['unit']);
    $user['academic_title'] = null;
    $user['orcid'] = null;
    $user['is_controlling'] = $user['department'] == "Controller";
    $user['is_scientist'] = in_array($user['dept'], [
        "BI & DB", "Services", "MIG","MIOS", "BUG", "MuTZ", "Patente", "PFVI", "MÖD"
    ]);
    $user['is_leader'] = str_contains($user['unit'], "leitung");
    $user['is_active'] = !str_contains($user['unit'], "verlassen");
    return $user;
}