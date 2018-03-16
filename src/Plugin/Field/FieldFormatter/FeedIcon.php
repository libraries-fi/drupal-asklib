<?php

namespace Drupal\asklib\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Url;

/**
 * Creates a link based on path alias
 *
 * @FieldFormatter(
 *   id = "asklib_feed_icon",
 *   label = @Translation("Feed icon"),
 *   field_types = {
 *     "list_string",
 *     "entity_reference",
 *   }
 * )
 */
class FeedIcon extends FormatterBase {
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    if ($items->count()) {
      $provider = $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getOptionsProvider('value', $items->getEntity());

      // Flatten the possible options, to support opt groups.
      $options = OptGroup::flattenOptions($provider->getPossibleOptions());

      foreach ($items as $delta => $item) {
        // var_dump($item);
        if ($item->value) {
          $label = $options[$item->value];
          $src = sprintf('/modules/asklib/public/images/feed-%s.png', $item->value);
          $elements[$delta] = [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'title' => $label,
              'src' => Url::fromUserInput($src)->toString(),
            ],
          ];
        } elseif ($channel = $item->entity) {
          $src = sprintf('/modules/asklib/public/images/feed-%s.png', $channel->getCode());
          $elements[$delta] = [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'title' => $label,
              'src' => Url::fromUserInput($src)->toString(),
            ],
          ];
        }
      }
    }

    return $elements;
  }
}
