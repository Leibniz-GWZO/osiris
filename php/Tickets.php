<?php

/**
 * Internal tickets: feedback, bug reports and change requests of users
 * to the OSIRIS administrators of their institution.
 *
 * Data: collection `tickets`, attachments in the GridFS bucket `ticket_files`
 * (not below /uploads, which serves files to every logged-in user).
 * Categories: vocabulary `ticket-category`.
 *
 * Managers are all users with the right `tickets.manage`. As long as no role
 * has this right, everyone with `admin.see` manages tickets, so the feature
 * works right after it has been enabled.
 *
 * With the feature `tickets-public`, reporters can make a ticket visible to
 * all users. Others can then join it ("affects me too"), comment on it and
 * are notified about its progress.
 *
 * This file is part of the OSIRIS package.
 *
 * @package     OSIRIS
 * @license     MIT
 */
class Tickets
{
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;
    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'txt', 'csv', 'xlsx', 'docx'];
    public const IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    public const OPEN = ['new', 'in_progress', 'waiting'];

    public $db;
    public $user;
    private Settings $settings;
    private $managers = null;
    private $canManage = null;
    private static $categories = null;

    public function __construct(Settings $settings)
    {
        $DB = new DB;
        $this->db = $DB->db;
        $this->settings = $settings;
        $this->user = $_SESSION['username'] ?? null;
    }

    /** Icons, colors and filling hints of the default categories. */
    private static function categoryDefaults(): array
    {
        return [
            'bug' => [
                'icon' => 'bug',
                'color' => 'danger',
                'hint' => lang(
                    'What did you do, what did you expect, and what happened instead? A screenshot helps a lot.',
                    'Was hast du gemacht, was hast du erwartet und was ist stattdessen passiert? Ein Screenshot hilft sehr.'
                ),
            ],
            'field' => [
                'icon' => 'textbox',
                'color' => 'primary',
                'hint' => lang(
                    'Which form or activity type is it about, which field should be added, removed or changed, and why?',
                    'Um welches Formular bzw. welchen Aktivitätstyp geht es, welches Feld soll hinzukommen, wegfallen oder sich ändern, und warum?'
                ),
            ],
            'data' => [
                'icon' => 'database',
                'color' => 'secondary',
                'hint' => lang(
                    'Which entry is wrong (a link helps) and what should it say instead?',
                    'Welcher Eintrag ist falsch (ein Link hilft) und was soll dort stattdessen stehen?'
                ),
            ],
            'feature' => [
                'icon' => 'lightbulb',
                'color' => 'success',
                'hint' => lang(
                    'What would you like to do in OSIRIS, and what would it help you with?',
                    'Was möchtest du in OSIRIS tun können, und wobei würde es dir helfen?'
                ),
            ],
            'question' => [
                'icon' => 'question',
                'color' => 'signal',
                'hint' => lang('What would you like to know?', 'Was möchtest du wissen?'),
            ],
            'other' => [
                'icon' => 'chat-circle-dots',
                'color' => 'muted',
                'hint' => '',
            ],
        ];
    }

    /**
     * Categories from the vocabulary `ticket-category`.
     * @param bool $all include inactive ones (for existing tickets)
     */
    public static function categories(bool $all = false): array
    {
        if (self::$categories === null) {
            include_once BASEPATH . "/php/Vocabulary.php";
            $Vocabulary = new Vocabulary();
            $defaults = self::categoryDefaults();
            self::$categories = [];
            foreach ($Vocabulary->getValues('ticket-category', true) as $v) {
                $id = $v['id'];
                self::$categories[$id] = array_merge(
                    ['icon' => 'tag', 'color' => 'muted', 'hint' => ''],
                    $defaults[$id] ?? [],
                    ['label' => lang($v['en'], $v['de'] ?? null), 'inactive' => !empty($v['inactive'])]
                );
            }
            if (empty(self::$categories)) {
                self::$categories['other'] = array_merge($defaults['other'], ['label' => lang('Other', 'Sonstiges'), 'inactive' => false]);
            }
        }
        if ($all) return self::$categories;
        return array_filter(self::$categories, fn($c) => !$c['inactive']);
    }

    public static function statuses(): array
    {
        return [
            'new' => ['label' => lang('New', 'Neu'), 'color' => 'signal'],
            'in_progress' => ['label' => lang('In progress', 'In Bearbeitung'), 'color' => 'primary'],
            'waiting' => ['label' => lang('Question to reporter', 'Rückfrage'), 'color' => 'danger'],
            'done' => ['label' => lang('Done', 'Erledigt'), 'color' => 'success'],
            'rejected' => ['label' => lang('Declined', 'Abgelehnt'), 'color' => 'muted'],
        ];
    }

    /** Status label in a fixed language, for notifications (lang() reads $_GET['lang'] first). */
    public static function statusLabel($status, string $language): string
    {
        $had = array_key_exists('lang', $_GET);
        $old = $_GET['lang'] ?? null;
        $_GET['lang'] = $language;
        $label = self::statuses()[$status]['label'] ?? $status;
        if ($had) $_GET['lang'] = $old;
        else unset($_GET['lang']);
        return $label;
    }

    public static function priorities(): array
    {
        return [
            'low' => lang('Low', 'Niedrig'),
            'normal' => lang('Normal', 'Normal'),
            'high' => lang('High', 'Hoch'),
        ];
    }

    public static function categoryBadge($category): string
    {
        $c = self::categories(true)[$category] ?? ['icon' => 'tag', 'color' => 'muted', 'label' => e($category)];
        return '<span class="badge ' . $c['color'] . '"><i class="ph ph-' . $c['icon'] . '"></i> ' . $c['label'] . '</span>';
    }

    public static function statusBadge($status): string
    {
        $s = self::statuses()[$status] ?? ['label' => e($status), 'color' => ''];
        return '<span class="badge ' . $s['color'] . '">' . $s['label'] . '</span>';
    }

    /* ------------------------------------
       ACCESS
    ------------------------------------ */

    private function managerRight(): string
    {
        $configured = $this->db->adminRights->countDocuments(['right' => 'tickets.manage', 'value' => true]);
        return $configured > 0 ? 'tickets.manage' : 'admin.see';
    }

    public function canManage(): bool
    {
        if ($this->canManage === null) {
            $this->canManage = $this->settings->hasPermission($this->managerRight())
                || (defined('ADMIN') && ADMIN == $this->user);
        }
        return $this->canManage;
    }

    /** Usernames of everyone who manages tickets. */
    public function managers(): array
    {
        if ($this->managers !== null) return $this->managers;
        $DB = new DB;
        $users = $DB->getMessageGroup('right:' . $this->managerRight());
        if (defined('ADMIN') && !empty(ADMIN)) $users[] = ADMIN;
        $this->managers = array_values(array_unique(array_filter($users)));
        return $this->managers;
    }

    public function publicEnabled(): bool
    {
        return $this->settings->featureEnabled('tickets-public');
    }

    /** Filter for tickets every user may see. */
    public function publicFilter(): array
    {
        return $this->publicEnabled() ? ['public' => true] : ['_id' => null];
    }

    public function isPublic($ticket): bool
    {
        return $this->publicEnabled() && !empty($ticket['public']);
    }

    public function canView($ticket): bool
    {
        return $this->canManage() || $this->isReporter($ticket) || $this->isPublic($ticket);
    }

    public function isReporter($ticket): bool
    {
        return ($ticket['created_by'] ?? null) == $this->user;
    }

    public function isFollower($ticket): bool
    {
        return in_array($this->user, DB::doc2Arr($ticket['followers'] ?? []));
    }

    public function get($id)
    {
        if (!DB::is_ObjectID($id)) return null;
        return $this->db->tickets->findOne(['_id' => DB::to_ObjectID($id)]);
    }

    public function newCount(): int
    {
        return $this->db->tickets->countDocuments(['status' => 'new']);
    }

    /** Own tickets with a question from the managers. */
    public function waitingCount(): int
    {
        if (empty($this->user)) return 0;
        return $this->db->tickets->countDocuments(['created_by' => $this->user, 'status' => 'waiting']);
    }

    /**
     * Open tickets the user may see whose title or description contain words
     * of $query, best matches first. Used to avoid duplicates.
     */
    public function similar(string $query, int $limit = 5): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_unique(array_filter($words, fn($w) => mb_strlen($w) >= 3)));
        if (empty($words)) return [];
        $words = array_slice($words, 0, 8);

        $match = [];
        foreach ($words as $w) {
            $regex = new MongoDB\BSON\Regex(preg_quote($w), 'i');
            $match[] = ['title' => $regex];
            $match[] = ['description' => $regex];
        }
        $access = [['created_by' => $this->user]];
        if ($this->canManage()) $access = [[]];
        else if ($this->publicEnabled()) $access[] = ['public' => true];

        $found = $this->db->tickets->find([
            'status' => ['$in' => self::OPEN],
            '$and' => [['$or' => $match], ['$or' => $access]],
        ], ['projection' => ['number' => 1, 'title' => 1, 'description' => 1, 'status' => 1, 'followers' => 1], 'limit' => 50])->toArray();

        $result = [];
        foreach ($found as $t) {
            $title = mb_strtolower($t['title']);
            $description = mb_strtolower($t['description']);
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($title, $w)) $score += 3;
                else if (str_contains($description, $w)) $score += 1;
            }
            $result[] = [
                'id' => strval($t['_id']),
                'number' => intval($t['number']),
                'title' => $t['title'],
                'status' => self::statuses()[$t['status']]['label'] ?? $t['status'],
                'affected' => 1 + count(DB::doc2Arr($t['followers'] ?? [])),
                'score' => $score,
            ];
        }
        usort($result, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($result, 0, $limit);
    }

    /* ------------------------------------
       CHANGES
    ------------------------------------ */

    private function nextNumber(): int
    {
        $last = $this->db->tickets->findOne([], ['sort' => ['number' => -1], 'projection' => ['number' => 1]]);
        return intval($last['number'] ?? 0) + 1;
    }

    public function create(array $values): array
    {
        $now = date('Y-m-d H:i:s');
        $categories = self::categories();
        $fallback = array_key_exists('other', $categories) ? 'other' : array_key_last($categories);
        $category = array_key_exists($values['category'] ?? '', $categories) ? $values['category'] : $fallback;
        $ticket = [
            'number' => $this->nextNumber(),
            'category' => $category,
            'title' => trim(mb_substr((string) ($values['title'] ?? ''), 0, 200)),
            'description' => trim((string) ($values['description'] ?? '')),
            'page' => self::cleanPage($values['page'] ?? ''),
            'public' => $this->publicEnabled() && !empty($values['public']),
            'context' => [
                'version' => defined('OSIRIS_VERSION') ? OSIRIS_VERSION : null,
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
            ],
            'status' => 'new',
            'priority' => 'normal',
            'assigned_to' => null,
            'created_by' => $this->user,
            'created_at' => $now,
            'updated_at' => $now,
            'followers' => [],
            'comments' => [],
            'files' => [],
            'history' => [['at' => $now, 'by' => $this->user, 'action' => 'created']],
        ];
        $result = $this->db->tickets->insertOne($ticket);
        $ticket['_id'] = $result->getInsertedId();
        return $ticket;
    }

    /** Only keep a path below ROOTPATH, never a foreign URL. */
    public static function cleanPage($page): ?string
    {
        $page = trim((string) $page);
        if ($page === '' || !str_starts_with($page, '/') || str_starts_with($page, '//')) return null;
        return mb_substr($page, 0, 500);
    }

    public function comment($ticket, string $text, bool $internal)
    {
        $now = date('Y-m-d H:i:s');
        $comment = [
            'id' => uniqid(),
            'by' => $this->user,
            'at' => $now,
            'text' => $text,
            'internal' => $internal,
        ];
        $set = ['updated_at' => $now];
        $push = ['comments' => $comment];
        // the reporter answers a question: back to the managers
        if (!$internal && $ticket['status'] == 'waiting' && $this->isReporter($ticket)) {
            $set['status'] = 'in_progress';
            $push['history'] = ['at' => $now, 'by' => $this->user, 'action' => 'status', 'from' => 'waiting', 'to' => 'in_progress'];
        }
        $this->db->tickets->updateOne(['_id' => $ticket['_id']], ['$set' => $set, '$push' => $push]);
        return $comment;
    }

    /** "Affects me too": join or leave a ticket. */
    public function follow($ticket, bool $follow)
    {
        if ($this->isReporter($ticket)) return;
        $op = $follow ? '$addToSet' : '$pull';
        $this->db->tickets->updateOne(['_id' => $ticket['_id']], [$op => ['followers' => $this->user]]);
    }

    /**
     * Managers change status, priority, category, assignee and visibility.
     * @return array the changed fields as [field => [from, to]]
     */
    public function update($ticket, array $values): array
    {
        $now = date('Y-m-d H:i:s');
        $allowed = [
            'status' => array_keys(self::statuses()),
            'priority' => array_keys(self::priorities()),
            'category' => array_keys(self::categories(true)),
            'assigned_to' => array_merge([''], $this->managers()),
        ];
        if ($this->publicEnabled()) $allowed['public'] = ['0', '1'];
        $set = [];
        $history = [];
        foreach ($allowed as $field => $options) {
            if (!array_key_exists($field, $values)) continue;
            $value = (string) $values[$field];
            if (!in_array($value, $options, true)) continue;
            $old = $ticket[$field] ?? null;
            $new = $value === '' ? null : $value;
            if ($field == 'public') {
                $old = !empty($old);
                $new = $value === '1';
            }
            if ($old === $new) continue;
            $set[$field] = $new;
            $history[] = ['at' => $now, 'by' => $this->user, 'action' => $field, 'from' => $old, 'to' => $new];
        }
        if (empty($set)) return [];
        $set['updated_at'] = $now;
        $this->db->tickets->updateOne(['_id' => $ticket['_id']], [
            '$set' => $set,
            '$push' => ['history' => ['$each' => $history]],
        ]);
        $changes = [];
        foreach ($history as $h) $changes[$h['action']] = [$h['from'], $h['to']];
        return $changes;
    }

    public function delete($ticket)
    {
        foreach (DB::doc2Arr($ticket['files'] ?? []) as $f) {
            try {
                $this->bucket()->delete(DB::to_ObjectID($f['id']));
            } catch (Exception $e) {
                // already gone
            }
        }
        $this->db->tickets->deleteOne(['_id' => $ticket['_id']]);
    }

    /* ------------------------------------
       FILES
    ------------------------------------ */

    private function bucket()
    {
        return $this->db->selectGridFSBucket(['bucketName' => 'ticket_files']);
    }

    /**
     * Normalize $_FILES['files'] (multiple) into a list of single files.
     */
    public static function uploadedFiles($files): array
    {
        if (empty($files) || !is_array($files['name'] ?? null)) return [];
        $list = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $list[] = [
                'name' => $name,
                'tmp_name' => $files['tmp_name'][$i],
                'size' => $files['size'][$i],
                'error' => $files['error'][$i],
            ];
        }
        return $list;
    }

    /** Returns an error message or null. */
    public function addFile($ticket, array $file)
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return lang('The file could not be uploaded.', 'Die Datei konnte nicht hochgeladen werden.');
        }
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return lang('The file is larger than 10 MB: ', 'Die Datei ist größer als 10 MB: ') . e($file['name']);
        }
        $name = basename(str_replace('\\', '/', (string) $file['name']));
        $name = preg_replace('/[\x00-\x1F\x7F"]/u', '', $name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS)) {
            return lang('This file type is not allowed: ', 'Dieser Dateityp ist nicht erlaubt: ') . e($ext);
        }
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        // an image must really be one, it is shown inline
        if (in_array($ext, self::IMAGES) && !str_starts_with($mime, 'image/')) {
            return lang('The file is not a valid image: ', 'Die Datei ist kein gültiges Bild: ') . e($name);
        }
        $stream = fopen($file['tmp_name'], 'rb');
        $fileId = $this->bucket()->uploadFromStream($name, $stream, [
            'metadata' => ['ticket_id' => strval($ticket['_id']), 'mime' => $mime, 'by' => $this->user],
        ]);
        fclose($stream);
        $now = date('Y-m-d H:i:s');
        $this->db->tickets->updateOne(['_id' => $ticket['_id']], [
            '$push' => ['files' => ['id' => strval($fileId), 'name' => $name, 'size' => intval($file['size']), 'mime' => $mime, 'by' => $this->user, 'at' => $now]],
            '$set' => ['updated_at' => $now],
        ]);
        return null;
    }

    public static function isImage($file): bool
    {
        return in_array(strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)), self::IMAGES)
            && str_starts_with($file['mime'] ?? '', 'image/');
    }

    public function sendFile($ticket, $fileId)
    {
        $file = null;
        foreach (DB::doc2Arr($ticket['files'] ?? []) as $f) {
            if (($f['id'] ?? null) === $fileId) $file = DB::doc2Arr($f);
        }
        if (empty($file) || !DB::is_ObjectID($fileId)) abortwith(404, lang('File', 'Datei'));
        $name = str_replace(['"', "\r", "\n"], '', $file['name']);
        $disposition = self::isImage($file) && isset($_GET['inline']) ? 'inline' : 'attachment';
        header('Content-Type: ' . ($file['mime'] ?? 'application/octet-stream'));
        header('Content-Length: ' . intval($file['size']));
        header('Content-Disposition: ' . $disposition . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'wb');
        $this->bucket()->downloadToStream(DB::to_ObjectID($fileId), $out);
        fclose($out);
        die;
    }

    /* ------------------------------------
       NOTIFICATIONS
    ------------------------------------ */

    /** Reporter and everyone who joined the ticket. */
    public function audience($ticket): array
    {
        $users = array_merge([$ticket['created_by']], DB::doc2Arr($ticket['followers'] ?? []));
        return array_values(array_unique(array_filter($users)));
    }

    /**
     * OSIRIS inbox message and, if enabled, an e-mail.
     * Never notifies the current user.
     */
    public function notify(array $users, $ticket, string $en, string $de, ?string $text = null)
    {
        $users = array_values(array_diff(array_unique(array_filter($users)), [$this->user]));
        if (empty($users)) return;
        $link = '/tickets/view/' . strval($ticket['_id']);
        $label = '#' . intval($ticket['number']) . ' ' . e($ticket['title']);
        $en = str_replace('%t', "<b>$label</b>", $en);
        $de = str_replace('%t', "<b>$label</b>", $de);

        $DB = new DB;
        foreach ($users as $u) {
            $DB->addMessage($u, $en, $de, 'ticket', $link);
        }

        if (!$this->settings->featureEnabled('tickets-mail', true)) return;
        include_once BASEPATH . '/php/MailSender.php';
        $html = '<p>' . $de . '</p><p style="color:#777">' . $en . '</p>';
        if (!empty($text)) {
            $html .= '<blockquote style="border-left:3px solid #ddd;margin:0;padding-left:10px">' . nl2br(e(mb_substr($text, 0, 2000))) . '</blockquote>';
        }
        $html = buildNotificationMail('OSIRIS Ticket #' . intval($ticket['number']), $html, 'Ticket ansehen / View ticket', $link);
        $subject = '[OSIRIS] #' . intval($ticket['number']) . ' ' . $ticket['title'];
        $recipients = $this->db->persons->find(
            ['username' => ['$in' => $users], 'is_active' => ['$ne' => false], 'mail' => ['$exists' => true, '$ne' => '']],
            ['projection' => ['mail' => 1]]
        );
        foreach ($recipients as $r) {
            sendMail($r['mail'], $subject, $html);
        }
    }
}
