<?php

/** Phase flow (`"flow": "phases"`). */
class ProcessPhases
{
    public const STATUSES = ['active', 'on_hold', 'published', 'rejected', 'withdrawn'];
    public const OPEN = ['active', 'on_hold'];

    public static function isPhases($type): bool
    {
        return ($type['flow'] ?? 'approval') == 'phases';
    }

    /**
     * Fields of a phase type as one list: base fields, then per phase a
     * heading, the note and the phase fields (marked with `phase`).
     */
    public static function expandFields($type): array
    {
        $fields = $type['fields'] ?? [];
        foreach ($type['phases'] as $phase) {
            $fields[] = ['id' => '_h_' . $phase['id'], 'type' => 'heading', 'label' => $phase['label'], 'label_en' => $phase['label_en'] ?? null, 'phase' => $phase['id'], 'help' => $phase['help'] ?? null, 'help_en' => $phase['help_en'] ?? null];
            $fields[] = ['id' => 'note_' . $phase['id'], 'type' => 'text', 'label' => 'Notiz', 'label_en' => 'Note', 'rows' => 2, 'phase' => $phase['id']];
            foreach ($phase['fields'] ?? [] as $field) {
                $field['phase'] = $phase['id'];
                $fields[] = $field;
            }
        }
        return $fields;
    }

    public static function validate($type, $file)
    {
        if (empty($type['phases']) || !is_array($type['phases'])) return "$file: 'phases' is missing";
        if (!empty($type['year_field'])) {
            $base = array_column($type['fields'] ?? [], null, 'id');
            if (($base[$type['year_field']]['type'] ?? null) != 'int') return "$file: year_field '{$type['year_field']}' must be an int field of the base data";
            if (!empty($type['year_extra_field']) && !isset($base[$type['year_extra_field']])) return "$file: year_extra_field '{$type['year_extra_field']}' is not a field of the base data";
        }
        if (!empty($type['category_field']) && !in_array($type['category_field'], array_column($type['fields'] ?? [], 'id'))) {
            return "$file: category_field '{$type['category_field']}' is not a field";
        }
        $ids = [];
        foreach ($type['phases'] as $phase) {
            if (empty($phase['id']) || !preg_match('/^[a-z0-9_]+$/', $phase['id']) || in_array($phase['id'], $ids)) {
                return "$file: phase without valid id or id used twice";
            }
            $ids[] = $phase['id'];
            $rules = empty($phase['due']) ? [] : (isset($phase['due'][0]) ? $phase['due'] : [$phase['due']]);
            foreach ($rules as $rule) {
                if (!in_array($rule['field'] ?? null, array_column(self::expandFields($type), 'id'))) {
                    return "$file: deadline of phase '{$phase['id']}' refers to an unknown field";
                }
            }
            $tasks = [];
            foreach ($phase['tasks'] ?? [] as $task) {
                if (empty($task['id']) || in_array($task['id'], $tasks)) return "$file: task without id or id used twice in phase '{$phase['id']}'";
                $tasks[] = $task['id'];
            }
        }
        return null;
    }

    public static function phaseIndex($type, $phaseId)
    {
        foreach ($type['phases'] as $i => $phase) {
            if ($phase['id'] === $phaseId) return $i;
        }
        return null;
    }

    public static function phase($type, $phaseId)
    {
        $i = self::phaseIndex($type, $phaseId);
        return $i === null ? null : $type['phases'][$i];
    }

    /**
     * Fields of one section of the form: 'base' (fields outside the phases)
     * or a phase id. Used to edit one phase at a time.
     */
    public static function sectionFields($type, $section): array
    {
        return array_values(array_filter($type['fields'], function ($f) use ($section) {
            return $section == 'base' ? empty($f['phase']) : ($f['phase'] ?? null) === $section;
        }));
    }

    public static function canManage(Processes $P, $type, $username = null): bool
    {
        return $P->inAudience($type['manage'] ?? [], $username ?? $P->user);
    }

    /* ------------------------------------
       TASKS
    ------------------------------------ */

    /**
     * Tasks of the case that are due: their phase has been reached, the
     * condition is met. Each with its state (open or done).
     */
    public static function tasks(Processes $P, $case, $type = null): array
    {
        $type = $type ?? $P->type($case['type']);
        $current = self::phaseIndex($type, $case['phase'] ?? null) ?? -1;
        $states = DB::doc2Arr($case['tasks'] ?? []);
        $values = DB::doc2Arr($case['values'] ?? []);
        $result = [];
        foreach ($type['phases'] as $i => $phase) {
            foreach ($phase['tasks'] ?? [] as $task) {
                $state = DB::doc2Arr($states[$task['id']] ?? []);
                $done = ($state['status'] ?? null) == 'done';
                if (!$done && $i > $current) continue;
                if (!$done && !empty($task['condition']) && !ProcessConditions::check($task['condition'], $values)) continue;
                $result[] = ['task' => $task, 'phase' => $phase, 'done' => $done, 'state' => $state];
            }
        }
        return $result;
    }

    public static function isAssignee(Processes $P, $task, $username = null): bool
    {
        return $P->inAudience($task['assignees'] ?? [], $username ?? $P->user);
    }

    /** Open tasks of the case for the user. */
    public static function myOpenTasks(Processes $P, $case, $username = null): array
    {
        if (!in_array($case['status'] ?? '', self::OPEN)) return [];
        return array_values(array_filter(self::tasks($P, $case), function ($t) use ($P, $username) {
            return !$t['done'] && self::isAssignee($P, $t['task'], $username);
        }));
    }

    /** Is the user assignee of any task of the type? */
    public static function hasTasksInType(Processes $P, $type, $username = null): bool
    {
        foreach ($type['phases'] as $phase) {
            foreach ($phase['tasks'] ?? [] as $task) {
                if (self::isAssignee($P, $task, $username)) return true;
            }
        }
        return false;
    }

    /* ------------------------------------
       PERMISSIONS
    ------------------------------------ */

    /** `owner_manage`: creator and participants manage their own case. */
    public static function isOwner(Processes $P, $case, $type, $username = null): bool
    {
        $username = $username ?? $P->user;
        if (empty($type['owner_manage']) || empty($username)) return false;
        return ($case['created_by'] ?? null) == $username || in_array($username, DB::doc2Arr($case['participants'] ?? []));
    }

    public static function permissions(Processes $P, $case, $type): array
    {
        $manage = self::canManage($P, $type) || self::isOwner($P, $case, $type);
        $assigned = false;
        foreach (self::tasks($P, $case, $type) as $t) {
            if (self::isAssignee($P, $t['task'])) $assigned = true;
        }
        $view = $manage || $assigned || $P->isParticipant($case) || $P->canSeeAll($type);
        return [
            'view' => $view,
            'edit' => $manage,
            'edit_fields' => $manage ? ProcessForm::fieldIds($type) : [],
            'decide' => false,
            'submit' => false,
            'withdraw' => false,
            'delete' => $manage,
            'upload' => $manage || ($assigned && in_array($case['status'], self::OPEN)),
            'manage' => $manage,
            'tasks' => $assigned || $manage,
        ];
    }

    /* ------------------------------------
       ACTIONS
    ------------------------------------ */

    /** Initial data of a new case. */
    public static function init($type, $startPhase, $now): array
    {
        $phase = self::phase($type, $startPhase) ?? $type['phases'][0];
        return [
            'status' => 'active',
            'phase' => $phase['id'],
            'phase_log' => [$phase['id'] => ['start' => substr($now, 0, 10), 'end' => null]],
            'tasks' => (object) [],
        ];
    }

    /**
     * Actions: phase (move to $target), status ($target), task_done and
     * task_reopen ($target = task id). Returns an error message or null.
     */
    public static function act(Processes $P, $case, $action, $target, $comment)
    {
        $type = $P->type($case['type']);
        $perm = self::permissions($P, $case, $type);
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $id = DB::to_ObjectID($case['_id']);
        $set = ['updated_at' => $now];
        $entry = ['at' => $now, 'by' => $P->user, 'action' => $action, 'comment' => $comment ?: null];

        switch ($action) {
            case 'phase':
                if (!$perm['manage']) return lang('You cannot change the phase.', 'Du kannst die Phase nicht ändern.');
                $to = self::phaseIndex($type, $target);
                $from = self::phaseIndex($type, $case['phase'] ?? null);
                if ($to === null || $to === $from) return lang('Please choose another phase.', 'Bitte wähle eine andere Phase.');
                $log = DB::doc2Arr($case['phase_log'] ?? []);
                if ($from !== null) {
                    $set['phase_log.' . $case['phase'] . '.end'] = $today;
                }
                // a phase entered again starts anew
                $set['phase_log.' . $target] = ['start' => $today, 'end' => null];
                $set['phase'] = $target;
                $entry['from'] = $case['phase'] ?? null;
                $entry['to'] = $target;
                break;
            case 'status':
                if (!$perm['manage']) return lang('You cannot change the status.', 'Du kannst den Status nicht ändern.');
                if (!in_array($target, self::STATUSES) || $target == $case['status']) return lang('Please choose another status.', 'Bitte wähle einen anderen Status.');
                $set['status'] = $target;
                $set['closed_at'] = in_array($target, self::OPEN) ? null : $now;
                $entry['to'] = $target;
                break;
            case 'task_done':
            case 'task_reopen':
                $task = null;
                foreach (self::tasks($P, $case, $type) as $t) {
                    if ($t['task']['id'] === $target) $task = $t;
                }
                if (!$task) return lang('Task not found.', 'Aufgabe nicht gefunden.');
                if (!$perm['manage'] && !self::isAssignee($P, $task['task'])) return lang('This is not your task.', 'Das ist nicht deine Aufgabe.');
                if ($action == 'task_done') {
                    $set['tasks.' . $target] = ['status' => 'done', 'by' => $P->user, 'at' => $now, 'comment' => $comment ?: null];
                } else {
                    $set['tasks.' . $target] = ['status' => 'open'];
                }
                $entry['task'] = $target;
                break;
            case 'year':
                if (!$perm['manage']) return lang('You cannot change the year.', 'Du kannst das Jahr nicht ändern.');
                $field = self::yearField($type);
                if (!$field) return lang('This kind of project has no planned year.', 'Diese Projektart hat kein geplantes Jahr.');
                if (!in_array($case['status'] ?? '', self::OPEN)) return lang('Closed projects cannot be moved.', 'Abgeschlossene Projekte lassen sich nicht verschieben.');
                $target = trim((string) $target);
                if ($target === '') {
                    if (!empty(ProcessForm::field($type, $field)['required'])) return lang('The year is required for this kind of project.', 'Das Jahr ist bei dieser Projektart Pflicht.');
                    $year = null;
                } else {
                    $year = preg_match('/^\d{4}$/', $target) ? (int) $target : 0;
                    if ($year < self::YEAR_MIN || $year > (int) date('Y') + self::YEAR_AHEAD) return lang('Please choose a valid year.', 'Bitte wähle ein gültiges Jahr.');
                }
                $values = DB::doc2Arr($case['values'] ?? []);
                $old = self::year($case, $type);
                if ($old === $year) return lang('Please choose another year.', 'Bitte wähle ein anderes Jahr.');
                $set['values.' . $field] = $year;
                $values[$field] = $year;
                $set['title'] = $P->title(['values' => $values] + $case, $type);
                $entry['field'] = $field;
                $entry['from'] = $old;
                $entry['to'] = $year;
                break;
            default:
                return lang('Unknown action.', 'Unbekannte Aktion.');
        }

        $P->db->proc_cases->updateOne(['_id' => $id], ['$set' => $set, '$push' => ['history' => $entry]]);
        $before = self::myOpenTasksAll($P, $case);
        $case = $P->getCase(strval($case['_id']));
        ProcessNotify::afterPhaseAction($P, $case, $entry, $before);
        return null;
    }

    /* ------------------------------------
       YEAR BOARD
    ------------------------------------ */

    public const YEAR_MIN = 2000;
    public const YEAR_AHEAD = 20;

    /** `year_field` of the type, or null. */
    public static function yearField($type): ?string
    {
        return $type['year_field'] ?? null;
    }

    /** Year of a case as int, or null. */
    public static function year($case, $type): ?int
    {
        $field = self::yearField($type);
        if (!$field) return null;
        $v = DB::doc2Arr($case['values'] ?? [])[$field] ?? null;
        return is_numeric($v) ? (int) $v : null;
    }

    /**
     * Category of a case: `category_field` (mapped by `category_map`), else `category`.
     */
    public static function category($case, $type): ?string
    {
        $field = $type['category_field'] ?? null;
        if ($field) {
            $value = DB::doc2Arr($case['values'] ?? [])[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $map = $type['category_map'] ?? null;
                if ($map === null) return $value;
                if (isset($map[$value])) return $map[$value];
            }
        }
        return $type['category'] ?? null;
    }

    /** Do deadlines of the type depend on the year field? */
    public static function dueDependsOnYear($type): bool
    {
        $field = self::yearField($type);
        if (!$field) return false;
        foreach ($type['phases'] as $phase) {
            $rules = empty($phase['due']) ? [] : (isset($phase['due'][0]) ? $phase['due'] : [$phase['due']]);
            if (in_array($field, array_column($rules, 'field'))) return true;
        }
        return false;
    }

    /** Ids of all open tasks of the case (for any assignee). */
    public static function myOpenTasksAll(Processes $P, $case): array
    {
        $ids = [];
        foreach (self::tasks($P, $case) as $t) {
            if (!$t['done']) $ids[] = $t['task']['id'];
        }
        return $ids;
    }

    /* ------------------------------------
       DURATION
    ------------------------------------ */

    /** Weeks since the current phase started. */
    public static function weeksInPhase($case): ?float
    {
        $start = DB::doc2Arr($case['phase_log'] ?? [])[$case['phase'] ?? ''] ?? null;
        $start = DB::doc2Arr($start)['start'] ?? null;
        if (empty($start)) return null;
        return (strtotime(date('Y-m-d')) - strtotime($start)) / (7 * 86400);
    }

    /** Text of the target duration, e.g. "4–6 weeks". */
    public static function durationLabel($phase): string
    {
        $d = $phase['duration_weeks'] ?? null;
        if (empty($d)) return '';
        [$min, $max] = [$d[0] ?? null, $d[1] ?? $d[0] ?? null];
        if ($max >= 9) {
            $range = $min == $max ? round($max / 4.33) : round($min / 4.33) . '–' . round($max / 4.33);
            return $range . ' ' . lang('months', 'Monate');
        }
        return ($min == $max ? $max : $min . '–' . $max) . ' ' . lang('weeks', 'Wochen');
    }

    /** Is the case longer in its current phase than the target duration? */
    public static function overdue($type, $case): bool
    {
        if (($case['status'] ?? '') != 'active') return false;
        $phase = self::phase($type, $case['phase'] ?? null);
        $max = $phase['duration_weeks'][1] ?? $phase['duration_weeks'][0] ?? null;
        $weeks = self::weeksInPhase($case);
        if ($max !== null && $weeks !== null && $weeks > $max) return true;
        $due = self::dueDate($phase, DB::doc2Arr($case['values'] ?? []));
        return $due !== null && $due < date('Y-m-d');
    }

    /** Number shown for a phase, counted from `number_from` (default 0). */
    public static function number($type, $i): int
    {
        return $i + (int) ($type['number_from'] ?? 0);
    }

    /* ------------------------------------
       DEADLINES
    ------------------------------------ */

    /**
     * Deadline of a phase from a field of the case (Y-m-d or null):
     * {"field": "vl_datum", "months": -3, "days": 0} counts from a date field,
     * {"field": "jahr", "date": "03-31", "year_offset": 0} takes the year
     * of a date field or a year field. A list of such rules with a
     * `condition` each uses the first rule that applies.
     */
    public static function dueDate($phase, $values): ?string
    {
        $due = $phase['due'] ?? null;
        if (isset($due[0])) {
            $due = current(array_filter($due, function ($rule) use ($values) {
                return empty($rule['condition']) || ProcessConditions::check($rule['condition'], $values);
            })) ?: null;
        }
        if (empty($due['field'])) return null;
        $value = trim((string) ($values[$due['field']] ?? ''));
        $isYear = preg_match('/^\d{4}$/', $value);
        if ($value === '' || (!$isYear && !strtotime($value))) return null;
        if (isset($due['date'])) {
            $year = $isYear ? (int) $value : (int) date('Y', strtotime($value));
            $date = ($year + (int) ($due['year_offset'] ?? 0)) . '-' . $due['date'];
        } else {
            $date = date('Y-m-d', strtotime(sprintf('%s %+d months %+d days', $value, (int) ($due['months'] ?? 0), (int) ($due['days'] ?? 0))));
        }
        return strtotime($date) ? $date : null;
    }

    /** Deadline as text: the rule (`due_text`) and, if known, the date. */
    public static function dueLabel($phase, $values = null): string
    {
        $text = Processes::t($phase, 'due_text');
        $date = $values === null ? null : self::dueDate($phase, $values);
        if ($date) $text .= ($text ? ' · ' : '') . date('d.m.Y', strtotime($date));
        return $text;
    }
}
