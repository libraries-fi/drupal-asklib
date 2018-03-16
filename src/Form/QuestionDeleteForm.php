<?php

namespace Drupal\asklib\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class QuestionDeleteForm extends ContentEntityConfirmFormbase
{
    public function getQuestion()
    {
        return $this->t('Are you sure you want to delete the question #@id', ['@id' => $this->entity->id()]);
    }

    public function getCancelUrl()
    {
        return Url::fromRoute('view.asklib_index.page_1');
    }

    public function getConfirmText()
    {
        return $this->t('Delete');
    }

    public function getDescription()
    {
        return $this->t('The question will be deleted permanently.');
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->entity->delete();
        drupal_set_message($this->t('The question has been deleted.'));
        $this->logger('content')->notice('Deleted question @id', ['@id' => $this->entity->id()]);
        $form_state->setRedirectUrl($this->getCancelUrl());
    }
}
