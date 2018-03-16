<?php

namespace Drupal\asklib\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\RouteCacheContext;

/**
 * Caching by route parameters asklib_question.
 */
class QuestionCacheContext extends RouteCacheContext {
  public static function getLabel() {
    return t('Ask a librarian question');
  }

  public function getContext() {
    if ($question = $this->routeMatch->getParameter('asklib_question')) {
      return $question->id();
    } else {
      return '0';
    }
  }
}
