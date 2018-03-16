<?php

namespace Drupal\asklib\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\asklib\MailGroupListBuilder;
use Drupal\asklib\MailUserListBuilder;

class MailGroupController extends ControllerBase
{
    public function groups()
    {
        $builder = $this->getGroupListBuilder();
        return $builder->render();
    }

    public function users()
    {
        $builder = $this->getUserListBuilder();
        return $builder->render();
    }

    public function showGroup($taxonomy_term)
    {
        return "OK!";
    }

    private function getGroupListBuilder()
    {
        $container = \Drupal::getContainer();
        $type = $this->entityManager()->getDefinition('taxonomy_term');
        return MailGroupListBuilder::createInstance($container, $type);
    }

    private function getUserListBuilder()
    {
        $container = \Drupal::getContainer();
        $type = $this->entityManager()->getDefinition('user');
        return MailUserListBuilder::createInstance($container, $type);
    }
}
