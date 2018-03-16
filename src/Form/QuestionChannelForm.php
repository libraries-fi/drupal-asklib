<?php

namespace Drupal\asklib\Form;

use Drupal\asklib\Entity\QuestionChannel;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuestionChannelForm extends ContentEntityForm {
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['code']['#disabled'] = !$this->entity->isNew();
    $form['code']['widget'][0]['value']['#type'] = 'machine_name';
    $form['code']['widget'][0]['value']['#machine_name'] = [
      'exists' => QuestionChannel::class . '::load',
      'source' => ['name', 'widget', 0, 'value']
    ];

    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->urlInfo('collection'));

    if ($status == SAVED_NEW) {
      drupal_set_message($this->t('Channel @id has been created.', ['@id' => $this->entity->id()]));
    } else {
      drupal_set_message($this->t('Changes have been saved.'));
    }

    return $status;
  }
}
