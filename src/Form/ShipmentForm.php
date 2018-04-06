<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce\EntityHelper;
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
    $order = $shipment->getOrder();
    $store = $order->getStore();

    $available_countries = [];
    foreach ($store->get('shipping_countries') as $country_item) {
      $available_countries[] = $country_item->value;
    }

    $form['shipping_profile'] = [
      '#type' => 'commerce_profile_select',
      '#default_value' => $shipment->getShippingProfile(),
      '#default_country' => $store->getAddress()->getCountryCode(),
      '#available_countries' => $available_countries,
    ];

    $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
    $form_display->removeComponent('shipping_profile');
    $form_display->buildForm($shipment, $form, $form_state);

    $form['#wrapper_id'] = 'shipment-entity-form-wrapper';
    $form['#prefix'] = '<div id="' . $form['#wrapper_id'] . '">';
    $form['#suffix'] = '</div>';

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

    $form['recalculate_shipping'] = [
      '#type' => 'button',
      '#value' => $this->t('Recalculate shipping'),
      '#recalculate' => TRUE,
      '#weight' => 1,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
      // The calculation process only needs a valid shipping profile.
      '#limit_validation_errors' => [],
    ];

    return $form;
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

    if (!$shipment->hasItems()) {
      $items = [];
      foreach ($order->getItems() as $order_item) {
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

        $quantity = $order_item->getQuantity();
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
      if ($shipment->getData('unpackaged_items') === NULL) {
        $shipment->setData('unpackaged_items', $items);
      }
    }
  }

  public function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $recalculate = !empty($triggering_element['#recalculate']);

    if ($recalculate) {
      $form_state->set('recalculate_shipping', TRUE);
      // The profile in form state needs to reflect the submitted values, since
      // it will be passed to the packers when the form is rebuilt.
      $form_state->set('shipping_profile', $form['shipping_profile']['#profile']);
    }

    $shipment = clone $this->entity;
    $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
    $form_display->removeComponent('shipping_profile');
    $form_display->removeComponent('title');
    $form_display->extractFormValues($shipment, $form, $form_state);
    $form_display->validateFormValues($shipment, $form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
//    parent::submitForm($form, $form_state);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
    $form_display->removeComponent('shipping_profile');
    $form_display->extractFormValues($shipment, $form, $form_state);
    $shipment->setShippingProfile($form['shipping_profile']['#profile']);
    $shipment->save();
    $order = $shipment->getOrder();
    $shipment_ids = EntityHelper::extractIds($order->get('shipments')->referencedEntities());

    if (!in_array($shipment->id(), $shipment_ids)) {
      $order->get('shipments')->appendItem($shipment);
      $order->save();
    }

    $form_state->setRedirect('entity.commerce_shipment.collection', ['commerce_order' => $order->id()]);
  }

}