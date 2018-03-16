<?php

namespace Drupal\asklib\Plugin\Field\FieldFormatter;

use LogicException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\user\UserInterface;

/**
 * Creates a link based on path alias
 *
 * @FieldFormatter(
 *   id = "user_email",
 *   label = @Translation("Email address"),
 *   description = @Translation("Display email addresses as the label of the entities."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class UserEmail extends EntityReferenceLabelFormatter {
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $user) {
      if (!($user instanceof UserInterface)) {
        throw new LogicException('This field only supports User entities.');
      }
      $key = isset($elements[$delta]['#plain_text']) ? '#plain_text' : '#title';
      $elements[$delta][$key] = $user->getEmail();
    }

    return $elements;
  }
}
