<?php

namespace Drupal\asklib;

use PDO;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class QuestionStorage extends SqlContentEntityStorage {
  public function findSimilarQuestions(QuestionInterface $question, $limit = 10) {
    if (!($tags = $question->getTags())) {
      return [];
    }

    $tags = array_map(function($t) { return $t->id(); }, $tags);

    $query = $this->database->select('asklib_questions', 'q')
      ->fields('q', ['id'])
      ->condition('q.langcode', $question->language()->getId())
      ->condition('q.published', true)
      ->condition('q.state', QuestionInterface::STATE_ANSWERED)
      ->condition('q.id', $question->id(), '<>')
      ->condition('t.tags_target_id', $tags, 'IN')
      ->groupBy('q.id')
      ->orderBy('matches', 'DESC')
      ->range(0, $limit);

    $query->innerJoin('asklib_question__tags', 't', 't.entity_id = q.id');
    $query->addExpression('COUNT(*)', 'matches');
    $result = $query->execute()->fetchAll(PDO::FETCH_COLUMN);

    return $this->loadMultiple($result);
  }
}
