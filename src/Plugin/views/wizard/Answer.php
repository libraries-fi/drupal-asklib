<?php

namespace Drupal\asklib\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Tests creating managed files views with the wizard.
 *
 * @ViewsWizard(
 *   id = "asklib_answers",
 *   base_table = "asklib_answers",
 *   title = @Translation("Answers")
 * )
 */
class Answer extends WizardPluginBase {
  protected $createdColumn = 'created';
  protected $changedColumn = 'updated';

  protected $answerField = [
    'id' => 'answer',
    'table' => 'asklib_answers',
    'field' => 'answer',
    'exclude' => TRUE,
  ];

  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // $display_options['relationships']['question']['id'] = 'question';
    // $display_options['relationships']['question']['table'] = 'asklib_answers';
    // $display_options['relationships']['question']['field'] = 'body';
    // $display_options['relationships']['question']['entity_type'] = 'asklib_question';
    // $display_options['relationships']['question']['required'] = 1;
    // $display_options['relationships']['question']['plugin_id'] = 'standard';

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'access content';


//     // Remove the default fields, since we are customizing them here.

    unset($display_options['fields']);
//
    /* Field: File: Name */
    $display_options['fields']['answer']['id'] = 'answer';
    $display_options['fields']['answer']['table'] = 'asklib_answers';
    $display_options['fields']['answer']['field'] = 'body';
    $display_options['fields']['answer']['label'] = t('Title');
    $display_options['fields']['answer']['alter']['alter_text'] = 0;
    $display_options['fields']['answer']['alter']['make_link'] = 0;
    $display_options['fields']['answer']['alter']['absolute'] = 0;
    $display_options['fields']['answer']['alter']['trim'] = 0;
    $display_options['fields']['answer']['alter']['word_boundary'] = 0;
    $display_options['fields']['answer']['alter']['ellipsis'] = 0;
    $display_options['fields']['answer']['alter']['strip_tags'] = 0;
    $display_options['fields']['answer']['alter']['html'] = 0;
    $display_options['fields']['answer']['hide_empty'] = 0;
    $display_options['fields']['answer']['empty_zero'] = 0;
    $display_options['fields']['answer']['link_to_file'] = 1;

    return $display_options;
  }
}
