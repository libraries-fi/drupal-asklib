<?php

namespace Drupal\asklib\Form;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilderInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class GenericSearchForm extends FormBase {  
  public function getFormId() {
    return 'asklib_search_form';
  }

  /**
   * @param $entity_type Entity type definition for the entities that this form is used with.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'form--inline';
    $form['q'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $form_state->getValue('q'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->get('list_builder')->searchQuery = $form_state->getValue('q');
  }
}
