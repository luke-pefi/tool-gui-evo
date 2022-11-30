/**
 *
 * Pefi button controls the right side info/support popup
 *
 */

var PefiButton = my.Class(Button, {

  constructor: function(options) {

    options.name    = 'PEFI';
    options.bubble  = "Get Support!";
    options.id      = Button.MOD_PEFI;
    options.panelid = '#pefi-panel';

    options.onClick = function(event) {

      echo("[PefiButton] toggling panel...");

      PefiButton.Super.prototype.pop.call(event.data);
    };

    PefiButton.Super.call(this, options);

    /* its a static panel, we only need to render it once at construction time */

    this.render.call(this);

  },

  /**
   *
   * render() - fill in the info/support panel
   *
   */

  render: function() {

    echo("[FlashButton] rendering...");

    /* its static! */

    /* all done */

    return true;
  }

});