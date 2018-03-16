<?php

namespace Drupal\asklib\Plugin\Field\FieldWidget;

use Drupal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Simplified form widget for attached files.
 *
 * @FieldWidget(
 *   id = "asklib_file_generic",
 *   label = @Translation("File"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class AttachedFiles extends WidgetBase {
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    foreach ($items as $item) {
      $file = $item->entity;

      $element[$delta] = [
        'filename' => [
          '#theme' => 'file_link',
          '#file' => $file,
        ]
      ];
    }

    return $element;
  }

  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $elements = [];
    $title = $this->fieldDefinition->getLabel();
    $description = FieldFilteredMarkup::create(Drupal::token()->replace($this->fieldDefinition->getDescription()));

    foreach ($items as $delta => $file) {
      $element = [
        '#title' => $title,
        '#title_display' => 'before',
        '#description' => $description,
      ];

      $elements[$delta] = $this->formSingleElement($items, $delta, $element, $form, $form_state);
    }

    $elements += [
      '#type' => 'container',
      '#attributes' => [
        // This class is needed for opening files in new windows on the form.
        'class' => ['js-form-managed-file'],
      ]
    ];

    $container = [
      '#type' => 'fieldset',
      '#title' => $title,
      // '#description' => $description,

      'widget' => $elements,

      // 'empty' => [
      //   '#type' => 'item',
      //   '#markup' => $this->t('No attachments'),
      // ],
    ];

    return $container;
  }
}
