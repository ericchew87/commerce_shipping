<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

/**
 * Provides the all_in_one shipment packager.
 *
 * @CommerceShipmentPackager(
 *   id = "default_package_all_in_one",
 *   label = @Translation("Default Packaging - All In One"),
 *   description = @Translation("Places all shipment items into the default package specified by the shipping method."),
 * )
 */
class DefaultAllInOneShipmentPackager extends ShipmentPackagerBase {

  public function packageItems(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method) {
    $shipment->setPackageType($shipping_method->getDefaultPackageType());
    /** @var \Drupal\commerce_shipping\ShipmentItem[] $items */
    $items = $shipment->getData('unpackaged_items');
    /** @var \Drupal\commerce_shipping\Entity\PackageInterface $package */
    $package = $this->entityTypeManager->getStorage('commerce_package')->create([
      'items' => $items,
      'title' => $shipment->getPackageType()->getLabel(),
      'package_type' => $shipping_method->getDefaultPackageType()->getId(),
      'declared_value' => $shipment->getTotalDeclaredValue(),
      'weight' => $shipment->getWeight(),
    ]);
    $shipment->addPackage($package);
    $shipment->setData('unpackaged_items', []);
  }

}