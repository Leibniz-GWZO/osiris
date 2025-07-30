<?php
include_once BASEPATH . '/php/LDAPInterface.php';

$fields = $_POST['field'] ?? [];

// Speichern der aktualisierten Daten
$osiris->adminGeneral->updateOne(
    ['key' => 'ldap_mappings'],
    ['$set' => ['value' => $fields]],
    ['upsert' => true]
);

// look in LDAP for those fields
$ldap_fields = array_filter($fields);

$filter = "(|";
foreach ($ldap_fields as $field) {
    $filter .= "($field=*)";
}
$filter .= ")";

$LDAP = new LDAPInterface();
$result = $LDAP->fetchUsers('(cn=*)', array_values($ldap_fields));
if (is_string($result)) {
    echo $result;
    exit;
}
?>

<div class="alert success">
    <?= lang('The attributes have been saved.', 'Die Attribute wurden gespeichert.') ?>
</div>

<h1>
    <?= lang('Synchronized attributes from LDAP', 'Synchronisierte Attribute aus LDAP') ?>
</h1>

<p>
    <?= lang('The following attributes are synchronized from LDAP to OSIRIS every time a user synchronization is performed.', 'Die folgenden Attribute werden von LDAP nach OSIRIS synchronisiert, jedes Mal wenn eine Nutzer-Synchronisation durchgeführt wird.') ?>
</p>

<style>
    th code {
        font-size: smaller;
        color: var(--muted-color);
        text-transform: none; font-weight: normal
    }
</style>

<table class="table">
    <thead>
        <tr>
            <th>
                User
                <br>
                <code>samaccountname</code>
            </th>
            <?php foreach ($ldap_fields as $osiris_field => $ldap_key) {
            ?>
                <th>
                    <?= htmlspecialchars($osiris_field) ?>
                    <br>
                    <code><?= htmlspecialchars($ldap_key) ?></code>
                </th>
            <?php } ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($result as $entry) {
            $user = '';
            if (isset($entry['samaccountname'])) {
                $user = $entry['samaccountname'][0];
            } else if (isset($entry['uid'])) {
                $user = $entry['uid'][0];
            }
            if (empty($user)) continue;
        ?>
            <tr>
                <td><?= $user ?></td>
                <?php foreach ($ldap_fields as $osiris_field => $lf) { ?>
                    <td>
                        <?php if (isset($entry[$lf])) { ?>
                            <?= $entry[$lf][0] ?>
                        <?php } else { ?>
                            <span class="text-danger">not found</span>
                        <?php } ?>
                    </td>
                <?php } ?>
            </tr>
        <?php } ?>
    </tbody>
</table>

<p>
    <?=lang('You are now ready to synchronize attributes from LDAP to OSIRIS by <a href="https://wiki.osiris-app.de/technical/user-management/ldap/#synchronisation-der-nutzerattribute" target="_blank">setting up a CRON-Job</a> or you can do it manually:', 
    'Du bist nun bereit, die Attribute von LDAP nach OSIRIS zu synchronisieren, indem du einen <a href="https://wiki.osiris-app.de/technical/user-management/ldap/#synchronisation-der-nutzerattribute" target="_blank">CRON-Job</a> einrichtest oder es manuell tust:')?>
</p>

<form action="<?= ROOTPATH ?>/synchronize-attributes-now" method="post">
    <button class="btn primary">
        <i class="ph ph-check"></i>
        <?= lang('Synchronize now', 'Jetzt synchronisieren') ?>
    </button>
</form>