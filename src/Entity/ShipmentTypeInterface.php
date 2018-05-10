<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityInterface;

/**
 * Defines the interface for shipment types.
 */
interface ShipmentTypeInterface extends CommerceBundleEntityInterface {

  /**
   * Gets the shipment type's matching shipment package type ID.
   *
   * @return string
   *   The shipment package type ID.
   */
  public function getShipmentPackageTypeId();

  /**
   * Sets the shipment type's matching shipment package type ID.
   *
   * @param string $shipment_package_type_id
   *   The shipment package type ID.
   *
   * @return $this
   */
  public function setShipmentPackageTypeId($shipment_package_type_id);
}
