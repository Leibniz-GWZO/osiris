<?php

/**
 * Form fields of process types: rendering, parsing and display.
 *
 * Field definition (in the type JSON):
 * {
 *   "id": "pa_nachname", "type": "string", "label": "Name", "label_en": "Last name",
 *   "required": true, "help": "...", "help_en": "...", "width": 6,
 *   "show_if": {condition}, "options": [{"value": "a", "label": "A", "label_en": "A"}],
 *   "default": "...", "only_steps": ["verwaltung"], "participant": true
 * }
 */
class ProcessForm
{
    public const TYPES = [
        'heading' => 'Heading',
        'string' => 'Single line text',
        'text' => 'Multi-line text',
        'int' => 'Integer',
        'float' => 'Number',
        'money' => 'Amount in EUR',
        'date' => 'Date',
        'bool' => 'Yes/No',
        'check' => 'Checkbox',
        'select' => 'Selection',
        'multiselect' => 'Multiple selection',
        'unit' => 'Organisational unit',
        'person' => 'Person',
        'contacts' => 'External contacts (name, e-mail, function)',
        'activity' => 'Link to an OSIRIS activity',
    ];

    public static function field($type, $id)
    {
        foreach ($type['fields'] ?? [] as $field) {
            if ($field['id'] == $id) return $field;
        }
        return null;
    }

    /** Ids of all fields that hold a value (no headings). */
    public static function fieldIds($type): array
    {
        $ids = [];
        foreach ($type['fields'] ?? [] as $field) {
            if ($field['type'] != 'heading') $ids[] = $field['id'];
        }
        return $ids;
    }

    public static function isEmpty($value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    public static function visible($field, $values): bool
    {
        return ProcessConditions::check($field['show_if'] ?? null, $values);
    }

    /** Labels of required fields that are visible but empty. */
    public static function missingRequired($type, $values): array
    {
        $missing = [];
        foreach ($type['fields'] as $field) {
            if ($field['type'] == 'heading' || empty($field['required'])) continue;
            if (!self::visible($field, $values)) continue;
            if (self::isEmpty($values[$field['id']] ?? null)) $missing[] = Processes::t($field);
        }
        return $missing;
    }

    /**
     * Values from the form post for the editable fields, checked and converted.
     * Fields that are hidden by their condition are emptied.
     */
    public static function parse($type, $post, array $editable, array $old, Processes $P): array
    {
        $values = [];
        foreach ($type['fields'] as $field) {
            $id = $field['id'];
            if ($field['type'] == 'heading' || !in_array($id, $editable)) continue;
            $values[$id] = self::convert($field, $post[$id] ?? null, $P);
        }
        $merged = array_merge($old, $values);
        foreach ($type['fields'] as $field) {
            if (array_key_exists($field['id'], $values) && !self::visible($field, $merged)) {
                $values[$field['id']] = null;
            }
        }
        return $values;
    }

    private static function convert($field, $raw, Processes $P)
    {
        if (is_string($raw)) $raw = trim($raw);
        switch ($field['type']) {
            case 'string':
            case 'text':
                if (!is_string($raw) || $raw === '') return null;
                $max = $field['max_length'] ?? ($field['type'] == 'text' ? 10000 : 500);
                return mb_substr(str_replace("\r\n", "\n", $raw), 0, $max);
            case 'int':
                return is_numeric($raw) ? intval($raw) : null;
            case 'float':
            case 'money':
                if (!is_string($raw) || $raw === '') return null;
                // accept 1.234,56 and 1234.56
                $n = str_replace(' ', '', $raw);
                if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $n) || preg_match('/^-?\d+,\d+$/', $n)) {
                    $n = str_replace(['.', ','], ['', '.'], $n);
                }
                if (!is_numeric($n)) return null;
                return $field['type'] == 'money' ? round(floatval($n), 2) : floatval($n);
            case 'date':
                if (!is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return null;
                return checkdate((int) substr($raw, 5, 2), (int) substr($raw, 8, 2), (int) substr($raw, 0, 4)) ? $raw : null;
            case 'bool':
                if ($raw === 'true') return true;
                if ($raw === 'false') return false;
                return null;
            case 'check':
                return $raw === 'true';
            case 'select':
                return in_array($raw, array_column($field['options'] ?? [], 'value'), true) ? $raw : null;
            case 'multiselect':
                if (!is_array($raw)) return [];
                $allowed = array_column($field['options'] ?? [], 'value');
                return array_values(array_intersect($allowed, $raw));
            case 'unit':
                return is_string($raw) && isset(self::unitOptions($field, $P)[$raw]) ? $raw : null;
            case 'person':
                if (!is_string($raw) || $raw === '') return null;
                $exists = $P->db->persons->countDocuments(['username' => $raw]) > 0;
                return $exists ? $raw : null;
            case 'contacts':
                if (!is_array($raw)) return [];
                $contacts = [];
                foreach (($raw['name'] ?? []) as $i => $name) {
                    $c = [
                        'name' => mb_substr(trim((string) $name), 0, 200),
                        'email' => mb_substr(trim((string) ($raw['email'][$i] ?? '')), 0, 200),
                        'role' => mb_substr(trim((string) ($raw['role'][$i] ?? '')), 0, 200),
                    ];
                    if ($c['name'] === '' && $c['email'] === '') continue;
                    if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $c['email'] = '';
                    $contacts[] = $c;
                }
                return $contacts;
            case 'activity':
                // id or URL of an activity
                if (!is_string($raw) || !preg_match('/([0-9a-f]{24})/', $raw, $m)) return null;
                return $P->db->activities->countDocuments(['_id' => DB::to_ObjectID($m[1])]) > 0 ? $m[1] : null;
        }
        return null;
    }

    public static function unitOptions($field, Processes $P): array
    {
        $groups = $P->groups();
        $allowed = $field['units'] ?? null;
        $options = [];
        $add = function ($parent, $depth) use (&$add, &$options, $groups, $allowed, $P) {
            $children = array_filter($groups, function ($g) use ($parent) {
                return ($g['parent'] ?? null) == $parent && !($g['inactive'] ?? false);
            });
            uasort($children, function ($a, $b) use ($P) {
                return strcmp($P->groupName($a['id']), $P->groupName($b['id']));
            });
            foreach ($children as $g) {
                if ($depth > 0 && (empty($allowed) || in_array($g['id'], $allowed))) {
                    $options[$g['id']] = str_repeat('– ', $depth - 1) . $P->groupName($g['id']);
                }
                $add($g['id'], $depth + 1);
            }
        };
        $add(null, 0);
        return $options;
    }

    private static function personOptions(Processes $P): array
    {
        static $options = null;
        if ($options === null) {
            $options = [];
            $cursor = $P->db->persons->find(
                ['is_active' => ['$ne' => false], 'username' => ['$exists' => true]],
                ['projection' => ['username' => 1, 'first' => 1, 'last' => 1], 'sort' => ['last' => 1, 'first' => 1]]
            );
            foreach ($cursor as $p) {
                $options[$p['username']] = trim(($p['last'] ?? '') . ', ' . ($p['first'] ?? ''), ', ');
            }
        }
        return $options;
    }

    /* ------------------------------------
       RENDERING
    ------------------------------------ */

    /**
     * Render all fields of a type. Fields not in $editable are shown read-only.
     */
    public static function render($type, array $values, array $editable, Processes $P, bool $isNew = false)
    {
        echo '<div class="row row-eq-spacing proc-form">';
        foreach ($type['fields'] as $field) {
            $value = $values[$field['id']] ?? null;
            $canEdit = in_array($field['id'], $editable);
            // defaults prefill new cases and fields that are filled in later (e.g. by a step)
            if ($value === null && $canEdit && array_key_exists('default', $field) && ($isNew || !empty($field['only_steps']))) {
                $value = $field['default'];
            }
            self::renderField($field, $value, $canEdit, $P);
        }
        echo '</div>';
    }

    private static function renderField($field, $value, bool $editable, Processes $P)
    {
        $id = $field['id'];
        $label = e(Processes::t($field));
        $help = Processes::t($field, 'help');
        $showIf = !empty($field['show_if']) ? " data-show-if='" . e(json_encode($field['show_if'])) . "'" : '';
        $width = intval($field['width'] ?? 12);
        if ($width < 1 || $width > 12) $width = 12;

        if ($field['type'] == 'heading') {
            echo '<div class="col-12 proc-field" data-field="' . $id . '"' . $showIf . '>';
            echo '<h4 class="mt-20 mb-5">' . $label . '</h4>';
            if ($help) echo '<p class="text-muted mt-0">' . e($help) . '</p>';
            echo '</div>';
            return;
        }

        $name = 'values[' . $id . ']';
        $required = !empty($field['required']);
        // read-only values are needed by the conditions of other fields
        $data = $editable ? '' : " data-value='" . e(json_encode($value)) . "'";
        echo '<div class="col-sm-' . $width . ' form-group proc-field" data-field="' . $id . '"' . $showIf . $data . '>';
        echo '<label for="f-' . $id . '" class="' . ($required ? 'required' : '') . '">' . $label . '</label>';

        if (!$editable) {
            echo '<div class="proc-readonly">' . self::displayValue($field, $value, $P) . '</div>';
            if ($help) echo '<small class="text-muted d-block">' . e($help) . '</small>';
            echo '</div>';
            return;
        }

        $req = $required ? ' data-required="1"' : '';
        switch ($field['type']) {
            case 'string':
                echo '<input type="text" class="form-control" id="f-' . $id . '" name="' . $name . '" value="' . e($value) . '"' . $req . '>';
                break;
            case 'text':
                echo '<textarea class="form-control" rows="' . intval($field['rows'] ?? 4) . '" id="f-' . $id . '" name="' . $name . '"' . $req . '>' . e($value) . '</textarea>';
                break;
            case 'int':
                echo '<input type="number" step="1" class="form-control" id="f-' . $id . '" name="' . $name . '" value="' . e($value) . '"' . $req . '>';
                break;
            case 'float':
                echo '<input type="text" inputmode="decimal" class="form-control" id="f-' . $id . '" name="' . $name . '" value="' . e($value === null ? '' : str_replace('.', ',', (string) $value)) . '"' . $req . '>';
                break;
            case 'money':
                echo '<div class="input-group"><input type="text" inputmode="decimal" class="form-control" id="f-' . $id . '" name="' . $name . '" value="' . e($value === null ? '' : number_format((float) $value, 2, ',', '.')) . '"' . $req . '>';
                echo '<div class="input-group-append"><span class="input-group-text">€</span></div></div>';
                break;
            case 'date':
                echo '<input type="date" class="form-control" id="f-' . $id . '" name="' . $name . '" value="' . e($value) . '"' . $req . '>';
                break;
            case 'bool':
                echo '<div>';
                foreach (['true' => lang('Yes', 'Ja'), 'false' => lang('No', 'Nein')] as $v => $l) {
                    $checked = ($value === true && $v == 'true') || ($value === false && $v == 'false');
                    echo '<div class="custom-radio d-inline-block mr-20">';
                    echo '<input type="radio" id="f-' . $id . '-' . $v . '" name="' . $name . '" value="' . $v . '"' . ($checked ? ' checked' : '') . $req . '>';
                    echo '<label for="f-' . $id . '-' . $v . '">' . $l . '</label></div>';
                }
                echo '</div>';
                break;
            case 'check':
                echo '<input type="hidden" name="' . $name . '" value="false">';
                echo '<div class="custom-checkbox"><input type="checkbox" id="f-' . $id . '" name="' . $name . '" value="true"' . ($value === true ? ' checked' : '') . '>';
                echo '<label for="f-' . $id . '">' . lang('Yes', 'Ja') . '</label></div>';
                break;
            case 'select':
            case 'unit':
            case 'person':
                if ($field['type'] == 'select') {
                    $options = [];
                    foreach ($field['options'] ?? [] as $o) $options[$o['value']] = Processes::t($o);
                } elseif ($field['type'] == 'unit') {
                    $options = self::unitOptions($field, $P);
                } else {
                    $options = self::personOptions($P);
                }
                echo '<select class="form-control" id="f-' . $id . '" name="' . $name . '"' . $req . '>';
                echo '<option value="">' . lang('– please select –', '– bitte auswählen –') . '</option>';
                foreach ($options as $v => $l) {
                    echo '<option value="' . e($v) . '"' . ((string) $value === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>';
                }
                echo '</select>';
                break;
            case 'contacts':
                $rows = is_array($value) ? $value : [];
                $rows[] = ['name' => '', 'email' => '', 'role' => ''];
                echo '<table class="table small proc-contacts" id="f-' . $id . '"><thead><tr><th>Name</th><th>' . lang('E-mail', 'E-Mail') . '</th><th>' . lang('Function', 'Funktion') . '</th></tr></thead><tbody>';
                foreach ($rows as $c) {
                    $c = DB::doc2Arr($c);
                    echo '<tr><td><input type="text" class="form-control" name="' . $name . '[name][]" value="' . e($c['name'] ?? '') . '"></td>';
                    echo '<td><input type="email" class="form-control" name="' . $name . '[email][]" value="' . e($c['email'] ?? '') . '"></td>';
                    echo '<td><input type="text" class="form-control" name="' . $name . '[role][]" value="' . e($c['role'] ?? '') . '" placeholder="' . lang('e.g. author', 'z. B. Autor*in') . '"></td></tr>';
                }
                echo '</tbody></table>';
                echo '<button type="button" class="btn small" onclick="var t=document.querySelector(\'#f-' . $id . ' tbody\');var r=t.rows[t.rows.length-1].cloneNode(true);r.querySelectorAll(\'input\').forEach(function(i){i.value=\'\'});t.appendChild(r)"><i class="ph ph-plus"></i> ' . lang('Add contact', 'Kontakt hinzufügen') . '</button>';
                break;
            case 'activity':
                echo '<input type="text" class="form-control" id="f-' . $id . '" name="' . $name . '" value="' . e($value ? ROOTPATH . '/activities/view/' . $value : '') . '" placeholder="' . lang('Link or ID of the activity in OSIRIS', 'Link oder ID der Aktivität in OSIRIS') . '">';
                break;
            case 'multiselect':
                $value = is_array($value) ? $value : [];
                echo '<input type="hidden" name="' . $name . '" value="">';
                foreach ($field['options'] ?? [] as $i => $o) {
                    echo '<div class="custom-checkbox"><input type="checkbox" id="f-' . $id . '-' . $i . '" name="' . $name . '[]" value="' . e($o['value']) . '"' . (in_array($o['value'], $value) ? ' checked' : '') . '>';
                    echo '<label for="f-' . $id . '-' . $i . '">' . e(Processes::t($o)) . '</label></div>';
                }
                break;
        }
        if ($help) echo '<small class="text-muted d-block">' . e($help) . '</small>';
        echo '</div>';
    }

    /* ------------------------------------
       DISPLAY
    ------------------------------------ */

    /** Value as plain text (for titles). */
    public static function plainValue($field, $value, Processes $P): string
    {
        if ($field === null || self::isEmpty($value)) return '';
        switch ($field['type']) {
            case 'bool':
            case 'check':
                return $value ? lang('Yes', 'Ja') : lang('No', 'Nein');
            case 'select':
                foreach ($field['options'] ?? [] as $o) {
                    if ($o['value'] === $value) return Processes::t($o);
                }
                return (string) $value;
            case 'multiselect':
                $labels = [];
                foreach ($field['options'] ?? [] as $o) {
                    if (in_array($o['value'], (array) $value)) $labels[] = Processes::t($o);
                }
                return implode(', ', $labels);
            case 'unit':
                return $P->groupName($value);
            case 'person':
                return $P->personName($value);
            case 'contacts':
                return implode(', ', array_filter(array_map(function ($c) {
                    return DB::doc2Arr($c)['name'] ?? '';
                }, (array) $value)));
            case 'activity':
                $a = $P->db->activities->findOne(['_id' => DB::to_ObjectID($value)], ['projection' => ['title' => 1]]);
                return strip_tags($a['title'] ?? $value);
            case 'money':
                return number_format((float) $value, 2, ',', '.') . ' €';
            case 'float':
                return str_replace('.', ',', (string) $value);
            case 'date':
                return date('d.m.Y', strtotime($value));
        }
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    /** Value as escaped HTML. */
    public static function displayValue($field, $value, Processes $P): string
    {
        if (self::isEmpty($value)) return '<span class="text-muted">–</span>';
        if ($field['type'] == 'text') return nl2br(e($value));
        if ($field['type'] == 'person') {
            return '<a href="' . ROOTPATH . '/profile/' . e($value) . '">' . e($P->personName($value)) . '</a>';
        }
        if ($field['type'] == 'activity') {
            return '<a href="' . ROOTPATH . '/activities/view/' . e($value) . '"><i class="ph ph-link"></i> ' . e(self::plainValue($field, $value, $P)) . '</a>';
        }
        if ($field['type'] == 'contacts') {
            $out = [];
            foreach ((array) $value as $c) {
                $c = DB::doc2Arr($c);
                $line = e($c['name'] ?? '');
                if (!empty($c['role'])) $line .= ' <small class="text-muted">(' . e($c['role']) . ')</small>';
                if (!empty($c['email'])) $line .= ' <a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>';
                $out[] = $line;
            }
            return implode('<br>', $out);
        }
        return e(self::plainValue($field, $value, $P));
    }

    /**
     * Read-only display of all filled and visible fields, grouped by headings.
     */
    public static function display($type, array $values, Processes $P)
    {
        $sections = [];
        $current = ['heading' => null, 'rows' => []];
        foreach ($type['fields'] as $field) {
            if (!self::visible($field, $values)) continue;
            if ($field['type'] == 'heading') {
                $sections[] = $current;
                $current = ['heading' => $field, 'rows' => []];
                continue;
            }
            $current['rows'][] = $field;
        }
        $sections[] = $current;
        foreach ($sections as $section) {
            if (empty($section['rows'])) continue;
            if ($section['heading']) echo '<h4 class="mt-20 mb-10">' . e(Processes::t($section['heading'])) . '</h4>';
            echo '<table class="table small proc-values"><tbody>';
            foreach ($section['rows'] as $field) {
                echo '<tr><th style="width:40%">' . e(Processes::t($field)) . '</th><td>' . self::displayValue($field, $values[$field['id']] ?? null, $P) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }

    /** Script that shows and hides fields by their show_if condition. */
    public static function script()
    {
?>
        <script>
            (function() {
                function value(field) {
                    var box = document.querySelector('.proc-field[data-field="' + field + '"]');
                    if (!box) return null;
                    var inputs = box.querySelectorAll('input:not([type=hidden]), select, textarea');
                    if (!inputs.length) {
                        var ro = box.getAttribute('data-value');
                        return ro === null ? null : JSON.parse(ro);
                    }
                    var first = inputs[0];
                    if (first.type == 'radio') {
                        var c = box.querySelector('input:checked');
                        return c ? c.value : null;
                    }
                    if (first.type == 'checkbox') {
                        var all = box.querySelectorAll('input[type=checkbox]');
                        if (all.length == 1 && !first.name.endsWith('[]')) return first.checked ? 'true' : 'false';
                        return Array.from(box.querySelectorAll('input[type=checkbox]:checked')).map(function(i) {
                            return i.value;
                        });
                    }
                    return first.value === '' ? null : first.value;
                }

                function str(v) {
                    if (v === true) return 'true';
                    if (v === false) return 'false';
                    return v === null || v === undefined ? '' : String(v);
                }

                function num(v) {
                    return parseFloat(str(v).replace(/\./g, '').replace(',', '.'));
                }

                function check(c) {
                    if (!c) return true;
                    if (c.all) return c.all.every(check);
                    if (c.any) return c.any.some(check);
                    var v = value(c.field);
                    var empty = v === null || v === '' || (Array.isArray(v) && !v.length);
                    var t = c.value;
                    switch (c.op || 'eq') {
                        case 'eq':
                            return Array.isArray(v) ? v.indexOf(str(t)) >= 0 : str(v) === str(t);
                        case 'ne':
                            return Array.isArray(v) ? v.indexOf(str(t)) < 0 : str(v) !== str(t);
                        case 'in':
                            return [].concat(t).map(str).indexOf(str(v)) >= 0;
                        case 'not_in':
                            return [].concat(t).map(str).indexOf(str(v)) < 0;
                        case 'gt':
                            return !empty && num(v) > num(t);
                        case 'gte':
                            return !empty && num(v) >= num(t);
                        case 'lt':
                            return !empty && num(v) < num(t);
                        case 'lte':
                            return !empty && num(v) <= num(t);
                        case 'filled':
                            return !empty;
                        case 'empty':
                            return empty;
                    }
                    return false;
                }

                function update() {
                    document.querySelectorAll('.proc-field[data-show-if]').forEach(function(box) {
                        var show = check(JSON.parse(box.getAttribute('data-show-if')));
                        box.style.display = show ? '' : 'none';
                        box.querySelectorAll('input, select, textarea').forEach(function(i) {
                            i.disabled = !show;
                        });
                    });
                }
                document.addEventListener('change', function(e) {
                    if (e.target.closest('.proc-form')) update();
                });
                document.addEventListener('DOMContentLoaded', update);
                update();
            })();
        </script>
<?php
    }
}
