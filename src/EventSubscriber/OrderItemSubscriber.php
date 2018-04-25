<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderItemSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OrderEvents::ORDER_ITEM_UPDATE => ['onUpdate']
    ];
  }

  /**
   * Flags shipments for repackaging if order item quantity changes in the cart.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onUpdate(OrderItemEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order_item = $event->getOrderItem();
    // Repackage shipments if cart order item quantity changes.
    if ($order = $order_item->getOrder() && $order_item->getOrder()->get('cart')->value == '1') {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
      $shipments = $order->get('shipments')->referencedEntities();
      if (count($shipments) > 0 && isset($order_item->original) && $order_item->getQuantity() != $order_item->original->getQuantity()) {
        foreach ($shipments as $shipment) {
          if ($shipment->hasPackages()) {
            $shipment->setData('needs_repackage', TRUE);
          }
        }
      }
    }
  }
}
