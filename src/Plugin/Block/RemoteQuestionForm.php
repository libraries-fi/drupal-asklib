<?php

namespace Drupal\asklib\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'New forum topics' block.
 *
 * @Block(
 *   id = "asklib_remote_question_block",
 *   admin_label = @Translation("Embedded question form"),
 *   category = @Translation("Ask a Librarian")
 * )
 */
class RemoteQuestionForm extends BlockBase {
  public static function form() {
    return [
      '#type' => 'form',
      'name' => [
        '#id' => 'asklib-remote-name',
        '#type' => 'textfield',
        '#title' => t('Name'),
        '#required' => TRUE,
        '#value' => '',
      ],
      'email' => [
        '#id' => 'asklib-remote-email',
        '#type' => 'email',
        '#title' => t('Email'),
        '#value' => '',
      ],
      'library' => [
        '#id' => 'asklib-remote-library',
        '#type' => 'select',
        '#title' => t('Your library'),
        '#options' => ['Test'],
        '#required' => TRUE,
        '#value' => '',
      ],
      'body' => [
        '#id' => 'asklib-remote-body',
        '#type' => 'textarea',
        '#title' => t('Your question'),
        '#rows' => 8,
        '#cols' => 40,
        '#required' => TRUE,
        '#value' => '',
      ],
      '#attached' => [
        'library' => ['asklib/rating']
      ]
    ];
  }

  public function build() {
    return [
      '#theme' => 'asklib_remote_form'
    ];
  }
}
