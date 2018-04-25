<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce\EntityHelper;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;

/**
 * Defines the shipment add/edit form.
 */
class ShipmentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $this->prepareShipment($form, $form_state);

    $form['#wrapper_id'] = 'shipment-entity-form-wrapper';
    $form['#prefix'] = '<div id="' . $form['#wrapper_id'] . '">';
    $form['#suffix'] = '</div>';

    if ($form_state->has('step') && $form_state->get('step') == 'shipping_method') {
      return self::buildShippingMethodForm($form, $form_state);
    }

    $form_state->set('step', 'shipping_information');
    $form_display =$this->getFormDisplay($form_state);
    $form_display->removeComponent('shipping_method');
    $form_display->buildForm($shipment, $form, $form_state);

    if (!empty($form['shipping_profile'])) {
      $form['shipping_profile']['#type'] = 'fieldset';
      $form['shipping_profile']['#title'] = $this->t('Shipping Profile');
    }

    $form += self::buildShipmentItemForm($form, $form_state);

    return $form;
  }

  protected function updateOrderItemUsageArray(ShipmentInterface $shipment, array &$usage) {
    foreach ($shipment->getItems() as $shipment_item) {
      if (!empty($usage[$shipment_item->getOrderItemId()])) {
        $usage[$shipment_item->getOrderItemId()] += $shipment_item->getQuantity();
      } else {
        $usage[$shipment_item->getOrderItemId()] = $shipment_item->getQuantity();
      }
    }
  }

  public function buildShipmentItemForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $order = $shipment->getOrder();

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $order_shipments */
    $order_shipments = $order->get('shipments')->referencedEntities();

    $this_order_item_usage = [];
    $other_order_item_usage = [];

    foreach ($order_shipments as $order_shipment) {
      if ($order_shipment->id() == $shipment->id()) {
        $this->updateOrderItemUsageArray($order_shipment, $this_order_item_usage);
      } else {
        $this->updateOrderItemUsageArray($order_shipment, $other_order_item_usage);
      }
    }

    $form['shipment_item_builder'] = [
      '#type' => 'table',
      '#title' => $this->t('Shipment Items'),
      '#header' => [
        $this->t('Product'),
        $this->t('Quantity'),
      ],
      '#weight' => 99,
      '#empty' => $this->t('All items are already tied to other shipments.'),
    ];

    foreach ($order->getItems() as $order_item) {
      $quantity_used = (array_key_exists($order_item->id(), $this_order_item_usage)) ? $this_order_item_usage[$order_item->id()] : 0;
      $quantity_available = (int)$order_item->getQuantity();
      if (array_key_exists($order_item->id(), $other_order_item_usage)) {
        $quantity_available -= $other_order_item_usage[$order_item->id()];
      }

      $form['shipment_item_builder'][$order_item->id()]['product'] = [
        '#title' => $this->t('Product'),
        '#markup' => $order_item->getPurchasedEntity()->label(),
      ];
      $form['shipment_item_builder'][$order_item->id()]['quantity'] = [
          '#type' => 'number',
          '#title' => $this->t('Quantity'),
          '#title_display' => 'invisible',
          '#min' => 0,
          '#disabled' => ($quantity_available == 0 && $quantity_used == 0) ? TRUE : FALSE,
          '#max' => $quantity_available,
          '#size' => 4,
          '#step' => 1,
          '#default_value' => ($quantity_used != 0) ? $quantity_used : $quantity_available,
          '#suffix' => '<span>' . $this->t('Available:' . $quantity_available)  . '</span>',
      ];
    }

    return $form;
  }

  public function submitShippingInformationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $items = [];
    foreach ($form_state->getvalue('shipment_item_builder') as $order_item_id => $values) {
      $quantity = $values['quantity'];
      if ($quantity <= 0) {
        continue;
      }
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($order_item_id);
      $purchased_entity = $order_item->getPurchasedEntity();
      // Ship only shippable purchasable entity types.
      if (!$purchased_entity || !$purchased_entity->hasField('weight')) {
        continue;
      }
      // The weight will be empty if the shippable trait was added but the
      // existing entities were not updated.
      if ($purchased_entity->get('weight')->isEmpty()) {
        $purchased_entity->set('weight', new Weight(0, WeightUnit::GRAM));
      }

      /** @var \Drupal\physical\Weight $weight */
      $weight = $purchased_entity->get('weight')->first()->toMeasurement();
      $items[] = new ShipmentItem([
        'order_item_id' => $order_item->id(),
        'title' => $order_item->getTitle(),
        'quantity' => $quantity,
        'weight' => $weight->multiply($quantity),
        'declared_value' => $order_item->getUnitPrice()->multiply($quantity),
      ]);
    }
    $shipment->setItems($items);
    $shipment->resetPackagingData();
    $this->entity = $this->buildEntity($form, $form_state);
    $form_state->set('step', 'shipping_method');
    $form_state->setRebuild(TRUE);
  }

  public function buildShippingMethodForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
    /** @var \Drupal\commerce_shipping\Plugin\Field\FieldWidget\ShippingRateWidget $widget */
    if ($widget = $form_display->getRenderer('shipping_method')) {
      $form['#parents'] = [];
      $items = $shipment->get('shipping_method');
      $items->filterEmptyItems();
      $form['shipping_method'] = $widget->form($items, $form, $form_state);

      if (!$shipment->isNew()) {
        $url = Url::fromRoute('commerce_shipping.shipment_builder', ['order' => $shipment->getOrderId(), 'shipment' => $shipment->id()]);
        $url->mergeOptions(['query' => ['destination' => \Drupal::service('path.current')->getPath()]]);
        $form['shipment_builder'] = [
          '#type' => 'link',
          '#url' => $url,
          '#title' => t('Edit Packages'),
          '#weight' => -99,
          '#attributes' => [
            'class' => [
              'use-ajax',
              'button',
            ],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode([
              'width' => 1200,
            ]),
          ],
        ];
        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      }
    }

    return $form;
  }

  /**
   * Returns an array of supported actions for the current entity form.
   *
   * @todo Consider introducing a 'preview' action here, since it is used by
   *   many entity types.
   */
  protected function actions(array $form, FormStateInterface $form_state) {

    if ($form_state->has('step') && $form_state->get('step') == 'shipping_information') {
      $actions['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save and Continue'),
        '#submit' => ['::submitShippingInformationForm'],
      ];
    } else {
      $actions = parent::actions($form, $form_state);
    }

    return $actions;
  }

  protected function prepareShipment(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $order_id = $shipment->get('order_id')->target_id;

    if (!$order_id) {
      $order_id = $this->getRouteMatch()->getParameter('commerce_order');
      $shipment->set('order_id', $order_id);
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);
    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = $form_state->get('shipping_profile');

    if (!$shipping_profile) {
      $shipping_profile = $shipment->getShippingProfile();
    }
    if (!$shipping_profile) {
      $shipping_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => 'customer',
        'uid' => $order->getCustomerId(),
      ]);
    }
    $shipment->setShippingProfile($shipping_profile);

  }

  public function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
    $form_display->extractFormValues($shipment, $form, $form_state);
    $order = $shipment->getOrder();
    $shipment_ids = EntityHelper::extractIds($order->get('shipments')->referencedEntities());

    if (!in_array($shipment->id(), $shipment_ids)) {
      $order->get('shipments')->appendItem($shipment);
      $order->save();
    }

    $form_state->setRedirect('entity.commerce_shipment.collection', ['commerce_order' => $order->id()]);
  }

}