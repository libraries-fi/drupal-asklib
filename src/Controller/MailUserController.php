<?php

namespace Drupal\asklib\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\asklib\MailGroupListBuilder;
use Drupal\asklib\MailUserListBuilder;

class MailUserController extends ControllerBase
{
    public function showUser($user)
    {
        return [];
    }

    private function getGroupListBuilder()
    {
        $type = \Drupal::service('entity_type.manager')->getDefinition('taxonomy_term');
        $storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
        return new MailGroupListBuilder($type, $storage);
    }

    private function getUserListBuilder()
    {
        $type = \Drupal::service('entity_type.manager')->getDefinition('user');
        $storage = \Drupal::service('entity_type.manager')->getStorage('user');
        return new MailUserListBuilder($type, $storage);
    }
}
