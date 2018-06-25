<?php

namespace Drupal\asklib;

use InvalidArgumentException;
use Drupal\kifisearch\IndexerBase;

class QuestionIndexer extends IndexerBase {
  public function getTotal() {
    $query = $this->database->select('asklib_questions', 'entity')
      ->distinct()
      ->fields('entity', ['id'])
      ->condition('entity.state', QuestionInterface::STATE_ANSWERED)
      ->condition('entity.published', 1);

    $total = $query->countQuery()->execute()->fetchField();
    return $total;
  }

  public function getRemaining() {
    $query = $this->database->select('asklib_questions', 'entity')
      ->distinct()
      ->fields('entity', ['id'])
      ->condition('entity.state', QuestionInterface::STATE_ANSWERED)
      ->condition('entity.published', 1);

    // NOTE: Reindex is NULL when left join is to zero rows.
    $query->condition($query->orConditionGroup()
      ->condition('search.reindex', NULL, 'IS')
      ->condition('search.reindex', 0, '<>'));

    $query->leftJoin('kifisearch_index', 'search', 'search.entity_id = entity.id AND search.entity_type = :type', [
      ':type' => 'asklib_question'
    ]);

    $remaining = $query->countQuery()->execute()->fetchField();

    return $remaining;
  }

  public function updateIndex() {
    foreach ($this->fetchItemsForIndexing() as $question) {
      foreach ($question->getTranslationLanguages() as $language) {
        $langcode = $language->getId();
        $question = $question->getTranslation($langcode);
        $answer = $question->getAnswer();

        $document = [
          'entity_type' => 'asklib_question',
          'id' => (int)$question->id(),
          'bundle' => $question->bundle(),
          'title' => $question->label(),
          'langcode' => $langcode,
          'created' => date('Y-m-d\TH:i:s', $question->getCreatedTime()),
          'changed' => date('Y-m-d\TH:i:s', $question->getChangedTime()),
        ];

        $question_body = $this->stripHtml($question->getBody());
        $answer_body = $this->stripHtml($answer->getBody());

        $document['body'] = $question_body . "\n\n\n\n\n" . $answer_body;

        foreach ($question->getTags() as $tag) {
          $document['terms'][] = (int)$tag->id();

          try {
            // Terms from asklib_tags vocabulary don't have translations but for some reason
            // they can / have been bound to questions of all languages. Maybe a bug, maybe inherited
            // from the old Meteor CMS.
            $document['tags'][] = $tag->getTranslation($langcode)->label();
          } catch (InvalidArgumentException $e) {
            // pass
          }
        }

        foreach ($question->getFeeds() as $feed) {
          $document['terms'][] = (int)$feed->id();

          try {
            // Terms from asklib_tags vocabulary don't have translations but for some reason
            // they can / have been bound to questions of all languages. Maybe a bug, maybe inherited
            // from the old Meteor CMS.
            $document['tags'][] = $feed->getTranslation($langcode)->label();
          } catch (InvalidArgumentException $e) {
            // pass
          }
        }

        $document['fields']['asklib_question']['score'] = (int)$answer->getRating();
        $this->index($document);
      }
    }
  }

  protected function fetchItemsForIndexing() {
    $query = $this->database->select('asklib_questions', 'entity')
      ->distinct()
      ->fields('entity', ['id'])
      ->range(0, $this->batchSize)
      ->orderBy('entity.id')
      ->condition('entity.state', QuestionInterface::STATE_ANSWERED)
      ->condition('entity.published', 1);

    // NOTE: Reindex is NULL when left join is to zero rows.
    $query->condition($query->orConditionGroup()
      ->condition('search.reindex', NULL, 'IS')
      ->condition('search.reindex', 0, '<>'));

    $query->leftJoin('kifisearch_index', 'search', 'search.entity_id = entity.id AND search.entity_type = :type', [
      ':type' => 'asklib_question'
    ]);

    $ids = $query->execute()->fetchCol();

    if ($ids) {
      return $this->storage->loadMultiple($ids);
    } else {
      return [];
    }
  }
}
