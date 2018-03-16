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
        $type = $this->entityManager()->getDefinition('taxonomy_term');
        $storage = $this->entityManager()->getStorage('taxonomy_term');
        return new MailGroupListBuilder($type, $storage);
    }

    private function getUserListBuilder()
    {
        $type = $this->entityManager()->getDefinition('user');
        $storage = $this->entityManager()->getStorage('user');
        return new MailUserListBuilder($type, $storage);
    }
}
