<?php
Route::get('/export/?', function () {
    include_once BASEPATH . "/php/_config.php";
    $breadcrumb = [
        ['name' => lang("Export")]
    ];

    include BASEPATH . "/header.php";
    echo "TODO";
    include BASEPATH . "/footer.php";
}, 'login');

Route::get('/export/publications', function () {
    include_once BASEPATH . "/php/_config.php";
    $breadcrumb = [
        ['name' => 'Export', 'path' => "/export"],
        ['name' => lang("Publications", "Publikationen")]
    ];

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/export/publications.php";
    include BASEPATH . "/footer.php";
}, 'login');


Route::get('/export/reports', function () {
    include_once BASEPATH . "/php/_config.php";
    $breadcrumb = [
        ['name' => 'Export', 'path' => "/export"],
        ['name' => lang("Reports", "Berichte")]
    ];

    include BASEPATH . "/header.php";
    include BASEPATH . "/pages/export/reports.php";
    include BASEPATH . "/footer.php";
}, 'login');


function getTypeTitle($type)
{
    $types = [
        'teaching' => 'Lehre und Gäste',
        'poster' => 'Poster',
        'lecture' => 'Vorträge',
        'publication' => 'Publikationen',
        'misc' => 'Weitere Aktivitäten',
        'review' => 'Reviews &amp; Editorial Boards'
    ];
    return $types[$type] ?? '';
}


Route::post('/export/publications', function () {
    require_once BASEPATH . '/php/_db.php';
    require_once BASEPATH . '/php/format.php';
    $Format = new Format($_SESSION['username'], 'word');

    $order = array(
        'publication',
        'lecture',
        'poster',
        'review',
        'misc',
        'teaching',
    );
    // select data
    $collection = $osiris->activities;
    $options = ['sort' => ["type" => 1, "year" => 1, "month" => 1]];

    $filter = [];
    if (isset($_POST['filter']['type']) && !empty($_POST['filter']['type'])) {
        $filter['type'] = trim($_POST['filter']['type']);
    }
    if (isset($_POST['filter']['user']) && !empty($_POST['filter']['user'])) {
        $filter['$or'] = [['authors.user' => $_POST['filter']['user']], ['editors.user' => $_POST['filter']['user']]];
    }
    if (isset($_POST['filter']['year']) && !empty($_POST['filter']['year'])) {
        $filter['year'] = intval($_POST['filter']['year']);
    }
    $cursor = $collection->find($filter, $options);

    $headers = [];

    if ($_POST['format'] == "word") {
        // Creating the new document...
        \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $phpWord->addTitleStyle(1, ["bold" => true, "size" => 16], ["spaceBefore" => 8]);
        // $phpWord->setOutputEscapingEnabled(true);
        /* Note: any element you append to a document must reside inside of a Section. */

        // Adding an empty Section to the document...
        $section = $phpWord->addSection();

        // sort the elements
        $cursor = $cursor->toArray();
        usort($cursor, function ($a, $b) use ($order) {
            $pos_a = array_search($a['type'], $order);
            $pos_b = array_search($b['type'], $order);
            return $pos_a - $pos_b;
        });

        foreach ($cursor as $doc) {
            if (!in_array($doc['type'], $headers)) {
                $headers[] = $doc['type'];
                $section->addTitle(getTypeTitle($doc['type']), 1);
            }
            $paragraph = $section->addTextRun();
            $line = $Format->format($doc['type'], $doc);
            \PhpOffice\PhpWord\Shared\Html::addHtml($paragraph, $line);
        }

        // Download file
        $file = 'Publications.docx';
        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        $xmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $xmlWriter->save("php://output");

        // Saving the document as OOXML file...
        // $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        // $objWriter->save('helloWorld.docx');
        // // Saving the document as HTML file...
        // $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        // $objWriter->save('helloWorld.html');

    } else if ($_POST['format'] == "bibtex") {
        header("Content-Type: text/plain; charset=utf-8");

        $bibentries = [
            'journal-article' => "article",
            'article' => "article",
            'Journal Article' => "article",
            'book' => "book",
            'chapter' => "inbook",
            "misc" => "misc"
        ];
        foreach ($cursor as $doc) {

            echo '@' . ($bibentries[trim($doc['pubtype'] ?? 'misc')] ?? 'misc') . '{' . $doc['_id'] . ',' . PHP_EOL;

            if (isset($doc['title']) and ($doc['title'] != '')) echo '  Title = {' . $doc['title'] . '},' . PHP_EOL;
            if (isset($doc['authors']) and ($doc['authors'] != '')) echo '  Author = {' . formatAuthors($doc['authors'], ', ') . '},' . PHP_EOL;
            if (isset($doc['editor']) and ($doc['editor'] != '')) echo '  Editor = {' . $doc['editor'] . '},' . PHP_EOL;
            if (isset($doc['journal']) and ($doc['journal'] != '')) echo '  Journal = {' . $doc['journal'] . '},' . PHP_EOL;
            if (isset($doc['year']) and ($doc['year'] != '')) echo '  Year = {' . $doc['year'] . '},' . PHP_EOL;
            if (isset($doc['number']) and ($doc['number'] != '')) echo '  Number = {' . $doc['number'] . '},' . PHP_EOL;
            if (isset($doc['pages']) and ($doc['pages'] != '')) echo '  Pages = {' . $doc['pages'] . '},' . PHP_EOL;
            if (isset($doc['volume']) and ($doc['volume'] != '')) echo '  Volume = {' . $doc['volume'] . '},' . PHP_EOL;
            if (isset($doc['doi']) and ($doc['doi'] != '')) echo '  Doi = {' . $doc['doi'] . '},' . PHP_EOL;
            if (isset($doc['isbn']) and ($doc['isbn'] != '')) echo '  Isbn = {' . $doc['isbn'] . '},' . PHP_EOL;
            if (isset($doc['publisher']) and ($doc['publisher'] != '')) echo '  Publisher = {' . $doc['publisher'] . '},' . PHP_EOL;
            if (isset($doc['book']) and ($doc['book'] != '')) echo '  Booktitle = {' . $doc['book'] . '},' . PHP_EOL;
            if (isset($doc['chapter']) and ($doc['chapter'] != '')) echo '  Chapter = {' . $doc['chapter'] . '},' . PHP_EOL;
            if (isset($doc['abstract']) and ($doc['abstract'] != '')) echo '  Abstract = {' . $doc['abstract'] . '},' . PHP_EOL;
            if (isset($doc['keywords']) and ($doc['keywords'] != '')) {
                echo '  Keywords = {';
                foreach ($doc['keywords'] as $keyword) echo $keyword . PHP_EOL;
                echo '},' . PHP_EOL;
            }

            echo '}' . PHP_EOL . PHP_EOL;
        }
    }
}, 'login');



function clean_comment_export($subject, $front_addition_text = '')
{

    // Clean escaped quotes
    $subject = str_replace('\"', '"', $subject);

    // Clean style tags
    $subject = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $subject);

    // Remove multiple br tags
    $subject = preg_replace('#(<br */?>\s*)+#i', '<br />', $subject);

    // Try and catch all the weird spaces...
    $subject = str_replace(array("<br />", "<br/>"), "</p><p>", '<p>' . $subject . '</p>');

    // Sort the spacing
    $subject = preg_replace('#\R+#', "\r\n", preg_replace("/[\r\n]+/", "\r\n", $subject));

    // Remove Tabs 
    $subject = trim(preg_replace('/\t+/', '', $subject));

    // Remove multiple backslashes
    $subject = preg_replace('~\\+~', '', $subject);

    // Remove multiple single quote
    $subject = preg_replace("~'+~", "'", $subject);

    // Remove \' 
    $subject = str_replace("\'", "'", $subject);

    // Turn closing and opening p tags into breaks
    $subject = str_replace("</p><p>", "###", $subject);

    // Remove the double ######
    $subject = str_replace("######", "", $subject);

    // ...and ### ### as these happen apparently.
    $subject = str_replace("### ###", "###", $subject);

    // Remove nbsp
    $subject = str_replace("&nbsp;", ' ', $subject);

    // Remove double spaces 
    $subject = preg_replace('!\s+!', ' ', $subject);

    // Strip all tags, except <b><i><strike>
    $subject = strip_tags($subject, '<b><i><strike>');

    // Remove any ### at the start
    $subject = rtrim(ltrim($subject, "#"), "#");

    // Add in the <p> tags in the text.
    $subject = str_replace("###", "</p><br/><p>", $subject);

    // Sort the breaks before and after formatting tags
    $subject = '<p>' . nl2br($front_addition_text . $subject) . '</p>';

    return $subject;
}

Route::post('/export/reports', function () {
    require_once BASEPATH . '/php/_db.php';
    require_once BASEPATH . '/php/format.php';
    $Format = new Format(false, 'word');

    // prepare user dict with all departments
    $users_cursor = $osiris->users->find([], ['projection' => ['_id' => 1, 'dept' => 1]]);
    $users = array();
    foreach ($users_cursor as $u) {
        $users[$u['_id']] = $u['dept'];
    }

    // select data
    $startyear = intval(explode('-', $_POST['start'], 2)[0]);
    $endyear = intval(explode('-', $_POST['end'], 2)[0]);

    $collection = $osiris->activities;
    $options = ['sort' => ["type" => 1, "year" => 1, "month" => 1]];

    $filter = [
        'year' => ['$gte' => $startyear, '$lte' => $endyear],
    ];
    $cursor = $collection->find($filter, $options);

    $result = [
        'publication' => [],
        'lecture' => [],
        'poster' => [],
        'review' => [],
        'misc' => [],
        'teaching' => []
    ];

    $depts = [
        "BUG" => "Bioeconomy and Health Research",
        "MIOS" => "Microorganisms",
        "Services" => "Services Microorganisms and Patent Deposits",
        "MÖD" => "Microbial Ecology and Diversity Research",
        "BIDB" => "Central Bioinformatics and Databases",
        "MIG" => "Microbial Genome Research (DZIF)",
        "MB" => "Junior Research Group: Microbial Biotechnology",
        "MuTZ" => "Human Cell Biology",
        "PFVI" => "Plant Virology",
        "VI" => "Junior Research Group: VirusInteract",
    ];
    $countTables = [
        "autonom" => [],
        "collab" => [],
        "transfer" => []
    ];

    $start = getDateTime($_POST['start'] . ' 00:00:00');
    $end = getDateTime($_POST['end'] . ' 23:59:59');
    foreach ($cursor as $doc) {
        $ds = getDateTime($doc['start'] ?? $doc);
        if (isset($doc['end']) && !empty($doc['end'])) $de = getDateTime($doc['end'] ?? $doc);
        else $de = $ds;
        if (($ds <= $start && $start <= $de) || ($start <= $ds && $ds <= $end)) {
            // pass
            // dump($doc['_id']);
        } else {
            // dump($doc['_id']);
            continue;
        }

        $doc['file'] = "";
        if ($doc['type'] == 'publication') {
            // get the category of research
            $cat = 'collab';
            // get the departments
            $dept = [];

            foreach ($doc['authors'] as $a) {
                $aoi = $a['aoi'] ?? false;
                if ($aoi) {
                    if ($a['position'] == 'first' || $a['position'] == 'last') {
                        $cat = 'autonom';
                        // break;
                    }
                    if (isset($a['user']) && isset($users[$a['user']])) {
                        $dept[] = $users[$a['user']];
                    }
                }
            }

            $dept = array_count_values($dept);

            if (isset($doc['pubtype']) && in_array($doc['pubtype'], ['chapter', 'article'])) {
                foreach ($dept as $d => $v) {
                    if (array_key_exists($d, $depts)) {
                        if (!array_key_exists($d, $countTables[$cat])) {
                            $countTables[$cat][$d] = ["chapter" => 0, "article" => 0];
                        }
                        $countTables[$cat][$d][$doc['pubtype']] += 1;
                    }
                }
            }

            if (isset($doc['pubtype']) && in_array($doc['pubtype'], ['magazine'])) {
                foreach ($dept as $d => $v) {
                    if (array_key_exists($d, $depts)) {
                        if (!array_key_exists($d, $countTables['transfer'])) {
                            $countTables['transfer'][$d] = ["magazine" => 0, "lecture" => 0, "poster" => 0];
                        }
                        $countTables['transfer'][$d]['magazine'] += 1;
                    }
                }
            }

            $doc['cat'] = $cat;
            $doc['dept'] = $dept;
            if (array_key_exists('MuTZ', $dept)) $D = "MuTZ";
            else if (array_key_exists('PFVI', $dept)) $D = "PFVI";
            else $D = "MIOS";

            $result[$doc['type']][$cat][$D][$doc['pubtype'] ?? 'article'][] = $doc;
        } else if ($doc['type'] == 'review') {
            if ($doc['role'] == "Editorial") {
                $doc['type'] = 'editorial';
            }
            $result[$doc['type']][$doc['journal']][] = getTitleLastname($doc['authors'][0]['user'] ?? '');
        } else {

            $dept = [];

            foreach ($doc['authors'] as $a) {
                $aoi = $a['aoi'] ?? false;
                if ($aoi) {
                    if (isset($a['user']) && isset($users[$a['user']])) {
                        $dept[] = $users[$a['user']];
                    }
                }
            }

            $dept = array_count_values($dept);

            if (isset($doc['type']) && in_array($doc['type'], ['lecture', 'poster'])) {
                foreach ($dept as $d => $v) {
                    if (array_key_exists($d, $depts)) {
                        if (!array_key_exists($d, $countTables['transfer'])) {
                            $countTables['transfer'][$d] = ["magazine" => 0, "lecture" => 0, "poster" => 0];
                        }
                        $countTables['transfer'][$d][$doc['type']] += 1;
                    }
                }
            }


            $result[$doc['type']][] = $doc;
        }
    }

    // dump($countTables, true);
    // dump($result);
    // die;

    // Creating the new document...
    \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);
    \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
    $phpWord = new \PhpOffice\PhpWord\PhpWord();

    $phpWord->setDefaultFontName('Calibri');
    $phpWord->setDefaultFontSize(11);

    $phpWord->addNumberingStyle(
        'hNum',
        array(
            'type' => 'multilevel', 'levels' => array(
                array('pStyle' => 'Heading1', 'format' => 'decimal', 'text' => '%1'),
                array('pStyle' => 'Heading2', 'format' => 'decimal', 'text' => '%1.%2'),
                array('pStyle' => 'Heading3', 'format' => 'decimal', 'text' => '%1.%2.%3'),
            )
        )
    );

    $phpWord->addTitleStyle(1, ["bold" => true, "size" => 16], ["spaceBefore" => 8, 'numStyle' => 'hNum', 'numLevel' => 0]);
    $phpWord->addTitleStyle(2, ["bold" => true, "size" => 14], ["spaceBefore" => 8, 'numStyle' => 'hNum', 'numLevel' => 1]);
    $phpWord->addTitleStyle(3, ["bold" => true, "size" => 14], ["spaceBefore" => 8, 'numStyle' => 'hNum', 'numLevel' => 2]);

    $phpWord->addTableStyle('CountTable', ['borderSize' => 1, 'borderColor' => 'grey', 'cellMargin' => 80]);

    $styleCell = ['valign'=>'center'];
    $styleText = [];
    $styleTextBold = ['bold'=>true];
    $styleParagraph =  ['spaceBefore'=>0,'spaceAfter'=>0];
    $styleParagraphCenter =  ['spaceBefore'=>0,'spaceAfter'=>0,'align' => 'center'];
    // $cellBold = $styleTextBold;
    // $cellNormal = [, 'valign'=>'center']
    // Adding an empty Section to the document...
    $section = $phpWord->addSection();

    $section->addTitle("Foreword", 1);
    $section->addTitle("Science and Services", 1);

    // publications

    foreach ([
        "autonom" => "Autonomous Research",
        "collab" => "Collaborative Research"
    ] as $cat => $title1) {
        $section->addTitle($title1, 1);

        // count tables
        if (!empty($countTables[$cat])) {
            $table = $section->addTable("CountTable");

            $table->addRow();
            $cell = $table->addCell(5000, $styleCell);
            $cell->addText("Department", $styleTextBold, $styleParagraph);
            $cell = $table->addCell(2500, $styleCell);
            $n = array_sum(array_column($countTables[$cat], 'chapter'));
            $cell->addText("Book Chapters (total: $n)", ["bold" => true, ]);
            $cell = $table->addCell(2500, $styleCell);
            $n = array_sum(array_column($countTables[$cat], 'article'));
            $cell->addText("Refereed Publications (total: $n)", ["bold" => true, ]);

            foreach ($depts as $dept_short => $dept_name) {
                $table->addRow();
                // in twip (1mm = 56,6928 twip)
                $cell = $table->addCell(5000, $styleCell);
                $cell->addText($dept_name, $styleTextBold, $styleParagraph);
                $data = $countTables[$cat][$dept_short] ?? array("chapter" => 0, "article" => 0);
                $cell = $table->addCell(2500, $styleCell);
                $cell->addText($data['chapter'] == 0 ? '-' : $data['chapter'], $styleText, $styleParagraphCenter);
                $cell = $table->addCell(2500, $styleCell);
                $cell->addText($data['article'] == 0 ? '-' : $data['article'], $styleText, $styleParagraphCenter);
            }
        }

        $section->addTextRun();

        foreach ([
            "MIOS" => "Microbiology",
            "MuTZ" => "Human Cell Biology",
            "PFVI" => "Plant Virology"
        ] as $D => $title2) {

            if (isset($result['publication'][$cat][$D])) {
                $section->addTitle($title2, 2);

                foreach ([
                    "chapter" => "Book Chapters",
                    "article" => "Refereed Publications",
                    "magazine" => "Non-Refereed Publications"
                ] as $pubtype => $title3) {

                    if (isset($result['publication'][$cat][$D][$pubtype])) {
                        $section->addTitle($title3, 3);
                        if ($pubtype == 'article') {
                            $table = $section->addTable();
                                $table->addRow();
                                $cell = $table->addCell(9000);
                                $cell = $table->addCell(1000);
                                $cell->addText('Impact Factor', ['bold'=>true, 'underline'=> 'single'], $styleParagraphCenter);
                        }
                        foreach ($result['publication'][$cat][$D][$pubtype] as $doc) {
                            if ($pubtype == 'article') {
                                $table->addRow();
                                // in twip (1mm = 56,6928 twip)
                                $cell = $table->addCell(9000);
                                $line = $Format->format($doc['type'], $doc);
                                // echo $line;
                                // dump([$doc['dept'], $D]);
                                $line = clean_comment_export($line);
                                \PhpOffice\PhpWord\Shared\Html::addHtml($cell, $line, false, false);

                                $if = get_impact($doc['journal'], $doc['year'] - 1);
                                if (empty($if)) {
                                    $if = "IF not yet available";
                                } else {
                                    $if = number_format($if, 3, ',', '.');
                                }
                                $cell = $table->addCell(1000);
                                $cell->addText($if, $styleTextBold, $styleParagraphCenter);
                            } else {
                                $paragraph = $section->addTextRun();
                                $line = $Format->format($doc['type'], $doc);
                                // $line = clean_comment_export($line);
                                \PhpOffice\PhpWord\Shared\Html::addHtml($paragraph, $line);
                            }
                        }
                    }
                }
            }
        }
    }


    $section->addTitle("Knowledge Transfer", 1);

    // count tables
    if (!empty($countTables['transfer'])) {
        $table = $section->addTable("CountTable");

        $table->addRow();
        $cell = $table->addCell(5000, $styleCell);
        $cell->addText("Department", $styleTextBold, $styleParagraphCenter);
        $cell = $table->addCell(1500, $styleCell);
        $n = array_sum(array_column($countTables['transfer'], 'chapter'));
        $cell->addText("Non-Refereed contributions (total: $n)", $styleTextBold, $styleParagraphCenter);
        $cell = $table->addCell(1500, $styleCell);
        $n = array_sum(array_column($countTables['transfer'], 'article'));
        $cell->addText("Lectures (total: $n)", $styleTextBold, $styleParagraphCenter);
        $cell = $table->addCell(1500, $styleCell);
        $n = array_sum(array_column($countTables['transfer'], 'article'));
        $cell->addText("Posters (total: $n)", $styleTextBold, $styleParagraphCenter);

        foreach ($depts as $dept_short => $dept_name) {
            $table->addRow();
            // in twip (1mm = 56,6928 twip)
            $cell = $table->addCell(5000, $styleCell);
            $cell->addText($dept_name, $styleTextBold, $styleParagraphCenter);
            $data = $countTables['transfer'][$dept_short] ?? ["magazine" => 0, "lecture" => 0, "poster" => 0];
            $cell = $table->addCell(2500, $styleCell);
            $cell->addText($data['magazine'] == 0 ? '-' : $data['magazine'], $styleText, $styleParagraphCenter);
            $cell = $table->addCell(2500, $styleCell);
            $cell->addText($data['lecture'] == 0 ? '-' : $data['lecture'], $styleText, $styleParagraphCenter);
            $cell = $table->addCell(2500, $styleCell);
            $cell->addText($data['poster'] == 0 ? '-' : $data['poster'], $styleText, $styleParagraphCenter);
        }
    }

    $section->addTextRun();

    foreach ([
        "lecture" => "Lectures",
        "poster" => "Posters",
        "misc" => "Other activities"
    ] as $type => $title) {

        if (!empty($result[$type])) {
            $section->addTitle($title, 2);
            foreach ($result[$type] as $i => $doc) {
                $paragraph = $section->addTextRun();
                $line = $Format->format($doc['type'], $doc);
                // $line = clean_comment_export($line);
                \PhpOffice\PhpWord\Shared\Html::addHtml($paragraph, $line);
            }
        }
    }

    $section->addTitle('Editorial Boards of Scientific Journals and Reviewing for Scientific Journals', 2);


    foreach ([
        "editorial" => "Editorial Board of Journals:",
        "review" => "Reviewing for Journals:"
    ] as $type => $title) {

        if (!empty($result[$type])) {
            $paragraph = $section->addTextRun()->addText($title, ['underline' => 'single']);

            $data = $result[$type];
            asort($data);

            foreach ($data as $journal => $authors) {
                $paragraph = $section->addTextRun();

                $authors = array_unique($authors);
                sort($authors);

                $line = "$journal (" . implode(", ", $authors) . ")";
                // $line = clean_comment_export($line);
                \PhpOffice\PhpWord\Shared\Html::addHtml($paragraph, $line);
            }
        }
    }



    // $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
    // $objWriter->save('.data/report.html');
    // include_once '.data/report.html';

    // Download file
    $file = 'Report.docx';
    header("Content-Description: File Transfer");
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    $xmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $xmlWriter->save("php://output");
}, 'login');
