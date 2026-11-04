<?php

/** Target and actual counts per year and category. */
class ProcessTargets
{
    public static function config(Processes $P, $areaId): ?array
    {
        return $P->area($areaId)['targets'] ?? null;
    }

    public static function canEdit(Processes $P, $areaId): bool
    {
        $c = self::config($P, $areaId);
        return $c !== null && $P->inAudience($c['edit'] ?? [], $P->user);
    }

    public static function canView(Processes $P, $areaId): bool
    {
        $c = self::config($P, $areaId);
        return $c !== null && (self::canEdit($P, $areaId) || $P->inAudience($c['view'] ?? [], $P->user));
    }

    /** Targets of a year: category => int. */
    public static function targets(Processes $P, $areaId, int $year): array
    {
        $doc = $P->db->proc_targets->findOne(['_id' => "$areaId-$year"]);
        return array_map('intval', DB::doc2Arr($doc['values'] ?? []));
    }

    /** Last change of the targets of a year: [by, at] or null. */
    public static function changed(Processes $P, $areaId, int $year): ?array
    {
        $doc = $P->db->proc_targets->findOne(['_id' => "$areaId-$year"]);
        return $doc ? [$doc['updated_by'] ?? null, $doc['updated_at'] ?? null] : null;
    }

    /** Save the targets of a year; empty values remove the target. */
    public static function save(Processes $P, $areaId, int $year, array $input)
    {
        $values = [];
        foreach ($P->publicationTypes() as $id => $t) {
            $v = trim((string) ($input[$id] ?? ''));
            if ($v === '') continue;
            if (!ctype_digit($v) || (int) $v > 10000) return lang('Please enter whole numbers.', 'Bitte ganze Zahlen eintragen.');
            $values[$id] = (int) $v;
        }
        $P->db->proc_targets->updateOne(
            ['_id' => "$areaId-$year"],
            ['$set' => ['area' => $areaId, 'year' => $year, 'values' => (object) $values, 'updated_by' => $P->user, 'updated_at' => date('Y-m-d H:i:s')]],
            ['upsert' => true]
        );
        return null;
    }

    /** Activities per category. */
    public static function published(Processes $P, int $year, $unit = null): array
    {
        $match = [
            'type' => 'publication',
            'year' => $year,
            'affiliated' => ['$ne' => false],
            'hide' => ['$ne' => true],
            'exclude_from_reports' => ['$ne' => true],
        ];
        if ($unit) $match['units'] = $unit;
        $result = [];
        foreach ($P->db->activities->aggregate([['$match' => $match], ['$group' => ['_id' => '$subtype', 'n' => ['$sum' => 1]]]]) as $row) {
            $result[(string) $row['_id']] = (int) $row['n'];
        }
        return $result;
    }

    /** @return array ['planned' => [cat => [case, ...]], 'unlinked' => [case, ...]] */
    public static function planned(Processes $P, array $cases, int $year, $unit = null): array
    {
        $planned = [];
        $unlinked = [];
        foreach ($cases as $c) {
            $type = $P->type($c['type']);
            if (!ProcessPhases::isPhases($type) || ProcessPhases::year($c, $type) !== $year) continue;
            if ($unit && !in_array($unit, $P->unitChain($c['unit'] ?? null))) continue;
            $linked = !empty($type['activity_field']) && !empty(DB::doc2Arr($c['values'] ?? [])[$type['activity_field']] ?? null);
            if ($linked) continue;
            if (($c['status'] ?? '') == 'published') {
                $unlinked[] = $c;
                continue;
            }
            if (!in_array($c['status'] ?? '', ProcessPhases::OPEN)) continue;
            $planned[ProcessPhases::category($c, $type) ?? ''][] = $c;
        }
        return ['planned' => $planned, 'unlinked' => $unlinked];
    }
}
