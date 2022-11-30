/**
 *
 * RefreshButton is dead simply, click method should just reload the page for us.
 *
 */

var RefreshButton = my.Class(Button, {

  constructor: function(options) {

    options.name = 'Refresh';
    options.bubble = "Reload this GUI";
    options.id = Button.MOD_REFRESH;

    options.onClick = function (event) {

      echo("[RefreshButton] Reloading page...");

      location.reload()
    };

    RefreshButton.Super.call(this, options);
  },

  /**
   *
   * disable() - force the button to be disabled.
   *
   * @return boolean always true.
   *
   */

  disable: function() {
    RefreshButton.Super.prototype.disable.call(this);
  },

  /**
   *
   * enable() - force the button to be enabled.
   *
   * @return boolean always true.
   *
   */

  enable: function() {
    RefreshButton.Super.prototype.enable.call(this);
  }

});