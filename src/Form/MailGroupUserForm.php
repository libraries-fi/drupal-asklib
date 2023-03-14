<?php

namespace Drupal\asklib\Form;

use Drupal\asklib\UserMailGroupHelper;
use Drupal\user\UserDataInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;

class MailGroupUserForm extends ContentEntityForm {
  private $groupHelper;

  /**
   * Moved data to fields. 
   *
   * @deprecated
   */
  private $userData;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('user.data'),
      $container->get('asklib.user_mail_group_helper')
    );
  }

  public function __construct(EntityRepositoryInterface $entity_repository, UserDataInterface $config, UserMailGroupHelper $group_helper) {
    parent::__construct($entity_repository);
    $this->userData = $config;
    $this->groupHelper = $group_helper;
  }

  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['field_real_name']['widget'][0]['value']['#required'] = TRUE;

    $user_groups = $this->groupHelper->getGroupsForUser($this->entity->id());
    $groups = $this->sortGroups($this->groups(), $user_groups);
    $labels = array_map(fn($term) => $term->label(), $groups);

    if (empty($form['field_asklib_mail']['widget'][0]['value']['#default_value'])) {
      $form['field_asklib_mail']['widget'][0]['value']['#default_value'] = $this->entity->getEmail();
    }

    // FIXME: Remove once user
    if (empty($form['field_asklib_signature']['widget'][0]['value']['#default_value'])) {
      $form['field_asklib_signature']['widget'][0]['value']['#default_value'] = $this->getSetting('email.signature');
    }

    $label_suffix = array_map(fn($g) => $g->getName(), $user_groups);
    $label_suffix = implode(', ', $label_suffix);

    if ($label_suffix) {
      $label_suffix = sprintf(' (%s)', $label_suffix);
    }

    $form['mail_groups'] = [
      '#weight' => 100,
      '#type' => 'details',
      '#title' => $this->t('Email groups') . $label_suffix,
      '#open' => FALSE,

      'groups' => [
        '#type' => 'checkboxes',
        '#default_value' => array_keys($user_groups),
        '#options' => $labels,
      ]
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $gids = array_filter($form_state->getValue('groups'));
    $this->groupHelper->setGroupsForUser($this->entity->id(), $gids);
    // $this->setSetting('email.signature', $form_state->getValue('signature'));
  }

  private function groups() {
    $storage = $this->entityManager->getStorage('taxonomy_term');
    $tids = $storage->getQuery()
      ->sort('tid')
      ->condition('vid', ['asklib_libraries', 'asklib_municipalities'], 'in')
      ->execute();
    return $storage->loadMultiple($tids);
  }

  private function sortGroups(array $groups, array $selected) {
    uasort($groups, function($a, $b) use($selected) {
      $sel_a = isset($selected[$a->id()]);
      $sel_b = isset($selected[$b->id()]);
      if ($sel_a != $sel_b) {
        return $sel_b - $sel_a;
      } else {
        return strcasecmp($a->label(), $b->label());
      }
    });
    return $groups;
  }

  private function getSetting($key, $default = null) {
    $value = $this->userData->get('asklib', $this->entity->id(), $key);
    return is_null($value) ? $default : $value;
  }

  private function setSetting($key, $value) {
    $this->userData->set('asklib', $this->entity->id(), $key, $value);
  }
}
