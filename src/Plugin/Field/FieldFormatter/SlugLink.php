<?php

namespace Drupal\asklib\Plugin\Field\FieldFormatter;

use Drupal;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Creates a link based on path alias
 *
 * @FieldFormatter(
 *   id = "slug_link",
 *   label = @Translation("Link using slug"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class SlugLink extends FormatterBase {
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    // $aliases = Drupal::service('path.alias_storage');

    foreach ($items as $delta => $item) {
      $entity = $item->getEntity();

      if ($entity) {
        $url = $entity->url();
        // $alias = $aliases->lookupPathAlias($url, $langcode);
        // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
        // Please confirm that `$entity` is an instance of `Drupal\Core\Entity\EntityInterface`. Only the method name and not the class name was checked for this replacement, so this may be a false positive.
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $entity->label(),
          '#url' => $entity->toUrl(),
        ];
      }
    }

    return $elements;
  }
}
