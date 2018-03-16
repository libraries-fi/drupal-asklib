<?php

$pdo = new PDO('mysql:dbname=kirjastot_fi', 'root');

$result = $pdo->query('
  SELECT
    GROUP_CONCAT(tid) tids,
    GROUP_CONCAT(langcode) langcode,
    COUNT(*) total,
    name
  FROM
    taxonomy_term_field_data a
  WHERE a.vid = \'asklib_tags\'
  GROUP BY name
  HAVING total > 1
  ORDER BY a.name
');

$result->setFetchMode(PDO::FETCH_OBJ);

$smt1 = $pdo->prepare('
  UPDATE taxonomy_term_field_data
  SET tid = ? WHERE tid IN (?, ?)
');

$smt2 = $pdo->prepare('
  UPDATE asklib_question__tags
  SET tags_target_id = ?
  WHERE tags_target_id IN (?, ?)
');

$smt3 = $pdo->prepare('
  UPDATE asklib_question_revision__tags
  SET tags_target_id = ?
  WHERE tags_target_id IN (?, ?)
');

$smt4 = $pdo->prepare('
  DELETE FROM taxonomy_term_data WHERE tid IN (?, ?)
');

foreach ($result as $row) {
  $tids = array_pad(explode(',', $row->tids), 3, 0);
  $smt1->execute($tids);
  $smt2->execute($tids);
  $smt3->execute($tids);

  array_shift($tids);

  $smt4->execute($tids);
}

// var_dump($result);
