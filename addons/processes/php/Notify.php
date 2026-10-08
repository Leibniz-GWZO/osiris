<?php

/**
 * Notifications of the process add-on: OSIRIS inbox message and, if the
 * type has `email: true`, an e-mail via the OSIRIS mail settings.
 */
class ProcessNotify
{
    /** Run $fn with lang() fixed to one language (lang() reads $_GET['lang'] first). */
    private static function inLang($lang, callable $fn)
    {
        $had = array_key_exists('lang', $_GET);
        $old = $_GET['lang'] ?? null;
        $_GET['lang'] = $lang;
        try {
            return $fn();
        } finally {
            if ($had) $_GET['lang'] = $old;
            else unset($_GET['lang']);
        }
    }

    public static function afterAction(Processes $P, $case, $entry)
    {
        $type = $P->type($case['type']);
        // case label and step name in both languages
        $label = $step = [];
        $current = $P->currentStep($case);
        foreach (['de', 'en'] as $l) {
            $label[$l] = self::inLang($l, function () use ($P, $case, $type) {
                return e(trim(($case['number'] ?? '') . ' ' . $P->title($case, $type)));
            });
            $step[$l] = $current ? self::inLang($l, function () use ($current) {
                return e(Processes::t($current));
            }) : '';
        }
        $by = e($P->personName($P->user));
        $link = '/processes/view/' . strval($case['_id']);
        $creator = $case['created_by'];
        $owners = array_values(array_unique(array_merge([$creator], DB::doc2Arr($case['participants'] ?? []))));

        $messages = [];
        $status = $case['status'];

        // the next step has to decide
        if ($status == 'submitted' && $current) {
            $messages[] = [
                $P->approvers($case, $current) ?? [],
                "Please decide: <b>{$label['en']}</b> ({$step['en']})",
                "Bitte entscheiden: <b>{$label['de']}</b> ({$step['de']})",
            ];
        }
        switch ($entry['action']) {
            case 'approve':
                if ($status == 'approved') {
                    $messages[] = [$owners, "<b>{$label['en']}</b> has been approved.", "<b>{$label['de']}</b> wurde genehmigt."];
                }
                break;
            case 'return':
                $messages[] = [$owners, "<b>{$label['en']}</b> has been returned by $by for revision.", "<b>{$label['de']}</b> wurde von $by zur Überarbeitung zurückgegeben."];
                break;
            case 'reject':
                $messages[] = [$owners, "<b>{$label['en']}</b> has been rejected by $by.", "<b>{$label['de']}</b> wurde von $by abgelehnt."];
                break;
            case 'withdraw':
                // everyone who has already decided, and the current step
                $deciders = [];
                foreach (DB::doc2Arr($case['steps'] ?? []) as $s) {
                    if (!empty($s['by'])) $deciders[] = $s['by'];
                }
                $i = $P->stepIndex($type, $case['step'] ?? null);
                $open = $i === null ? null : $type['steps'][$i];
                if ($open) $deciders = array_merge($deciders, $P->approvers($case, $open) ?? []);
                $messages[] = [$deciders, "<b>{$label['en']}</b> has been withdrawn by $by.", "<b>{$label['de']}</b> wurde von $by zurückgezogen."];
                break;
        }

        $comment = $entry['comment'] ?? null;
        $done = [$P->user];
        foreach ($messages as [$users, $en, $de]) {
            $users = array_values(array_diff(array_unique(array_filter($users)), $done));
            if (empty($users)) continue;
            $done = array_merge($done, $users);
            self::send($P, $type, $users, $en, $de, $link, $comment);
        }
    }

    /**
     * Phase flow: new tasks after a phase change go to their assignees,
     * finished tasks to the people who manage the type.
     * @param array $before ids of the tasks that were open before the action
     */
    public static function afterPhaseAction(Processes $P, $case, $entry, array $before)
    {
        $type = $P->type($case['type']);
        $label = [];
        foreach (['de', 'en'] as $l) {
            $label[$l] = self::inLang($l, function () use ($P, $case, $type) {
                return e(trim(($case['number'] ?? '') . ' ' . $P->title($case, $type)));
            });
        }
        $by = e($P->personName($P->user));
        $link = '/processes/view/' . strval($case['_id']);
        $done = [$P->user];

        // tasks that became due (phase change, or a field such as "open access" changed)
        if (in_array($entry['action'], ['phase', 'edited']) && in_array($case['status'], ProcessPhases::OPEN)) {
            foreach (ProcessPhases::tasks($P, $case, $type) as $t) {
                if ($t['done'] || in_array($t['task']['id'], $before)) continue;
                $users = array_diff($P->audienceUsers($t['task']['assignees'] ?? []), $done);
                if (empty($users)) continue;
                $task = [];
                foreach (['de', 'en'] as $l) {
                    $task[$l] = self::inLang($l, function () use ($t) {
                        return e(Processes::t($t['task']));
                    });
                }
                self::send($P, $type, $users, "New task: {$task['en']} – <b>{$label['en']}</b>", "Neue Aufgabe: {$task['de']} – <b>{$label['de']}</b>", $link, null);
            }
        }
        if ($entry['action'] == 'task_done') {
            $task = null;
            foreach ($type['phases'] as $phase) {
                foreach ($phase['tasks'] ?? [] as $t) {
                    if ($t['id'] == $entry['task']) $task = $t;
                }
            }
            $users = array_diff($P->audienceUsers($type['manage'] ?? []), $done);
            if ($task && !empty($users)) {
                $tde = self::inLang('de', function () use ($task) {
                    return e(Processes::t($task));
                });
                $ten = self::inLang('en', function () use ($task) {
                    return e(Processes::t($task));
                });
                self::send($P, $type, $users, "$by has completed: $ten – <b>{$label['en']}</b>", "$by hat erledigt: $tde – <b>{$label['de']}</b>", $link, $entry['comment'] ?? null);
            }
        }
    }

    private static function send(Processes $P, $type, $users, $en, $de, $link, $comment)
    {
        $DB = new DB;
        foreach ($users as $u) {
            $DB->addMessage($u, $en, $de, 'process', $link);
        }
        if (empty($type['email'])) return;

        include_once BASEPATH . '/php/MailSender.php';
        $html = '<p>' . $de . '</p><p style="color:#777">' . $en . '</p>';
        if (!empty($comment)) {
            $html .= '<p><b>Kommentar / Comment:</b><br>' . nl2br(e($comment)) . '</p>';
        }
        $url = (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . ROOTPATH . $link;
        $html .= '<p style="margin-top:20px"><a href="' . e($url) . '" style="background-color:#f78104;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px">Vorgang ansehen / View case</a></p>';
        $html .= '<p style="font-size:12px;color:#777;margin-top:40px">Automatische Nachricht aus OSIRIS. / Automated message from OSIRIS.</p>';
        $subject = '[OSIRIS] ' . html_entity_decode(strip_tags($de));

        $recipients = $P->db->persons->find(
            ['username' => ['$in' => array_values($users)], 'is_active' => ['$ne' => false], 'mail' => ['$exists' => true, '$ne' => '']],
            ['projection' => ['mail' => 1]]
        );
        foreach ($recipients as $r) {
            sendMail($r['mail'], $subject, '<div style="font-family:Arial,sans-serif;color:#333">' . $html . '</div>');
        }
    }
}
