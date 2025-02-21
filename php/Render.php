<?php
include_once 'init.php';
function renderActivities($filter = [])
{
    global $Groups;
    $Format = new Document(true);
    $DB = new DB;
    $cursor = $DB->db->activities->find($filter);
    $rendered = [
        'print' => '',
        'web' => '',
        'icon' => '',
        'type' => '',
    ];
    foreach ($cursor as $doc) {
        $id = $doc['_id'];
        $Format->setDocument($doc);
        $Format->usecase = 'web';
        $doc['authors'] = DB::doc2Arr($doc['authors']);

        // $depts = $Groups->getDeptFromAuthors($doc['authors']);

        $f = $Format->format();
        $web = $Format->formatShort();

        $Format->usecase = 'portal';
        $portfolio = $Format->formatPortfolio();

        $rendered = [
            'print' => $f,
            'plain' => strip_tags($f),
            'portfolio' => $portfolio,
            'web' => $web,
            'icon' => trim($Format->activity_icon()),
            'type' => $Format->activity_type(),
            'subtype' => $Format->activity_subtype(),
            'title' => $Format->getTitle(),
            'authors' => $Format->getAuthors('authors'),
        ];
        $values = ['rendered' => $rendered];

        $values['start_date'] = valueFromDateArray($doc['start'] ?? $doc);
        if (array_key_exists('end', DB::doc2Arr($doc)) && is_null($doc['end'])) {
            $end = null;
        } else {
            $end = valueFromDateArray($doc['end'] ?? $doc['start'] ?? $doc);
        }
        $values['end_date'] = $end;

        if ($doc['type'] == 'publication' && isset($doc['journal'])) {
            // update impact if necessary
            $if = $DB->get_impact($doc);
            if (!empty($if)) {
                $values['impact'] = $if;
            }
            $values['metrics'] = $DB->get_metrics($doc);
            $values['quartile'] = $values['metrics']['quartile'] ?? null;
        }
        $aoi_authors = array_filter($doc['authors'], function ($a) {
            return $a['aoi'] ?? false;
        });
        $values['affiliated'] = !empty($aoi_authors);
        $values['affiliated_positions'] = $Format->getAffiliationTypes();
        $values['cooperative'] = $Format->getCooperationType($values['affiliated_positions'], $doc['units'] ?? []);
        $DB->db->activities->updateOne(
            ['_id' => $id],
            ['$set' => $values]
        );
    }
    // return last element in case that only one id has been rendered
    return $rendered;
}

function renderAuthorUnits($doc, $old_doc = [])
{
    global $Groups;
    if (!isset($doc['authors'])) return $doc;

    $DB = new DB;
    $osiris = $DB->db;

    $units = [];
    $startdate = strtotime($doc['start_date']);

    $authors = $doc['authors'] ?? [];
    $old = $old_doc['authors'] ?? [];

    // check if old authors are equal to new authors
    if (count($authors) == count($old) && $authors == $old) {
        return $doc;
    }

    // add user as key to authors
    $old = array_column($old, 'units', 'user');

    foreach ($authors as $i => $author) {
        if (!($author['aoi'] ?? false) || !isset($author['user'])) continue;
        $user = $author['user'];

        // check if author has been manually set, if so, do not update units
        $old_author = $old[$user] ?? [];
        if (!empty($old_author) && $old_author['manually']) {
            $authors[$i]['units'] = $old_author['units'] ?? [];
            $units = array_merge($units, $authors[$i]['units']);
            continue;
        }
        $person = $DB->getPerson($user);
        if (isset($person['units']) && !empty($person['units'])) {
            $u = DB::doc2Arr($person['units']);
            // filter units that have been active at the time of activity
            $u = array_filter($u, function ($unit) use ($startdate) {
                if (!$unit['scientific']) return false; // we are only interested in scientific units
                if (empty($unit['start'])) return true; // we have basically no idea when this unit was active
                return strtotime($unit['start']) <= $startdate && (empty($unit['end']) || strtotime($unit['end']) >= $startdate);
            });
            $u = array_column($u, 'unit');
            $authors[$i]['units'] = $u;
            $units = array_merge($units, $u);
        }
    }
    $units = array_unique($units);
    foreach ($units as $unit) {
        $units = array_merge($units, $Groups->getParents($unit, true));
    }
    $units = array_unique($units);
    $doc['units'] = array_values($units);
    $doc['authors'] = $authors;
    return $doc;
}


function renderAuthorUnitsMany($filter = []){
    $DB = new DB;
    $cursor = $DB->db->activities->find($filter, ['projection' => ['authors' => 1, 'units' => 1, 'start_date' => 1]]);
    foreach ($cursor as $doc) {
        $doc = renderAuthorUnits($doc);
        $DB->db->activities->updateOne(
            ['_id' => $doc['_id']],
            ['$set' => $doc]
        );
    }
}