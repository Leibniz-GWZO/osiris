<?php

/**
 * Conditions on form values, used for fields (show_if), steps (condition)
 * and hints. The same rules are evaluated in the browser (see form.php).
 *
 * {"field": "x", "op": "eq", "value": "y"}
 * {"all": [ ... ]}, {"any": [ ... ]}
 * ops: eq, ne, in, not_in, gt, gte, lt, lte, filled, empty
 */
class ProcessConditions
{
    public static function check($cond, $values): bool
    {
        if (empty($cond)) return true;
        if (isset($cond['all'])) {
            foreach ($cond['all'] as $c) {
                if (!self::check($c, $values)) return false;
            }
            return true;
        }
        if (isset($cond['any'])) {
            foreach ($cond['any'] as $c) {
                if (self::check($c, $values)) return true;
            }
            return false;
        }
        $v = $values[$cond['field'] ?? ''] ?? null;
        $target = $cond['value'] ?? null;
        $isEmpty = $v === null || $v === '' || $v === [];
        switch ($cond['op'] ?? 'eq') {
            case 'eq':
                return self::same($v, $target);
            case 'ne':
                return !self::same($v, $target);
            case 'in':
                return in_array(self::str($v), array_map([self::class, 'str'], (array) $target), true);
            case 'not_in':
                return !in_array(self::str($v), array_map([self::class, 'str'], (array) $target), true);
            case 'gt':
                return !$isEmpty && floatval($v) > floatval($target);
            case 'gte':
                return !$isEmpty && floatval($v) >= floatval($target);
            case 'lt':
                return !$isEmpty && floatval($v) < floatval($target);
            case 'lte':
                return !$isEmpty && floatval($v) <= floatval($target);
            case 'filled':
                return !$isEmpty;
            case 'empty':
                return $isEmpty;
        }
        return false;
    }

    private static function same($a, $b): bool
    {
        if (is_array($a)) return in_array(self::str($b), array_map([self::class, 'str'], $a), true);
        return self::str($a) === self::str($b);
    }

    public static function str($v): string
    {
        if ($v === true) return 'true';
        if ($v === false) return 'false';
        return (string) ($v ?? '');
    }
}
