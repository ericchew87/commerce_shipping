(($, { ajax, behaviors }) => {
  behaviors.commerceShippingShipmentBuilder = {
    attach(context) {
      $(context).find('.shipment-builder__area').sortable({
        items: '> .draggable',
        connectWith: '.shipment-builder__area',

        /**
         * Updates the layout with the new position of the block.
         *
         * @param {jQuery.Event} event
         *   The jQuery Event object.
         * @param {Object} ui
         *   An object containing information about the item being sorted.
         */
        update(event, ui) {
          // url/order_id/shipment_id/item_id/package_from/packaage_to
          // Check if the region from the event and region for the item match.
          const itemRegion = ui.item.closest('.shipment-builder__area');
          if (event.target === itemRegion[0]) {
            const shipmentItemArea = 'shipment-item-area';
            // If item dragged from package, provide package delta, otherwise item was dragged from shipment-item-area.
            const packageFrom = (ui.sender.closest('[data-package-id]').data('package-id') !== undefined) ? ui.sender.closest('[data-package-id]').data('package-id') : shipmentItemArea;
            // If item dragged to package, provide package delta, otherwise item was dragged to shipment-item-area.
            const packageTo = (ui.item.closest('[data-package-id]').data('package-id') !== undefined) ? ui.item.closest('[data-package-id]').data('package-id') : shipmentItemArea;

            ajax({
              url: [
                ui.item.closest('[data-layout-update-url]').data('layout-update-url'),
                ui.item.data('shipment-item-id'),
                packageFrom,
                packageTo,
              ]
                  .filter(element => element !== undefined)
                  .join('/'),
            }).execute();
          }
        },
      });
    },
  };
})(jQuery, Drupal);