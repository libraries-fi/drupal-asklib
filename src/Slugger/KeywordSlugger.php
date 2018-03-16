<?php

namespace Drupal\asklib\Slugger;

use Drupal\Core\Entity\EntityInterface;
use Drupal\autoslug\Slugger\DefaultSlugger;
use Drupal\taxonomy\TermInterface;

class KeywordSlugger extends DefaultSlugger {
  public function applies(EntityInterface $entity) {
    $vids = ['asklib_tags', 'finto'];
    return $entity instanceof TermInterface && in_array($entity->getVocabularyId(), $vids, TRUE);
  }

  protected function extractTokens(EntityInterface $entity, $pattern, $max_words = 0) {
    $values = parent::extractTokens($entity, $pattern, $max_words);
    $allowed = array_merge(range('a', 'z'), ['åäö']);
    $token = 'name[0]';

    if (!in_array($values[$token], $allowed, TRUE)) {
      $values[$token] = '0-9';
    }

    return $values;
  }
}
