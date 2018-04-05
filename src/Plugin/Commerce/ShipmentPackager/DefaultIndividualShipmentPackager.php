<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

/**
 * Provides the default_package_individual shipment packager.
 *
 * @CommerceShipmentPackager(
 *   id = "default_package_individual",
 *   label = @Translation("Default Packaging - Individual"),
 *   description = @Translation("Placed each shipment item into an individual package based on the default package specified by the shipping method."),
 * )
 */
class DefaultIndividualShipmentPackager extends ShipmentPackagerBase {

  /**
   * {@inheritdoc}
   */
  public function packageItems(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method) {
    $shipment->setPackageType($shipping_method->getDefaultPackageType());
    /** @var \Drupal\commerce_shipping\ShipmentItem[] $items */
    $items = $shipment->getData('unpackaged_items');
    foreach ($items as $item) {
      // @todo: ShipmentItem are immutable, need to delete current item and add new one with correct quantity.
      for ($i = 0; $i < $item->getQuantity(); $i++) {
        /** @var \Drupal\commerce_shipping\Entity\PackageInterface $package */
        $package = $this->entityTypeManager->getStorage('commerce_package')->create([
          'items' => [$item],
          'title' => $shipment->getPackageType()->getLabel() . '-' . $i,
          'package_type' => $shipping_method->getDefaultPackageType()->getId(),
          'declared_value' => $item->getDeclaredValue()->divide($item->getQuantity()),
          'weight' => $item->getWeight()->divide($item->getQuantity()),
        ]);
        $shipment->addPackage($package);
      }
    }
    $shipment->setData('unpackaged_items', []);
  }

}