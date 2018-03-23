<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager\ShipmentPackagerBase;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

/**
 * Provides the manual_packager shipment packager.
 *
 * @CommerceShipmentPackager(
 *   id = "manual_packager",
 *   label = @Translation("Manual Packaging"),
 *   description = @Translation("Uses the Manual Packaging from Product Variations to place items into the specified packaging."),
 * )
 */
class ManualShipmentPackager extends ShipmentPackagerBase {

  public function packageItems(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method) {
    /** @var \Drupal\commerce_shipping\ShipmentItem[] $items */
    $items = $shipment->getData('unpackaged_items');
    foreach ($items as $key => $item) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($item->getOrderItemId());
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity->hasField('packaging') && $purchased_entity->hasField('weight')) {
        $purchased_entity_weight = $purchased_entity->get('weight')->first()->toMeasurement();
        $packaging_options = $purchased_entity->get('packaging')->getValue();
        $item_qty = $item->getQuantity();
        // The packaging option with the highest maximum is always used first.
        foreach ($packaging_options as $packaging_option) {
          /** @var \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface $package_type */
          $package_type = $this->packageTypeManager->createInstance($packaging_option['package_type']);
          while ($item_qty >= $packaging_option['max']) {
            /** @var \Drupal\commerce_shipping\Entity\PackageInterface $package */
            $package = $this->entityTypeManager->getStorage('commerce_package')->create([
              'items' => [$item],
              'title' => $package_type->getLabel(),
              'package_type' => $package_type->getId(),
              'declared_value' => $purchased_entity->getPrice()->multiply($packaging_option['max']),
              'weight' => $purchased_entity_weight->multiply($packaging_option['max']),
            ]);
            $shipment->addPackage($package);
            $item_qty -= $packaging_option['max'];
            // @todo: ShipmentItem are immutable, need to delete current item and add new one with correct quantity.
          }
          if ($item_qty > 0 && $item_qty >= $packaging_option['min'] && $item_qty <= $packaging_option['max']) {
            $package = $this->entityTypeManager->getStorage('commerce_package')->create([
              'items' => [$item],
              'title' => $package_type->getLabel(),
              'package_type' => $package_type->getId(),
              'declared_value' => $purchased_entity->getPrice()->multiply((string)$item_qty),
              'weight' => $purchased_entity_weight->multiply((string)$item_qty),
            ]);
            $shipment->addPackage($package);
            unset($items[$key]);
            break;
          }
        }
      }
    }
    $shipment->setData('unpackaged_items', $items);
  }

}