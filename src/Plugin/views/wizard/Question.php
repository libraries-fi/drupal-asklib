<?php

namespace Drupal\asklib\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Tests creating managed files views with the wizard.
 *
 * @ViewsWizard(
 *   id = "asklib_questions",
 *   base_table = "asklib_questions",
 *   title = @Translation("Questions")
 * )
 */
class Question extends WizardPluginBase {
  protected $createdColumn = 'created';
  protected $changedColumn = 'changed';

  protected $questionField = [
    'id' => 'question',
    'table' => 'asklib_questions',
    'field' => 'question',
    'exclude' => TRUE,
    'entity_type' => 'asklib_question',
  ];

  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['provider'] = 'user';

    // Remove the default fields, since we are customizing them here.
    unset($display_options['fields']);

    $display_options['fields']['question']['id'] = 'question';
    $display_options['fields']['question']['table'] = 'asklib_questions';
    $display_options['fields']['question']['field'] = 'title';
    $display_options['fields']['question']['provider'] = 'title';
    $display_options['fields']['question']['label'] = t('Question');
    $display_options['fields']['question']['alter']['alter_text'] = 0;
    $display_options['fields']['question']['alter']['make_link'] = 0;
    $display_options['fields']['question']['alter']['absolute'] = 0;
    $display_options['fields']['question']['alter']['trim'] = 0;
    $display_options['fields']['question']['alter']['word_boundary'] = 0;
    $display_options['fields']['question']['alter']['ellipsis'] = 0;
    $display_options['fields']['question']['alter']['strip_tags'] = 0;
    $display_options['fields']['question']['alter']['html'] = 0;
    $display_options['fields']['question']['hide_empty'] = 0;
    $display_options['fields']['question']['empty_zero'] = 0;
    $display_options['fields']['question']['link_to_file'] = 1;

    return $display_options;
  }
}
