<?php

namespace Drupal\asklib\Plugin\Field\FieldWidget;

use Drupal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Simplified form widget for attached files.
 *
 * @FieldWidget(
 *   id = "asklib_parent_question",
 *   label = @Translation("Referenced question"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ParentQuestion extends WidgetBase {
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    foreach ($items as $item) {
      $parent = $item->entity;

      if ($parent) {
        $element = [
          '#type' => 'item',
          '#title' => $parent->parent->getFieldDefinition()->getLabel(),
          '#attributes' => [
            'class' => ['parent-link-wrapper'],
          ],
          'link' => [
            '#type' => 'link',
            '#title' => $parent->label(),
            '#url' => $parent->urlInfo('edit-form'),
          ],
          'value' => [
            '#type' => 'value',
            '#value' => $parent->id(),
          ],
        ];
      }
    }

    return $element;
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'asklib_question';
  }
}
