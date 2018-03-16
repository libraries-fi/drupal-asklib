<?php

namespace Drupal\asklib\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;

class AdminForm extends ConfigFormBase {
  protected $terms;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager')->getStorage("taxonomy_term")
    );
  }

  public function __construct(ConfigFactoryInterface $config, EntityStorageInterface $terms) {
    parent::__construct($config);
    $this->terms = $terms;
  }

  public function getFormId() {
    return 'asklib_admin_config';
  }

  protected function getEditableConfigNames() {
    return ['asklib.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('asklib.settings');
    $site_config = $this->config('system.site');
    $form = parent::buildForm($form, $form_state);

    $form['reserved_window'] = [
      '#type' => 'number',
      '#field_suffix' => $this->t('days'),
      '#title' => $this->t('Reservation window'),
      '#description' => $this->t('Number of days after which reserved unanswered questions are released.'),
      '#default_value' => $config->get('reserved_window'),
      '#attributes' => [
        'min' => 1,
        'max' => 30,
      ]
    ];

    $form['highlight_pending_after'] = [
      '#type' => 'number',
      '#field_suffix' => $this->t('days'),
      '#title' => $this->t('Highlight unanswered'),
      '#description' => $this->t('Highlight questions that have not been answered in this many days.'),
      '#default_value' => $config->get('highlight_pending_after'),
      '#attributes' => [
        'min' => 1,
        'max' => 30,
      ]
    ];

    $form['field_reply'] = [
      '#type' => 'details',
      '#title' => $this->t('Reply address'),
      '#open' => !empty($config->get('reply.address')),
      '#description' => $this->t('Configure to override global mail settings'),
      '#open' => true,
      'reply_address' => [
        '#type' => 'textfield',
        '#title' => $this->t('Email address'),
        '#default_value' => $config->get('reply.address'),
        '#attributes' => [
          'placeholder' => $site_config->get('mail'),
        ]
      ],
      'reply_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Sender name'),
        '#default_value' => $config->get('reply.name'),
        '#attributes' => [
          'placeholder' => $site_config->get('name')
        ]
      ]
    ];

    $terms = $this->terms->loadByProperties(['vid' => 'forums']);
    $options = array_map(function($term) { return $term->label(); }, $terms);

    $form['field_forum'] = [
      '#type' => 'details',
      '#title' => $this->t('Administrators forum'),
      '#open' => true,
      '#description' => $this->t('This forum will be made private for the administrators.'),
      'forum' => [
        '#type' => 'select',
        '#title' => $this->t('Forum'),
        '#options' => $options,
        '#empty_option' => '',
        '#default_value' => $config->get('forum'),
      ]
    ];

    $form['field_confirmation'] = [
      '#type' => 'details',
      '#title' => $this->t('Confirmation message'),
      '#description' => $this->t('Notification for users after submitting a new question.'),
      '#open' => TRUE,
      'confirmation' => [
        '#type' => 'text_format',
        '#title' => $this->t('Message'),
        '#default_value' => $config->get('confirmation'),
        '#format' => $config->get('confirmation_format') ?: 'basic_html_without_ckeditor',
        '#rows' => 2,
      ]
    ];

    $form['field_help'] = [
      '#type' => 'details',
      '#title' => $this->t('Info text for librarians.'),
      '#description' => $this->t('This text will be displayed on the front page.'),
      '#open' => TRUE,
      'help' => [
        '#type' => 'text_format',
        '#title' => $this->t('Body'),
        '#default_value' => $config->get('help'),
        '#format' => $config->get('help_format'),
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('asklib.settings')
      ->set('reserved_window', $form_state->getValue('reserved_window'))
      ->set('highlight_pending_after', $form_state->getValue('highlight_pending_after'))
      ->set('reply.address', $form_state->getValue('reply_address'))
      ->set('reply.name', $form_state->getValue('reply_name'))
      ->set('forum', $form_state->getValue('forum'))
      ->set('help', $form_state->getValue('help')['value'])
      ->set('help_format', $form_state->getValue('help')['format'])
      ->set('confirmation', $form_state->getValue('confirmation')['value'])
      ->set('confirmation_format', $form_state->getValue('confirmation')['format'])
      ->save();
  }
}
