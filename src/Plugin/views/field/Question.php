<?php

namespace Drupal\asklib\Plugin\views\field;

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;

class Question extends FieldPluginBase
{
    public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = null)
    {
        parent::init($view, $display, $options);
    }
}
