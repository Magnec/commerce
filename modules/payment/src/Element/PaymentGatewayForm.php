<?php

namespace Drupal\commerce_payment\Element;

use Drupal\commerce\Element\CommerceElementBase;
use Drupal\commerce_payment\Entity\EntityWithPaymentGatewayInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element for embedding the payment gateway forms.
 *
 * Usage example:
 * @code
 * $form['payment_method'] = [
 *   '#type' => 'commerce_payment_gateway_form',
 *   '#operation' => 'add-payment-method',
 *   // A payment or payment method entity, depending on the operation.
 *   // On submit, the payment method will be created remotely, and the
 *   // entity updated, for access via $form_state->getValue('payment_method')
 *   '#default_value' => $payment_method,
 * ];
 * @endcode
 *
 * @RenderElement("commerce_payment_gateway_form")
 */
class PaymentGatewayForm extends CommerceElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#operation' => '',
      // The entity operated on. Instance of EntityWithPaymentGatewayInterface.
      '#default_value' => NULL,
      '#process' => [
        [$class, 'attachElementSubmit'],
        [$class, 'processForm'],
      ],
      '#element_validate' => [
        [$class, 'validateElementSubmit'],
        [$class, 'validateForm'],
      ],
      '#commerce_element_submit' => [
        [$class, 'submitForm'],
      ],
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * Builds the payment gateway form.
   *
   * @param array $element
   *   The form element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the #operation or #default_value properties are empty, or
   *   when the #default_value property is not a valid entity.
   *
   * @return array
   *   The processed form element.
   */
  public static function processForm(array $element, FormStateInterface $form_state, array &$complete_form) {
    if (empty($element['#operation'])) {
      throw new \InvalidArgumentException('The commerce_payment_gateway_form element requires the #operation property.');
    }
    if (empty($element['#default_value'])) {
      throw new \InvalidArgumentException('The commerce_payment_gateway_form element requires the #default_value property.');
    }
    elseif (isset($element['#default_value']) && !($element['#default_value'] instanceof EntityWithPaymentGatewayInterface)) {
      throw new \InvalidArgumentException('The commerce_payment_gateway_form #default_value property must be a payment or a payment method entity.');
    }
    $plugin_form = static::createPluginForm($element);
    $element = $plugin_form->buildConfigurationForm($element, $form_state);
    // Allow the plugin form to override the page title.
    if (isset($element['#page_title'])) {
      $complete_form['#title'] = $element['#page_title'];
    }

    return $element;
  }

  /**
   * Validates the payment gateway form.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateForm(array &$element, FormStateInterface $form_state) {
    $plugin_form = self::createPluginForm($element);
    $plugin_form->validateConfigurationForm($element, $form_state);
  }

  /**
   * Submits the payment gateway form.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitForm(array &$element, FormStateInterface $form_state) {
    $plugin_form = self::createPluginForm($element);

    try {
      $plugin_form->submitConfigurationForm($element, $form_state);
      $form_state->setValueForElement($element, $plugin_form->getEntity());
    }
    catch (PaymentGatewayException $e) {
      $error_element = $plugin_form->getErrorElement($element, $form_state);
      $form_state->setError($error_element, $e->getMessage());
    }
  }

  /**
   * Creates an instance of the plugin form.
   *
   * @param array $element
   *   The form element.
   *
   * @return \Drupal\commerce_payment\PluginForm\PaymentGatewayFormInterface
   *   The plugin form.
   */
  public static function createPluginForm(array $element) {
    /** @var \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_factory */
    $plugin_form_factory = \Drupal::service('plugin_form.factory');
    /** @var \Drupal\commerce_payment\Entity\EntityWithPaymentGatewayInterface $entity */
    $entity = $element['#default_value'];
    $plugin = $entity->getPaymentGateway()->getPlugin();
    /** @var \Drupal\commerce_payment\PluginForm\PaymentGatewayFormInterface $plugin_form */
    $plugin_form = $plugin_form_factory->createInstance($plugin, $element['#operation']);
    $plugin_form->setEntity($entity);

    return $plugin_form;
  }

}
