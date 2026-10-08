<?php

/**
 * Add-on "Processes" (Vorgänge): registry of process types, access rules,
 * approval chain and storage of cases.
 *
 * The add-on is independent of the OSIRIS core data: process types are
 * defined in JSON files (addons/processes/definitions), cases are stored in
 * the collections proc_cases and proc_counters, attachments in the GridFS
 * bucket proc_files. From the core it only reads persons, groups, roles and
 * uses the message inbox and the mail settings.
 */

require_once __DIR__ . '/Conditions.php';
require_once __DIR__ . '/Phases.php';

class Processes
{
    public const FEATURE = 'processes';

    public const STATUS = [
        'draft'     => ['en' => 'Draft', 'de' => 'Entwurf', 'color' => 'muted', 'icon' => 'pencil-simple'],
        'submitted' => ['en' => 'In approval', 'de' => 'In Freigabe', 'color' => 'signal', 'icon' => 'hourglass-medium'],
        'returned'  => ['en' => 'Returned', 'de' => 'Zurückgegeben', 'color' => 'secondary', 'icon' => 'arrow-u-up-left'],
        'approved'  => ['en' => 'Approved', 'de' => 'Genehmigt', 'color' => 'success', 'icon' => 'check-circle'],
        'rejected'  => ['en' => 'Rejected', 'de' => 'Abgelehnt', 'color' => 'danger', 'icon' => 'x-circle'],
        'withdrawn' => ['en' => 'Withdrawn', 'de' => 'Zurückgezogen', 'color' => 'muted', 'icon' => 'arrow-counter-clockwise'],
        // phase flow
        'active'    => ['en' => 'In progress', 'de' => 'Läuft', 'color' => 'signal', 'icon' => 'play-circle'],
        'on_hold'   => ['en' => 'On hold', 'de' => 'Ruht', 'color' => 'muted', 'icon' => 'pause-circle'],
        'published' => ['en' => 'Published', 'de' => 'Erschienen', 'color' => 'success', 'icon' => 'book-open'],
    ];

    // statuses in which a case of the approval flow is still open
    public const OPEN = ['draft', 'submitted', 'returned'];

    private static $instance = null;

    /** @var MongoDB\Database */
    public $db;
    public $Settings;
    public $user;

    private $areas = [];
    private $types = [];
    private $errors = [];
    private $persons = [];
    private $groups = null;

    public static function get(): Processes
    {
        if (self::$instance === null) {
            global $osiris, $Settings;
            self::$instance = new Processes($osiris, $Settings);
        }
        return self::$instance;
    }

    public function __construct($db, $Settings)
    {
        $this->db = $db;
        $this->Settings = $Settings;
        $this->user = $_SESSION['username'] ?? null;
        $this->loadDefinitions();
    }

    public function enabled(): bool
    {
        return !empty($this->user) && $this->Settings->featureEnabled(self::FEATURE, false);
    }

    /* ------------------------------------
       DEFINITIONS
    ------------------------------------ */

    private function loadDefinitions()
    {
        $dir = dirname(__DIR__) . '/definitions';
        $areas = json_decode((string) @file_get_contents($dir . '/areas.json'), true);
        if (!is_array($areas)) {
            $this->errors[] = 'definitions/areas.json is missing or not valid JSON';
            return;
        }
        foreach ($areas['areas'] ?? [] as $area) {
            if (empty($area['id'])) continue;
            $this->areas[$area['id']] = $area;
        }
        foreach (glob($dir . '/types/*.json') as $file) {
            $type = json_decode((string) file_get_contents($file), true);
            if (is_array($type) && ProcessPhases::isPhases($type)) {
                $error = ProcessPhases::validate($type, basename($file));
                if ($error) {
                    $this->errors[] = $error;
                    continue;
                }
                $type['fields'] = ProcessPhases::expandFields($type);
            }
            $error = $this->validateType($type, basename($file));
            if ($error) {
                $this->errors[] = $error;
                continue;
            }
            $this->types[$type['id']] = $type;
        }
        uasort($this->types, function ($a, $b) {
            return ($a['order'] ?? 100) <=> ($b['order'] ?? 100);
        });
    }

    private function validateType($type, $file)
    {
        if (!is_array($type)) return "$file: not valid JSON";
        foreach (['id', 'area', 'name', 'fields', 'steps'] as $key) {
            if (empty($type[$key]) && $key != 'steps') return "$file: '$key' is missing";
        }
        if (!preg_match('/^[a-z0-9-]+$/', $type['id'])) return "$file: id must only contain a-z, 0-9 and -";
        if (!isset($this->areas[$type['area']])) return "$file: unknown area '{$type['area']}'";
        if (isset($this->types[$type['id']])) return "$file: id '{$type['id']}' is used twice";
        $ids = [];
        foreach ($type['fields'] as $field) {
            if (empty($field['id']) || !preg_match('/^[a-z0-9_]+$/', $field['id'])) return "$file: field without valid id";
            if (in_array($field['id'], $ids)) return "$file: field '{$field['id']}' is used twice";
            if (!isset(ProcessForm::TYPES[$field['type'] ?? ''])) return "$file: field '{$field['id']}' has unknown type '" . ($field['type'] ?? '') . "'";
            $ids[] = $field['id'];
        }
        $steps = [];
        foreach ($type['steps'] ?? [] as $step) {
            if (empty($step['id']) || in_array($step['id'], $steps)) return "$file: step without id or id used twice";
            $steps[] = $step['id'];
        }
        return null;
    }

    /** Is the case still open (not finished)? */
    public function isOpen($case): bool
    {
        $type = $this->type($case['type'] ?? '');
        $open = ProcessPhases::isPhases($type) ? ProcessPhases::OPEN : self::OPEN;
        return in_array($case['status'] ?? '', $open);
    }

    /** Label of where the case stands: current step or phase. */
    public function currentLabel($case): string
    {
        $type = $this->type($case['type'] ?? '');
        if (ProcessPhases::isPhases($type)) {
            $phase = ProcessPhases::phase($type, $case['phase'] ?? null);
            return $phase ? self::t($phase) : '';
        }
        $step = $this->currentStep($case);
        return $step ? self::t($step) : '';
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function areas(): array
    {
        return $this->areas;
    }

    public function area($id)
    {
        return $this->areas[$id] ?? null;
    }

    public function types($area = null): array
    {
        if ($area === null) return $this->types;
        return array_filter($this->types, function ($t) use ($area) {
            return $t['area'] == $area;
        });
    }

    public function type($id)
    {
        return $this->types[$id] ?? null;
    }

    /** Translated value of a definition: German in `key`, English in `key_en`. */
    public static function t($def, $key = 'label', $default = '')
    {
        if (!is_array($def)) return $default;
        $de = $def[$key] ?? $default;
        return lang($def[$key . '_en'] ?? $de, $de);
    }

    public static function status($status)
    {
        return self::STATUS[$status] ?? ['en' => $status, 'de' => $status, 'color' => 'muted', 'icon' => 'circle'];
    }

    public static function statusBadge($status)
    {
        $s = self::status($status);
        return '<span class="badge ' . $s['color'] . '"><i class="ph ph-' . $s['icon'] . '"></i> ' . lang($s['en'], $s['de']) . '</span>';
    }

    /* ------------------------------------
       PERSONS, ROLES AND UNITS
    ------------------------------------ */

    public function person($username)
    {
        if (empty($username)) return [];
        if (!array_key_exists($username, $this->persons)) {
            $p = $this->db->persons->findOne(
                ['username' => $username],
                ['projection' => ['username' => 1, 'first' => 1, 'last' => 1, 'mail' => 1, 'roles' => 1, 'units' => 1, 'is_active' => 1]]
            );
            $this->persons[$username] = $p ? DB::doc2Arr($p) : [];
        }
        return $this->persons[$username];
    }

    public function personName($username)
    {
        $p = $this->person($username);
        if (empty($p)) return $username;
        return trim(($p['first'] ?? '') . ' ' . ($p['last'] ?? ''));
    }

    public function roles($username): array
    {
        if ($username == $this->user) return $this->Settings->roles;
        $roles = DB::doc2Arr($this->person($username)['roles'] ?? []);
        $roles[] = 'user';
        return array_values(array_unique($roles));
    }

    /** All groups by id, with name, parent and heads. */
    public function groups(): array
    {
        if ($this->groups === null) {
            $this->groups = [];
            $cursor = $this->db->groups->find([], ['projection' => ['id' => 1, 'name' => 1, 'name_de' => 1, 'parent' => 1, 'head' => 1, 'inactive' => 1, 'level' => 1]]);
            foreach ($cursor as $g) {
                $g = DB::doc2Arr($g);
                $g['head'] = array_values(array_filter(DB::doc2Arr($g['head'] ?? [])));
                $this->groups[$g['id']] = $g;
            }
        }
        return $this->groups;
    }

    public function groupName($id)
    {
        $g = $this->groups()[$id] ?? null;
        if (empty($g)) return $id;
        return lang($g['name'] ?? $id, $g['name_de'] ?? null);
    }

    /** Ids of the unit and all units above it. */
    public function unitChain($id): array
    {
        $groups = $this->groups();
        $chain = [];
        while (!empty($id) && isset($groups[$id]) && !in_array($id, $chain)) {
            $chain[] = $id;
            $id = $groups[$id]['parent'] ?? null;
        }
        return $chain;
    }

    /** Units a person currently belongs to. */
    public function personUnits($username): array
    {
        $units = DB::doc2Arr($this->person($username)['units'] ?? []);
        $today = date('Y-m-d');
        $result = [];
        foreach ($units as $u) {
            $u = DB::doc2Arr($u);
            if (empty($u['unit'])) continue;
            if (!empty($u['start']) && substr((string) $u['start'], 0, 10) > $today) continue;
            if (!empty($u['end']) && substr((string) $u['end'], 0, 10) < $today) continue;
            $result[] = $u['unit'];
        }
        return array_values(array_unique($result));
    }

    public function isUnitHead($username): bool
    {
        foreach ($this->groups() as $g) {
            if (in_array($username, $g['head'])) return true;
        }
        return false;
    }

    /**
     * Does a person belong to an audience?
     * Audience keys: all (true), roles, units (incl. units below), persons, unit_heads (true).
     */
    public function inAudience($audience, $username): bool
    {
        if (empty($audience) || empty($username)) return false;
        if (!empty($audience['all'])) return true;
        if (!empty($audience['persons']) && in_array($username, $audience['persons'])) return true;
        if (!empty($audience['roles']) && array_intersect($audience['roles'], $this->roles($username))) return true;
        if (!empty($audience['unit_heads']) && $this->isUnitHead($username)) return true;
        if (!empty($audience['units'])) {
            foreach ($this->personUnits($username) as $unit) {
                if (array_intersect($audience['units'], $this->unitChain($unit))) return true;
            }
        }
        return false;
    }

    /** Usernames of an audience (roles and persons; units and unit heads are not expanded). */
    public function audienceUsers($audience): array
    {
        $users = array_merge($audience['persons'] ?? [], $this->personsWithRoles($audience['roles'] ?? []));
        return array_values(array_unique(array_filter($users)));
    }

    /** Active persons with one of these roles. */
    public function personsWithRoles(array $roles): array
    {
        if (empty($roles)) return [];
        $cursor = $this->db->persons->find(
            ['roles' => ['$in' => array_values($roles)], 'is_active' => ['$ne' => false]],
            ['projection' => ['username' => 1]]
        );
        return array_column(DB::doc2Arr($cursor->toArray()), 'username');
    }

    /* ------------------------------------
       APPROVAL CHAIN
    ------------------------------------ */

    /**
     * Who decides on a step of a case, as usernames. The creator never
     * decides on their own case. Unit heads: heads of the case unit; if the
     * creator is the head, the step is skipped (`self: skip`, default) or goes
     * to the next unit above (`self: next`).
     *
     * @return array|null usernames, or null if the step is skipped
     */
    public function approvers($case, $step)
    {
        $creator = $case['created_by'] ?? null;
        $rule = $step['approvers'] ?? [];
        $users = [];
        if (!empty($rule['persons'])) $users = array_merge($users, $rule['persons']);
        if (!empty($rule['roles'])) $users = array_merge($users, $this->personsWithRoles($rule['roles']));
        if (!empty($rule['unit_head'])) {
            $heads = [];
            $chain = $this->unitChain($case['unit'] ?? null);
            foreach ($chain as $unit) {
                $heads = array_diff($this->groups()[$unit]['head'], [$creator]);
                $creatorIsHead = in_array($creator, $this->groups()[$unit]['head']);
                if ($creatorIsHead && ($rule['self'] ?? 'skip') == 'skip' && empty($rule['roles']) && empty($rule['persons'])) {
                    return null;
                }
                if (!empty($heads)) break;
            }
            $users = array_merge($users, $heads);
        }
        $users = array_values(array_unique(array_filter($users, function ($u) use ($creator) {
            return !empty($u) && $u != $creator;
        })));
        return $users;
    }

    public function isApprover($case, $step, $username = null): bool
    {
        $username = $username ?? $this->user;
        if (empty($username) || $username == ($case['created_by'] ?? null)) return false;
        $rule = $step['approvers'] ?? [];
        if (!empty($rule['persons']) && in_array($username, $rule['persons'])) return true;
        if (!empty($rule['roles']) && array_intersect($rule['roles'], $this->roles($username))) return true;
        if (!empty($rule['unit_head'])) {
            $approvers = $this->approvers($case, ['approvers' => ['unit_head' => true, 'self' => $rule['self'] ?? 'skip']]);
            if (!empty($approvers) && in_array($username, $approvers)) return true;
        }
        return false;
    }

    /** Does a step apply to the case (condition met and not skipped)? */
    public function stepApplies($case, $step): bool
    {
        if (!empty($step['condition']) && !ProcessConditions::check($step['condition'], $case['values'] ?? [])) return false;
        return $this->approvers($case, $step) !== null;
    }

    /** Position of a step in the type definition, or null if it does not exist (anymore). */
    public function stepIndex($type, $stepId)
    {
        foreach ($type['steps'] ?? [] as $i => $step) {
            if ($step['id'] === $stepId) return $i;
        }
        return null;
    }

    /**
     * Current step definition of a submitted or returned case. Cases store
     * the step id, so steps can be added or reordered in the definition.
     */
    public function currentStep($case)
    {
        if (($case['status'] ?? '') != 'submitted' && ($case['status'] ?? '') != 'returned') return null;
        $type = $this->type($case['type']);
        $i = $this->stepIndex($type, $case['step'] ?? null);
        return $i === null ? null : $type['steps'][$i];
    }

    /** Open case whose current step was removed from the definition. */
    public function hasLostStep($case): bool
    {
        return in_array($case['status'] ?? '', ['submitted', 'returned']) && $this->currentStep($case) === null;
    }

    /**
     * Steps of a case with their state: done, skipped, current, open.
     */
    public function stepStates($case): array
    {
        $type = $this->type($case['type']);
        $states = DB::doc2Arr($case['steps'] ?? []);
        $result = [];
        foreach ($type['steps'] ?? [] as $i => $step) {
            $state = DB::doc2Arr($states[$step['id']] ?? []);
            $status = $state['status'] ?? 'open';
            if ($status == 'open') {
                if (in_array($case['status'], ['submitted', 'returned']) && ($case['step'] ?? null) === $step['id']) {
                    $status = $case['status'] == 'returned' ? 'returned' : 'current';
                } elseif (!$this->stepApplies($case, $step)) {
                    $status = 'skipped';
                } elseif (!in_array($case['status'], self::OPEN)) {
                    $status = 'not-reached';
                }
            }
            $result[] = ['step' => $step, 'index' => $i, 'status' => $status, 'by' => $state['by'] ?? null, 'at' => $state['at'] ?? null];
        }
        return $result;
    }

    /* ------------------------------------
       PERMISSIONS
    ------------------------------------ */

    public function canCreate($type, $username = null): bool
    {
        if (empty($type) || ($type['disabled'] ?? false)) return false;
        return $this->inAudience($type['create'] ?? [], $username ?? $this->user);
    }

    /** Can see all cases of the type (e.g. HR for staff requests). */
    public function canSeeAll($type, $username = null): bool
    {
        return $this->inAudience($type['view_all'] ?? [], $username ?? $this->user);
    }

    public function isParticipant($case, $username = null): bool
    {
        $username = $username ?? $this->user;
        if (empty($username)) return false;
        if (($case['created_by'] ?? null) == $username) return true;
        if (in_array($username, DB::doc2Arr($case['participants'] ?? []))) return true;
        foreach (DB::doc2Arr($case['history'] ?? []) as $h) {
            if (($h['by'] ?? null) == $username) return true;
        }
        return false;
    }

    /**
     * Permissions of the current user on a case.
     */
    public function permissions($case): array
    {
        $perm = ['view' => false, 'edit' => false, 'edit_fields' => [], 'decide' => false, 'submit' => false, 'withdraw' => false, 'delete' => false, 'upload' => false];
        $type = $this->type($case['type'] ?? '');
        if (empty($type) || empty($this->user)) return $perm;
        if (ProcessPhases::isPhases($type)) return ProcessPhases::permissions($this, $case, $type);

        $status = $case['status'] ?? 'draft';
        $own = ($case['created_by'] ?? null) == $this->user;
        $step = $this->currentStep($case);
        $approver = $status == 'submitted' && $step && $this->isApprover($case, $step);

        $perm['decide'] = $approver;
        $perm['view'] = $own || $approver || $this->isParticipant($case) || $this->canSeeAll($type);
        if ($status == 'draft') {
            // drafts are private
            $perm['view'] = $own;
            $perm['decide'] = false;
        }

        $fields = ProcessForm::fieldIds($type);
        if ($own && in_array($status, ['draft', 'returned'])) {
            $perm['edit'] = true;
            $perm['edit_fields'] = array_values(array_filter($fields, function ($id) use ($type) {
                return empty(ProcessForm::field($type, $id)['only_steps']);
            }));
            $perm['submit'] = true;
        } elseif ($approver && !empty($step['editable'])) {
            $perm['edit'] = true;
            $perm['edit_fields'] = array_values(array_intersect($fields, $step['editable']));
        }
        $perm['withdraw'] = $own && in_array($status, ['submitted', 'returned']);
        $perm['delete'] = $own && $status == 'draft';
        $perm['upload'] = ($type['attachments'] ?? true) && (($own && in_array($status, self::OPEN)) || $approver);
        return $perm;
    }

    /**
     * Cases of an area that the current user may see.
     * view: mine (created by me), todo (waiting for my decision), all
     */
    public function listCases($area, $view = 'mine'): array
    {
        $types = array_keys($this->types($area));
        if (empty($types)) return [];
        $filter = ['type' => ['$in' => $types]];
        if ($view == 'mine') {
            $filter['created_by'] = $this->user;
        } elseif ($view == 'todo') {
            $filter['status'] = ['$in' => ['submitted', 'active', 'on_hold']];
        } else {
            $filter['status'] = ['$ne' => 'draft'];
        }
        $cases = $this->db->proc_cases->find($filter, ['sort' => ['updated_at' => -1]])->toArray();
        $result = [];
        foreach ($cases as $case) {
            $case = DB::doc2Arr($case);
            $perm = $this->permissions($case);
            if (!$perm['view']) continue;
            if ($view == 'todo') {
                // approval: my decision; phases: my open tasks
                $isPhases = ProcessPhases::isPhases($this->type($case['type']));
                if (!$perm['decide'] && !($isPhases && ProcessPhases::myOpenTasks($this, $case))) continue;
            }
            $result[] = $case;
        }
        return $result;
    }

    /** Number of cases waiting for the current user's decision, per area. */
    public function todoCount($area): int
    {
        static $counts = [];
        if (!isset($counts[$area])) $counts[$area] = count($this->listCases($area, 'todo'));
        return $counts[$area];
    }

    /** Area with only phase types (todo means tasks there, not approvals). */
    public function isPhaseArea($area): bool
    {
        $types = $this->types($area);
        if (empty($types)) return false;
        foreach ($types as $t) {
            if (!ProcessPhases::isPhases($t)) return false;
        }
        return true;
    }

    /** Label of the todo list of an area. */
    public function todoLabel($area): string
    {
        return $this->isPhaseArea($area) ? lang('My tasks', 'Meine Aufgaben') : lang('To approve', 'Zur Freigabe');
    }

    /** Is the area relevant for the current user (create, decide, see)? */
    public function areaAccess($area): array
    {
        $access = ['create' => false, 'all' => false, 'mine' => false, 'todo' => false, 'overview' => false];
        foreach ($this->types($area) as $type) {
            if ($this->canCreate($type)) $access['create'] = true;
            if ($this->canSeeAll($type)) $access['all'] = true;
            if (ProcessPhases::isPhases($type)) {
                $manage = ProcessPhases::canManage($this, $type);
                if ($manage) $access['all'] = true;
                if ($manage || $this->canSeeAll($type)) $access['overview'] = true;
                if (ProcessPhases::hasTasksInType($this, $type)) $access['todo'] = true;
                continue;
            }
            foreach ($type['steps'] ?? [] as $step) {
                $rule = $step['approvers'] ?? [];
                if (
                    (!empty($rule['roles']) && array_intersect($rule['roles'], $this->Settings->roles))
                    || (!empty($rule['persons']) && in_array($this->user, $rule['persons']))
                    || (!empty($rule['unit_head']) && $this->isUnitHead($this->user))
                ) {
                    $access['todo'] = true;
                }
            }
        }
        $types = array_keys($this->types($area));
        if (!empty($types)) {
            $access['mine'] = $access['create'] || $this->db->proc_cases->countDocuments(['type' => ['$in' => $types], 'created_by' => $this->user]) > 0;
            if (!$access['all'] && !$access['todo']) {
                // involved in other cases, e.g. as participant or former approver
                $access['all'] = $this->db->proc_cases->countDocuments([
                    'type' => ['$in' => $types],
                    'status' => ['$ne' => 'draft'],
                    '$or' => [['participants' => $this->user], ['history.by' => $this->user]],
                    'created_by' => ['$ne' => $this->user],
                ]) > 0;
            }
        }
        return $access;
    }

    /* ------------------------------------
       CASES
    ------------------------------------ */

    public function getCase($id)
    {
        $id = strval($id);
        if (!DB::is_ObjectID($id)) return null;
        $case = $this->db->proc_cases->findOne(['_id' => DB::to_ObjectID($id)]);
        return $case ? DB::doc2Arr($case) : null;
    }

    public function title($case, $type = null)
    {
        $type = $type ?? $this->type($case['type']);
        $template = $type['title'] ?? null;
        $values = $case['values'] ?? [];
        if ($template) {
            $title = preg_replace_callback('/\{([a-z0-9_]+)\}/', function ($m) use ($values, $type) {
                return ProcessForm::plainValue(ProcessForm::field($type, $m[1]), $values[$m[1]] ?? null, $this);
            }, $template);
            $title = trim(preg_replace('/\s+/', ' ', $title), " -–,");
            if ($title !== '') return $title;
        }
        return self::t($type, 'name');
    }

    /** Next case number, e.g. PA-2026-0001. */
    public function nextNumber($type)
    {
        $prefix = ($type['prefix'] ?? strtoupper(substr($type['id'], 0, 3))) . '-' . date('Y');
        $counter = $this->db->proc_counters->findOneAndUpdate(
            ['_id' => $prefix],
            ['$inc' => ['seq' => 1]],
            ['upsert' => true, 'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );
        return $prefix . '-' . str_pad((string) $counter['seq'], 4, '0', STR_PAD_LEFT);
    }

    /** Participants: creator plus persons from person fields marked as participant. */
    public function participants($type, $values): array
    {
        $users = [];
        foreach ($type['fields'] as $field) {
            if (empty($field['participant']) || empty($values[$field['id']])) continue;
            $v = $values[$field['id']];
            $users = array_merge($users, is_array($v) ? $v : [$v]);
        }
        return array_values(array_unique(array_filter($users)));
    }

    /** Unit of the case: from the unit field of the type, else the first unit of the creator. */
    public function caseUnit($type, $values, $creator)
    {
        $field = $type['unit_field'] ?? null;
        if ($field && !empty($values[$field])) return $values[$field];
        return $this->personUnits($creator)[0] ?? null;
    }

    public function create($type, $values, $startPhase = null)
    {
        $now = date('Y-m-d H:i:s');
        $case = [
            'type' => $type['id'],
            'area' => $type['area'],
            'number' => null,
            'status' => 'draft',
            'step' => null,
            'values' => $values,
            'unit' => $this->caseUnit($type, $values, $this->user),
            'participants' => $this->participants($type, $values),
            'steps' => (object) [],
            'files' => [],
            'history' => [['at' => $now, 'by' => $this->user, 'action' => 'created']],
            'created_by' => $this->user,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (ProcessPhases::isPhases($type)) {
            // projects are tracked from the start, there is no draft
            $case = array_merge($case, ProcessPhases::init($type, $startPhase, $now));
            $case['number'] = $this->nextNumber($type);
            unset($case['steps']);
        }
        $case['title'] = $this->title($case, $type);
        $result = $this->db->proc_cases->insertOne($case);
        return strval($result->getInsertedId());
    }

    public function updateValues($case, $values, $comment = null)
    {
        $type = $this->type($case['type']);
        $old = DB::doc2Arr($case['values'] ?? []);
        $changed = array_keys(array_filter($values, function ($v, $k) use ($old) {
            return ($old[$k] ?? null) !== $v;
        }, ARRAY_FILTER_USE_BOTH));
        $merged = array_merge($old, $values);
        $case['values'] = $merged;
        $set = [
            'values' => $merged,
            'participants' => $this->participants($type, $merged),
            'updated_at' => date('Y-m-d H:i:s'),
            'title' => $this->title($case, $type),
        ];
        // the unit only changes as long as the case is not in approval
        if (in_array($case['status'], ['draft', 'returned'])) {
            $set['unit'] = $this->caseUnit($type, $merged, $case['created_by']);
        }
        $update = ['$set' => $set];
        if ($case['status'] != 'draft' && !empty($changed)) {
            $update['$push'] = ['history' => ['at' => $set['updated_at'], 'by' => $this->user, 'action' => 'edited', 'fields' => $changed, 'comment' => $comment]];
        }
        $this->db->proc_cases->updateOne(['_id' => DB::to_ObjectID($case['_id'])], $update);
    }

    /**
     * Status transitions. Returns an error message or null.
     * Actions: submit, approve, return, reject, withdraw, delete
     */
    public function act($case, $action, $comment = '', $target = null)
    {
        $type = $this->type($case['type']);
        if (ProcessPhases::isPhases($type)) {
            if ($action == 'delete') {
                if (!$this->permissions($case)['delete']) return lang('You cannot delete this case.', 'Du kannst diesen Vorgang nicht löschen.');
                ProcessFiles::deleteAll($this, $case);
                $this->db->proc_cases->deleteOne(['_id' => DB::to_ObjectID($case['_id'])]);
                return null;
            }
            return ProcessPhases::act($this, $case, $action, $target, trim((string) $comment));
        }
        $perm = $this->permissions($case);
        $comment = trim((string) $comment);
        $now = date('Y-m-d H:i:s');
        $id = DB::to_ObjectID($case['_id']);
        $entry = ['at' => $now, 'by' => $this->user, 'action' => $action, 'comment' => $comment ?: null];
        $step = $this->currentStep($case);
        if ($step) $entry['step'] = $step['id'];
        $set = ['updated_at' => $now];

        switch ($action) {
            case 'submit':
                if (!$perm['submit']) return lang('You cannot submit this case.', 'Du kannst diesen Vorgang nicht einreichen.');
                $missing = ProcessForm::missingRequired($type, $case['values'] ?? []);
                if (!empty($missing)) {
                    return lang('Please fill in all required fields: ', 'Bitte fülle alle Pflichtfelder aus: ') . implode(', ', $missing);
                }
                if (empty($case['number'])) $set['number'] = $this->nextNumber($type);
                if ($case['status'] == 'draft') $set['submitted_at'] = $now;
                $entry['action'] = $case['status'] == 'returned' ? 'resubmitted' : 'submitted';
                unset($entry['step']);
                // from the start, skipping approved steps: a returned case continues at the
                // step that returned it, or earlier if the changes made a step necessary
                $next = $this->nextStep($case, 0);
                $set += $this->stepTarget($type, $next, $now);
                break;
            case 'approve':
                if (!$perm['decide']) return lang('You cannot decide on this case.', 'Du kannst über diesen Vorgang nicht entscheiden.');
                $set['steps.' . $step['id']] = ['status' => 'done', 'by' => $this->user, 'at' => $now];
                $next = $this->nextStep($case, $this->stepIndex($type, $step['id']) + 1);
                $set += $this->stepTarget($type, $next, $now);
                break;
            case 'return':
            case 'reject':
                if (!$perm['decide']) return lang('You cannot decide on this case.', 'Du kannst über diesen Vorgang nicht entscheiden.');
                if ($comment === '') return lang('Please give a reason.', 'Bitte gib eine Begründung an.');
                $set['status'] = $action == 'return' ? 'returned' : 'rejected';
                if ($action == 'reject') $set['closed_at'] = $now;
                break;
            case 'withdraw':
                if (!$perm['withdraw']) return lang('You cannot withdraw this case.', 'Du kannst diesen Vorgang nicht zurückziehen.');
                $set['status'] = 'withdrawn';
                $set['closed_at'] = $now;
                break;
            case 'delete':
                if (!$perm['delete']) return lang('You cannot delete this case.', 'Du kannst diesen Vorgang nicht löschen.');
                ProcessFiles::deleteAll($this, $case);
                $this->db->proc_cases->deleteOne(['_id' => $id]);
                return null;
            default:
                return lang('Unknown action.', 'Unbekannte Aktion.');
        }

        $this->db->proc_cases->updateOne(['_id' => $id], ['$set' => $set, '$push' => ['history' => $entry]]);
        $case = $this->getCase($case['_id']);
        ProcessNotify::afterAction($this, $case, $entry);
        return null;
    }

    /** Index of the next step that applies and is not approved yet, starting at $from, or null if none is left. */
    private function nextStep($case, $from)
    {
        $type = $this->type($case['type']);
        $steps = $type['steps'] ?? [];
        $states = DB::doc2Arr($case['steps'] ?? []);
        for ($i = $from; $i < count($steps); $i++) {
            if ((DB::doc2Arr($states[$steps[$i]['id']] ?? [])['status'] ?? null) == 'done') continue;
            if ($this->stepApplies($case, $steps[$i])) return $i;
        }
        return null;
    }

    private function stepTarget($type, $next, $now): array
    {
        if ($next === null) return ['status' => 'approved', 'step' => null, 'closed_at' => $now];
        return ['status' => 'submitted', 'step' => $type['steps'][$next]['id']];
    }

    /** Hints of the type (e.g. deadlines) that apply to the values. */
    public function hints($type, $values): array
    {
        $hints = [];
        foreach ($type['hints'] ?? [] as $hint) {
            if (!empty($hint['condition']) && !ProcessConditions::check($hint['condition'], $values)) continue;
            if (!empty($hint['date_field'])) {
                $date = $values[$hint['date_field']] ?? null;
                if (empty($date)) continue;
                $days = (strtotime($date) - strtotime(date('Y-m-d'))) / 86400;
                if ($days >= ($hint['min_days_ahead'] ?? 0)) continue;
            }
            $hints[] = self::t($hint, 'message');
        }
        return $hints;
    }

    /* ------------------------------------
       CSRF
    ------------------------------------ */

    public static function csrfToken()
    {
        if (empty($_SESSION['proc_csrf'])) $_SESSION['proc_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['proc_csrf'];
    }

    public static function csrfField()
    {
        return '<input type="hidden" name="csrf" value="' . self::csrfToken() . '">';
    }

    public static function csrfCheck()
    {
        $token = $_POST['csrf'] ?? '';
        if (empty($_SESSION['proc_csrf']) || !is_string($token) || !hash_equals($_SESSION['proc_csrf'], $token)) {
            abortwith(400, lang('The form has expired. Please reload the page.', 'Das Formular ist abgelaufen. Bitte lade die Seite neu.'));
        }
    }

    /* ------------------------------------
       CORE HOOKS
    ------------------------------------ */

    /** Sidebar sections, one per area the user has access to (hook `sidebar`). */
    public static function sidebarGroups($Settings): array
    {
        $P = self::get();
        if (!$P->enabled()) return [];
        $groups = [];
        foreach ($P->areas() as $areaId => $area) {
            if (empty($P->types($areaId))) continue;
            $access = $P->areaAccess($areaId);
            $base = '/processes/' . $areaId;
            $items = [];
            if ($access['create']) {
                $items[] = self::sidebarItem("proc-$areaId-new", lang('New', 'Neu'), 'plus-circle', "$base/new", ["^/processes/$areaId/new"]);
            }
            if ($access['mine']) {
                $items[] = self::sidebarItem("proc-$areaId-mine", lang('My cases', 'Meine Vorgänge'), 'folder-user', "$base?view=mine", ["^/processes/$areaId(\\?view=mine)?$"]);
            }
            if ($access['todo']) {
                $label = $P->todoLabel($areaId);
                $n = $P->todoCount($areaId);
                if ($n > 0) $label .= ' <small class="sidebar-index danger">' . $n . '</small>';
                $items[] = self::sidebarItem("proc-$areaId-todo", $label, 'stamp', "$base?view=todo", ["^/processes/$areaId\\?view=todo"], false);
            }
            if ($access['overview']) {
                $items[] = self::sidebarItem("proc-$areaId-overview", lang('Overview', 'Übersicht'), 'kanban', "$base?view=overview", ["^/processes/$areaId\\?view=overview"]);
            }
            if ($access['all']) {
                $items[] = self::sidebarItem("proc-$areaId-all", lang('All cases', 'Alle Vorgänge'), 'files', "$base?view=all", ["^/processes/$areaId\\?view=all"]);
            }
            if (empty($items)) continue;
            $groups[] = [
                'id' => 'sidebar-proc-' . $areaId,
                'label' => e(self::t($area)),
                'items' => $items,
            ];
        }
        return $groups;
    }

    private static function sidebarItem($id, $label, $icon, $url, $active, $favoritable = true): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'icon' => $icon,
            'url' => $url,
            'active' => $active,
            'feature' => null,
            'default' => false,
            'permission' => null,
            'favoritable' => $favoritable,
            'hasSearch' => false,
        ];
    }

    /** Entries for "My tasks" in the sidebar (hook `tasks`). */
    public static function tasks($Settings): array
    {
        $P = self::get();
        if (!$P->enabled()) return [];
        $tasks = [];
        foreach ($P->areas() as $areaId => $area) {
            if (empty($P->types($areaId))) continue;
            $n = $P->todoCount($areaId);
            if ($n == 0) continue;
            $tasks[] = [
                'url' => "/processes/$areaId?view=todo",
                'icon' => 'stamp',
                'label' => self::t($area) . ': ' . $P->todoLabel($areaId),
                'count' => $n,
            ];
        }
        return $tasks;
    }
}
