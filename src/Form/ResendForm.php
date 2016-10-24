<?php
/**
 * MIT License
 *
 * Copyright (c) 2016 Ricardo Velhote
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
namespace Drupal\contact_storage_resend\Form;

use Drupal\contact\Entity\Message;
use Drupal\contact\MailHandler;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a message deletion confirmation form.
 */
class ResendForm extends ConfirmFormBase {

  /**
   * An object representing the message we want to delete.
   * @var Message
   */
  protected $message;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The currently logged-in user.
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailHandler;

  /**
   * ResendForm constructor.
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param \Drupal\contact\MailHandler $mail_handler
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $account, MailHandler $mail_handler) {
    $this->storage = $entity_type_manager->getStorage('contact_message');
    $this->account = $account;
    $this->mailHandler = $mail_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('contact.mail_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_resend_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to resend this message?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.contact_message.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Resend Message');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $contact_message = null) {
    $this->message = $this->storage->load($contact_message);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if(is_null($this->message)) {
      $this->logger('contact')->error('Failed to resend message because it no longer exists.');
      $form_state->setRedirect('entity.contact_message.collection');
      return;
    }

    $recipients = implode(', ', $this->message->getContactForm()->getRecipients());
    $this->mailHandler->sendMailMessages($this->message, $this->account);

    drupal_set_message($this->t('Message send via email to the following recepients: @recipients. Please check the log for any errors.', ['@recipients' => $recipients]));
    $this->logger('contact')->debug('Message (#@id) was resent', ['@id' => $this->message->getOriginalId()]);

    $form_state->setRedirect('entity.contact_message.collection');
  }
}
