<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

interface ShipmentPackagerInterface {

  /**
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method
   */
  public function packageItems(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method);

}