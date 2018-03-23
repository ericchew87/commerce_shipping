<?php

namespace Drupal\commerce_shipping\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the shipment packager plugin annotation object.
 *
 * Plugin namespace: Plugin\Commerce\ShipmentPackager.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class CommerceShipmentPackager extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The shipment packager label.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The shipment packager description.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description = '';

}
