<?php

namespace Drupal\asklib\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\asklib\QuestionChannelInterface;
use Drupal\asklib\QuestionInterface;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemoteQuestionForm extends ContentEntityForm {
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $allowed = ['body', 'email', 'municipality'];

    foreach (Element::children($form) as $key) {
      if (!in_array($key, $allowed)) {
        unset($form[$key]);
      }
    }

    $form['body']['widget'][0]['#format'] = 'basic_html_without_ckeditor';
    $form['body']['widget'][0]['#rows'] = 12;
    $form['body']['widget'][0]['#description'] = '';

    $form['body']['widget'][0]['#title'] = $this->t('Your question');
    $form['email']['widget'][0]['value']['#title'] = $this->t('Your email address');
    $form['municipality']['widget']['#title'] = $this->t('Your municipality');
    $form['municipality']['widget']['#description'] = '';

    return $form;
  }

  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Send');

    return $actions;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $channel = $this->channelFromRoute();
    $this->entity->setChannel($channel);
    $this->entity->get('feeds')->appendItem($channel);
  }

  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->setNotificationFlags(-1);
    $message = \Drupal::config('asklib.settings')->get('confirmation');
    drupal_set_message($message);

    return parent::save($form, $form_state);
  }

  private function channelFromRoute() {
    $term = \Drupal::routeMatch()->getParameter('channel');

    if ($term->getVocabularyId() != 'asklib_channels') {
      throw new NotFoundHttpException;
    }

    return $term;
  }
}
