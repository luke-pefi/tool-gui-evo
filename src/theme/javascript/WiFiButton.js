/**
 *
 * WiFi button controls the main panel for joining WiFi networks and sharing
 * our WiFi status around the application.
 *
 */

var WiFiButton = my.Class(Button, {

  constructor: function(options) {

    options.name    = 'WiFi';
    options.bubble  = "View WiFI status or join a network";
    options.id      = Button.MOD_WIFI;
    options.panelid = '#wifi-panel';

    options.onClick = function(event) {

      echo("[WiFiButton] toggling panel...");

      WiFiButton.Super.prototype.pop.call(event.data);
    };

    WiFiButton.Super.call(this, options);

    /*
     * start out assuming wifi is disconnected, we will confirm the status
     * once we start the wifi controller.
     *
     */

    EventBus.dispatch('wifi-disconnected', this);

    /*
     * this button starts out disabled, we don't know the WiFi state yet either way.
     *
     */

    echo("[WiFiButton] disabling ...");

    WiFiButton.Super.prototype.disable.call(this);

    EventBus.dispatch('wifi-actions-disabled', this);

    /* wait for status updates... */

    echo("[WiFiButton] waiting...");

    EventBus.addEventListener('wifi-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      /*
       * when the WiFi status changes, we enable this button if its not already enabled, so the
       * user cna then get to the WiFi panel and join a network (or leave).
       *
       */

      WiFiButton.Super.prototype.enable.call(this);

      /* if we're already connected, then we can set the color of the button ... */

      if(!status.connected) {

        $(Button.MOD_WIFI).css('background-color', 'red');

        return ;
      }

      var color = status.dBm.color;

      $(Button.MOD_WIFI).css('background-color', color);

      EventBus.dispatch('wifi-actions-enabled', this);

    }, this);

    /* set up the WiFi controller and listen for updates to the WiFI status... */

    echo("[WiFiButton] making controller...");

    App.WiFiController = new WiFiController({
      id: options.panelid
    });

  }

});