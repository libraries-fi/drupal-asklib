<?php

namespace Drupal\asklib\Form;

use DateTime;
use Drupal\Core\Url;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asklib\Event\QuestionEvent;
use Drupal\asklib\QuestionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class QuestionRedirectForm extends ContentEntityForm {
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // $form['target_library']['widget']['#default_value'] = NULL;

    $question = $this->entity;

    $form['current_library'] = [
      '#type' => 'item',
      '#title' => $this->t('Current group'),
      '#markup' => $question->getTargetLibrary()
        ? $question->getTargetLibrary()->label()
        : $this->t('No group set'),
    ];

    $options = $this->filterDisabledAnswerers($form['target_library']['widget']['#options']);
    $form['target_library']['widget']['#options'] = $options;

    return $form;
  }

  protected function filterDisabledAnswerers(array $options) {
    $storage = $this->entityManager->getStorage('taxonomy_term');

    foreach ($options as $id => $item) {
      if (is_array($item)) {
        $options[$id] = $this->filterDisabledAnswerers($item);

      } else if (is_integer($id)) {
        $term = $storage->load($id);
        if (!$term->get('field_asklib_active')->value) {
          // var_dump('drop ' . $item);
          unset($options[$id]);
        }
      }
    }

    return $options;
  }

  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['delete']['#access'] = FALSE;
    return $actions;
  }

  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->setNotificationFlags(QuestionInterface::NOTIFY_SUBSCRIBERS);
    $form_state->setRedirect('view.asklib_index.page_1');
    drupal_set_message(t('Target library updated.'));
    
    return parent::save($form, $form_state);
  }
}
