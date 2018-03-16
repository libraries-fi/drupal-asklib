<?php

namespace Drupal\asklib\Plugin\Field\FieldWidget;

use Drupal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asklib\AnswerInterface;

/**
 * Provides a default comment widget.
 *
 * @FieldWidget(
 *   id = "asklib_answer",
 *   label = @Translation("Answer"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class AnswerWidget extends WidgetBase {
  protected $filesComponent;

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    foreach ($items as $item) {
      $answer = $item->entity;

      if ($answer) {
        if ($answer->id()) {
          $element['target_id'] = [
            '#type' => 'hidden',
            '#value' => $answer->id(),
          ];
        }

        $display = \Drupal\Core\Entity\Entity\EntityFormDisplay::collectRenderDisplay($answer, 'default');

        $element['body'] = $display->getRenderer('body')->form($answer->get('body'), $form, $form_state);

        $element['details'] = $display->getRenderer('details')->form($answer->get('details'), $form, $form_state);

        $element['details']['widget']['#field_parents'] = ['answer', $delta, 'details'];
        $element['details']['widget']['#parents'] = ['answer', $delta, 'details'];

        $element['body']['widget']['#field_parents'] = ['answer', $delta, 'body'];
        $element['body']['widget']['#parents'] = ['answer', $delta, 'body'];

        $element['rating'] = $display->getRenderer('rating')->form($answer->get('rating'), $form, $form_state);

        $fids = array_map(function($o) { return $o->id(); }, $answer->getAttachments());

        $element['attachments'] = [
          '#type' => 'managed_file',
          '#title' => $answer->attachments->getFieldDefinition()->getLabel(),
          '#description' => $answer->attachments->getFieldDefinition()->getDescription(),
          '#default_value' => $fids,
          '#multiple' => TRUE,
          '#upload_location' => 'public://asklib/answers',
          '#upload_validators' => [
            'file_validate_extensions' => ['png jpg jpeg doc docx ppt pptx pdf'],
          ],
          '#entity_type' => 'asklib_answer',
          '#field_name' => 'attachments',
          '#cardinality' => 5,
        ];

        $element['library'] = [
          '#type' => 'container',
          '#tree' => TRUE,
          '#access' => !is_null($answer->get('library')->target_id),
          'widget' => [
            '#parents' => ['answer', $delta, 'library'],
            '#field_name' => 'library',
            '#field_parents' => [],
            '#tree' => TRUE,
            'target_id' => [
              '#required' => TRUE,
              '#type' => 'select',
              '#title' => $this->t('Answerer'),
              '#description' => $this->t('This library will be displayed as the answerer.'),
              '#options' => $this->getAnswererLibraryOptions($answer),
              '#empty_option' => $this->t('- Select -'),
              '#default_value' => $answer->get('library')->target_id,
            ]
          ]
        ];

        /*
         * NOTE: Following implementation is broken because it collides with the 'attachments' field of
         * the Question entity. Cannot figure out how to fix the name.
         */

        // $element['attachments']['#process'][] = [AnswerWidget::class, 'processFileWidget'];

        // $display = $form_state->get('form_display');
        // $this->overrideFilesComponent($display);
        // $element['attachments'] = $display->getRenderer('attachments')->form($answer->attachments, $form, $form_state);
        // $this->restoreFilesComponent($display);


        // foreach ($element['attachments']['widget'] as $delta => &$field) {
        //   if (is_numeric($delta)) {
        //     $field['#field_parents'][] = 'answer';
        //   }
        // }
        // var_dump(array_keys($element['attachments']['widget']['#after_build']));
      }
    }

    return $element;
  }

  public static function processFileWidget(&$element, FormStateInterface $form_state, &$form) {

    $element['upload_button']['#submit'][] = [AnswerWidget::class, 'processUploadedFiles'];

    // var_dump($element['upload_button']['#submit']);
    return $element;
    // $element['upload_button']['#submit'][] = [AnswerWidget::class, 'processUploadedFiles'];
  }

  public static function processUploadedFiles(array $form, FormStateInterface $form_state) {
    // $attachments = $form_state->getValue('answer')[0]['attachments'];
    //
    // if ($attachments) {
    //   $question = $form_state->getFormObject()->getEntity();
    //   $answer = $question->getAnswer();
    //   $answer->setAttachments($attachments);
    //
    //   foreach ($answer->getAttachments() as $file) {
    //     Drupal::service('file.usage')->add($file, 'asklib', 'asklib_answer', $answer->id());
    //   }
    // }
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'asklib_answer';
  }

  protected function restoreFilesComponent(EntityFormDisplayInterface $display) {
    $display->setComponent('attachments', $this->filesComponent);
  }

  protected function overrideFilesComponent(EntityFormDisplayInterface $display) {
    $this->filesComponent = $display->getComponent('attachments');
    $display->setComponent('attachments', ['type' => 'file_generic'] + $this->filesComponent);
  }

  protected function getAnswererLibraryOptions(AnswerInterface $answer) {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties(['vid' => ['asklib_libraries', 'asklib_municipalities']]);
    $tree = [];

    foreach ($terms as $i => $term) {
      $tree[$term->get('vid')->entity->label()][$term->id()] = $term->label();
    }

    foreach ($tree as $key => &$group) {
      asort($group);
    }

    ksort($tree);

    return $tree;
  }
}
