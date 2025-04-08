<?php

declare(strict_types=1);

namespace Drupal\asklib\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Provides published-only selection for municipalities.
 *
 * @EntityReferenceSelection(
 *   id = "asklib_question_municipality_selection",
 *   label = @Translation("Question municipality selection"),
 *   group = "asklib_question_municipality_selection",
 *   entity_types = {"taxonomy_term"},
 * )
 */
final class MunicipalitySelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $query = parent::buildEntityQuery($match, $match_operator);

    // Limit to published terms only.
    $query->condition('status', 1);

    return $query;
  }

}
