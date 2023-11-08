<?php

namespace Drupal\asklib\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserDataInterface;

abstract class EmailActionBase extends ActionBase implements ContainerFactoryPluginInterface {
  protected $mailer;
  protected $config;
  protected $userdata;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('user.data')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MailManagerInterface $mailer, ConfigFactoryInterface $config, UserDataInterface $user_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->mailer = $mailer;
    $this->config = $config;
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

  protected function mail($mail_id, $recipients, $langcode, $mail, $reply_to = null) {

    if (is_null($reply_to)) {
      $reply_to = $this->getGenericSenderAddress();
    }

    $mail = $this->mailer->mail('asklib', $mail_id, $recipients, $langcode, $mail, $reply_to);

    if ($mail['result']) {
    
      $log_message = <<<EOT
Mail sent from asklib with following data:
module: @module,
message_id: @message_id,
email: @email,
langcode: @langcode,
subject: @subject,
body: @body,
user: @user
EOT;
    
      \Drupal::logger('asklib')
        ->notice($log_message, [
          '@module' => $mail['module'],
          '@message_id' => $mail['key'],
          '@email' => $mail['to'],
          '@langcode' => $mail['langcode'],
          '@subject' => $mail['subject'],
          '@body' => $mail['body'],
          '@user' => \Drupal::currentUser()->id(),
        ]);

      
    }
  }

  protected function getGenericSenderAddress() {
    $config = $this->config->get('asklib.settings');
    $site_config = $this->config->get('system.site');
    $name = $config->get('reply.name');
    $email = $config->get('reply.address') ?: $site_config->get('mail');
    return ['name' => $name, 'email' => $email];
  }
}
