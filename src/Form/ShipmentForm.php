<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the shipment add/edit form.
 */
class ShipmentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $url = Url::fromRoute('commerce_shipping.shipment_builder', ['order' => $shipment->getOrderId(), 'shipment' => $shipment->id()]);
    $url->mergeOptions(['query' => ['destination' => \Drupal::service('path.current')->getPath()]]);
    $form['shipment_builder'] = [
      '#type' => 'link',
      '#url' => $url,
      '#title' => t('Build Packages'),
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

    return $form;
  }

}