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

    $this->excludeOldEChannelQuestions($query);

    $total = $query->countQuery()->execute()->fetchField();
    return $total;
  }

  public function getRemaining() {
    $query = $this->database->select('asklib_questions', 'entity')
      ->distinct()
      ->fields('entity', ['id'])
      ->condition('entity.state', QuestionInterface::STATE_ANSWERED)
      ->condition('entity.published', 1);

    $this->excludeOldEChannelQuestions($query);

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
        // $answer = $question->getAnswer();
        $answer = $this->cachedAnswers[$question->get('answer')->target_id];

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

        if (!empty($document['terms'])) {
          $document['terms'] = array_values(array_unique($document['terms']));
        }

        if (!empty($document['tags'])) {
          $document['tags'] = array_values(array_unique($document['tags']));
        }

        $document['asklib_score'] = (int)$answer->getRating();
        $this->index($document);
      }
    }
  }

  protected function fetchItemsForIndexing() {
    $query = $this->database->select('asklib_questions', 'entity')
      ->distinct()
      ->fields('entity', ['id', 'answer'])
      ->range(0, $this->batchSize)
      ->orderBy('entity.id')
      ->condition('entity.state', QuestionInterface::STATE_ANSWERED)
      ->condition('entity.published', 1);

    $this->excludeOldEChannelQuestions($query);

    // NOTE: Reindex is NULL when left join is to zero rows.
    $query->condition($query->orConditionGroup()
      ->condition('search.reindex', NULL, 'IS')
      ->condition('search.reindex', 0, '<>'));

    $query->leftJoin('kifisearch_index', 'search', 'search.entity_id = entity.id AND search.entity_type = :type', [
      ':type' => 'asklib_question'
    ]);

    $result = $query->execute()->fetchAll();
    $qids = array_column($result, 'id');
    $aids = array_column($result, 'answer');

    if ($qids) {
      $this->cachedAnswers = \Drupal::entityTypeManager()->getStorage('asklib_answer')->loadMultiple($aids);
      return $this->storage->loadMultiple($qids);
    } else {
      return [];
    }
  }

  protected function excludeOldEChannelQuestions(&$query) {
    $echannel_id = 188298;
    $echannel_cutoff_date = '23-04-2024';

    // We need to filter out all questions, that 1. belong to the e-channel and 2. are older than 23.4.
    // Unfortunately, we need to verify this in two places, 1. from 'channel' in asklib_questions,
    // and 2. From table 'asklib_question__feeds'. In future, it probably would be better sync to
    // either to question's channel or to the *_feeds table and then remove one of the query groups.


    // First query based on question 'channel' parameter.
    $query->condition($query->orConditionGroup()
    ->condition('entity.channel', NULL, 'IS')
    ->condition('entity.channel', $echannel_id, '<>')
    ->condition($query->andConditionGroup()
      ->condition('entity.channel', $echannel_id)
      ->condition('entity.created', strtotime($echannel_cutoff_date), '>=')));


    // Second query based on the addtional 'asklib_question__feeds' table.
    $subquery = $this->database->select('asklib_questions', 'entity')
    ->fields('entity', ['id'])
    ->condition('entity.state', QuestionInterface::STATE_ANSWERED)
    ->condition('entity.published', 1)
    ->condition('entity.created', strtotime($echannel_cutoff_date), '<');
    $subquery->join('asklib_question__feeds', 'af', 'af.entity_id = entity.id');
    $subquery->condition('af.feeds_target_id', $echannel_id);

    $query->condition('entity.id', $subquery, 'NOT IN');
  }
}
