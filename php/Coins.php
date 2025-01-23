<?php
include_once 'DB.php';

class Coins
{
    public $matrix = array();
    private $db = null;

    function __construct()
    {
        $db = new DB;
        $this->db = $db->db;

        $types = $this->db->adminTypes->find()->toArray();
        foreach ($types as $typeArr) {
            $type = $typeArr['id'];
            // Prüfen, ob "coins" existiert, und Standardwert setzen
            $typeArr['coins'] = isset($typeArr['coins']) ? floatval($typeArr['coins']) : 0;
            $this->matrix[$type] = $typeArr['coins'];
        }
    }

    function activityCoins($doc, $user)
    {
        if (isset($doc['epub']) && $doc['epub']) return [
            'coins' => 0, 'comment' => 'Online ahead of print'
        ];

        $subtype = $doc['subtype'];

        // Prüfung auf Subtype in der Matrix
        if (!isset($this->matrix[$subtype])) {
            return [
                'coins' => 0,
                'comment' => "Subtype '$subtype' not found in matrix"
            ];
        }

        $coins = $this->matrix[$subtype];

        $authors = DB::doc2Arr($doc['authors']);
        $author = array_filter($authors, function ($author) use ($user) {
            return $author['user'] == $user;
        });

        if (empty($author)) return [
            'coins' => 0, 'comment' => 'User not author'
        ];

        $author = reset($author);
        $position = ($author['position'] ?? '');

        if (!($author['aoi'] ?? false)) return [
            'coins' => 0, 'comment' => 'User not affiliated'
        ];

        if (is_numeric($coins)) {
            $comment = "$coins for $subtype";
            if ($position == 'middle') {
                $coins /= 2;
                $comment .= " (middle author)";
            }
            return [
                'coins' => $coins, 'comment' => $comment
            ];
        }

        if (preg_match('/(\d+)(?:\s*)([\+\-\*\/])(?:\s*)\{(if|sws)\}/', $coins, $matches) > 0) {
            $val = 0;
            $coins = $matches[1];
            $operator = $matches[2];
            $thingy = strtoupper($matches[3]);

            if ($thingy == 'IF') {
                $val = max($doc['impact'] ?? 0, 1);
            } elseif ($thingy == 'SWS') {
                $val = $author['sws'] ?? 0;
            }

            if (empty($val)) return [
                'coins' => 0, 'comment' => 'Undefined value'
            ];

            if ($position == 'middle') $coins /= 2;
            $c = ($coins) * intval($val);
            return [
                'coins' => $c, 'comment' => "$coins &times; $val ($thingy)"
            ];
        }

        return [
            'coins' => 0, 'comment' => 'Undefined coins'
        ];
    }

    function getCoins($user, $year = null)
    {
        $total = 0;

        foreach ($this->matrix as $subtype => $coins) {
            $filter = [
                'subtype' => $subtype,
                'authors' => ['$elemMatch' => ['user' => $user, 'aoi' => ['$in' => [true, 1, '1']]]],
                'epub' => ['$ne' => true]
            ];

            if ($year !== null) {
                $filter['$or'] = [
                    [
                        "start.year" => ['$lte' => $year],
                        '$or' => [
                            ['end.year' => ['$gte' => $year]],
                            [
                                'end' => null,
                                '$or' => [
                                    ['type' => 'misc', 'subtype' => 'misc-annual'],
                                    ['type' => 'review', 'subtype' => 'editorial'],
                                ]
                            ]
                        ]
                    ],
                    ['year' => $year]
                ];
            }

            if (is_numeric($coins)) {
                try {
                    $N = $this->db->activities->count($filter);
                    if ($N == 0) continue;

                    $middle_filter = $filter;
                    $middle_filter['authors']['$elemMatch']['position'] = 'middle';
                    $middle = $this->db->activities->count($middle_filter);

                    $total += (($N - $middle) * $coins) + ($middle * $coins / 2);
                } catch (Exception $e) {
                    error_log("Database error: " . $e->getMessage());
                    continue;
                }
            } elseif (preg_match('/(\d+)(?:\s*)([\+\-\*\/])(?:\s*)\{(if|sws)\}/', $coins, $matches) > 0) {
                $docs = $this->aggregateDocs($filter, $user, strtolower($matches[3]));

                foreach ($docs as $val) {
                    $c = ($matches[1]) * $val['sum'];
                    if ($val['_id'] == 'middle') $c /= 2;
                    $total += $c;
                }
            }
        }

        return round($total);
    }

    private function aggregateDocs($filter, $user, $field)
    {
        $projectField = $field === 'if' ? 'impact' : 'sws';

        return $this->db->activities->aggregate([
            ['$match' => $filter],
            ['$project' => [$projectField => 1, 'authors' => 1]],
            ['$unwind' => '$authors'],
            ['$match' => ['authors.user' => $user]],
            [
                '$group' => [
                    '_id' => ['$toLower' => '$authors.position'],
                    'sum' => ['$sum' => ['$convert' => [
                        'input' => "$authors.$projectField",
                        'to' => 'int',
                        'onError' => 0
                    ]]]
                ]
            ]
        ]);
    }
}
