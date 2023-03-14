<?php

namespace Drupal\asklib;

use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Render\Element;

class QuestionViewBuilder extends EntityViewBuilder {
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode, $langcode = null) {
    parent::buildComponents($build, $entities, $displays, $view_mode, $langcode);

    foreach ($entities as $delta => $question) {
      if ($question->isPublished()) {
        if ($view_mode == 'full') {
          $build[$delta]['ask_more'] = [
            // Hide for now
            '#access' => FALSE,

            '#theme' => 'asklib_ref_question',
            '#weight' => -100,
            '#url' => $question->toUrl('add-form', ['query' => [
              'ref' => $question->id(),
            ]]),
          ];

          if (!empty($build[$delta]['tags'])) {
            $this->sortTags($build[$delta]['tags']);
          }
        }
      } else {
        $build[$delta]['comments']['#access'] = FALSE;
      }
    }
  }

  /**
   * Sort tags i.e. taxonomy terms alphabetically.
   */
  protected function sortTags(array &$tags) {
    $keys = Element::children($tags, TRUE);
    $items = [];

    usort($keys, fn($a, $b) => strcasecmp($tags[$a]['#title'], $tags[$b]['#title']));

    foreach ($keys as $i => $key) {
      $items[$i] = $tags[$key];
    }

    foreach ($items as $i => &$child) {
      $tags[$i] = $child;
      $child['#weight'] = $i;
    }
  }
}
