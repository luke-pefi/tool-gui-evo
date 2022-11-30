/**
 *
 * CANBus button controls the main panel for connecting with the vehicle CAN Bus (RS-232)
 *
 */

var CANBusButton = my.Class(Button, {

  constructor: function(options) {

    options.name    = 'CANBus';
    options.bubble  = "View CAN Bus/ECU status";
    options.id      = Button.MOD_CANBUS;
    options.panelid = '#canbus-panel';

    options.onClick = function(event) {

      echo("[CANBusButton] toggling panel...");

      CANBusButton.Super.prototype.pop.call(event.data);
    };

    CANBusButton.Super.call(this, options);

    /*
     * start out assuming CAN Bus is disconnected, we will confirm the status
     * once we start the CAN Bus controller.
     *
     */

    EventBus.dispatch('canbus-disconnected', this);

    /*
     * this button starts out disabled, we don't know the CAN Bus state yet either way.
     *
     */

    echo("[CANBusButton] disabling ...");

    CANBusButton.Super.prototype.disable.call(this);

    EventBus.dispatch('canbus-actions-disabled', this);

    /* wait for status updates... */

    echo("[CANBusButton] waiting...");

    EventBus.addEventListener('canbus-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      /*
       * when the WiFi status changes, we enable this button if its not already enabled, so the
       * user cna then get to the WiFi panel and join a network (or leave).
       *
       */


      CANBusButton.Super.prototype.enable.call(this);

      /* if we're already connected, then we can set the color of the button ... */

      if(!status.connected) {

        echo("[CANBusButton] disabling... ");

        $(Button.MOD_CANBUS).css('background-color', 'red');

        EventBus.dispatch('canbus-disconnected', this);

        return ;
      }

      echo("[CANBusButton] enabling... ");

      $(Button.MOD_CANBUS).css('background-color', '#fff');

      EventBus.dispatch('canbus-actions-enabled', this);

    }, this);

    /* set up the WiFi controller and listen for updates to the WiFI status... */

    echo("[CANBusButton] making controller...");

    App.CANBusController = new CANBusController({
      id: options.panelid
    });

  },

  /**
   *
   * disable() - force the button to be disabled.
   *
   * @return boolean always true.
   *
   */

  disable: function() {
    CANBusButton.Super.prototype.disable.call(this);
  },

  /**
   *
   * enable() - force the button to be enabled.
   *
   * @return boolean always true.
   *
   */

  enable: function() {
    CANBusButton.Super.prototype.enable.call(this);
  }

});