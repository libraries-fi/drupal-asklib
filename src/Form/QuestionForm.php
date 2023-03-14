<?php

namespace Drupal\asklib\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asklib\Entity\QuestionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class QuestionForm extends ContentEntityForm {
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    if ($this->currentUser()->isAuthenticated()) {
      $form['user'] = [
        '#type' => 'value',
        '#value' => $this->currentUser()->id(),
      ];
    }

    // Need to set language manually because Question is not marked as 'translatable'.
    $form['langcode'] = [
      '#type' => 'value',
      '#value' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
    ];

    $form['body']['widget'][0]['#title'] = $this->t('Your question');
    $form['body']['widget'][0]['#format'] = 'basic_html_without_ckeditor';
    $form['body']['widget'][0]['#description'] = $this->t('Do not write your personal information here. Questions will usually be published. Attached pictures will also usually be published.');

    $form['email']['widget'][0]['value']['#title'] = $this->t('Your email address');
    $form['email']['widget'][0]['value']['#required'] = TRUE;
    $form['email']['widget'][0]['value']['#attributes']['autocomplete'] = 'email';

    $form['municipality']['widget']['#description'] = '';
    $form['municipality']['widget']['#title'] = $this->t('Your municipality');

    // Define empty_option to be an empty string in order for HTML5 validation to work.
    $form['municipality']['widget']['#empty_value'] = '';
    $form['municipality']['widget']['#empty_option'] = $this->t('- Select a value -');
    unset($form['municipality']['widget']['#options']['_none']);

    $form['attachments']['widget']['#description'] = $this->t('You may add files if needed.');

    $form['extra'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Other details'),
      '#attributes' => [
        'class' => ['link-button']
      ],
      '#attached' => [
        'library' => ['asklib/details-button']
      ],
    ];

    $form['attachments']['#group'] = 'extra';
    $form['details']['#group'] = 'extra';


    $form['theme_widget'] = [
      '#type' => 'container',
      '#group' => 'extra',
      '#weight' => $form['details']['#weight'] + 1,

      'theme' => [
        '#type' => 'select',
        '#title' => $this->t('Theme'),
        '#options' => $this->getCategoryOptions(),
        '#description' => $this->t('Choose a topic if you wish to target the question at a specific library.'),
        '#empty_option' => $this->t('- None -'),
      ]
    ];

    return $form;
  }

  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Send');

    // Inject '::processTheme' before '::save'.
    $submit = &$actions['submit']['#submit'];
    array_splice($submit, array_search('::save', $submit), 0, ['::processTheme']);
    unset($submit);

    return $actions;
  }

  public function processTheme(array $form, FormStateInterface $form_state) {
    if ($tid = $form_state->getValue('theme')) {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $query = $term_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', 'asklib_libraries')
        ->condition('field_asklib_theme', $tid);

      if ($result = $query->execute()) {
        $libraries = $term_storage->loadMultiple($result);
        $target_library = reset($libraries);
        $this->entity->setTargetLibrary($target_library);
      }
    }
  }

  public function submitForm(array & $form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $term = $this->entity->getMunicipality();
    if ($term->get('field_asklib_active')->value && !$this->entity->getTargetLibrary()) {
      $this->entity->setTargetLibrary($this->entity->getMunicipality());
    }
  }

  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->setNotificationFlags(-1);

    $this->messenger()->addStatus(t('Thank you for your question! We will answer you within three days. If you do not hear from us, please contact us at @email.', [
      '@email' => 'toimitus@kirjastot.fi'
    ]));

    return parent::save($form, $form_state);
  }

  protected function getCategoryOptions() {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $query = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'name'])
      ->condition('t.vid', 'asklib_themes')
      ->condition('t.langcode', $langcode)
      ->orderBy('t.name')
      ;

    $options = $query->execute()->fetchAllKeyed();

    return $options;
  }
}
