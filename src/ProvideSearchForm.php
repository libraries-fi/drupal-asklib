<?php

namespace Drupal\asklib;

use Drupal\Core\Form\FormState;
use Drupal\asklib\Form\GenericSearchForm;

trait ProvideSearchForm {
  protected $_searchForm;
  protected $_searchFormState;

  protected function searchForm() {
    if (!$this->_searchForm) {
      $form_state = new FormState;
      $form_state->setStorage(['list_builder' => $this])
        ->setMethod('get')
        ->setAlwaysProcess()
        ->disableRedirect();

      $builder = \Drupal::service('form_builder');
      $form = $builder->buildForm(GenericSearchForm::class, $form_state);

      $this->_searchFormState = $form_state;
      $this->_searchForm = $form;
    }

    return $this->_searchForm;
  }

  protected function searchFormState() {
    if (!$this->_searchFormState) {
      $this->searchForm();
    }
    return $this->_searchFormState;
  }
}
