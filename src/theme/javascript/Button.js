/**
 *
 * Button is the base class for the controllers on each major button in
 * the bottom navigation bar.
 *
 */

var Button = my.Class({

  STATIC: {
    MOD_WIFI:     '#btn-wifi',
    MOD_LOGIN:    '#btn-login',
    MOD_CANBUS:   '#btn-canbus',
    MOD_ACTIVATE: '#btn-activate',
    MOD_FLASH:    '#btn-flash',
    MOD_REFRESH:  '#btn-refresh',
    MOD_PEFI:     '#btn-pefi'
  },

  /* standard constructor */

  constructor: function(options) {

    echo("[Button] constructing...");

    this.enabled = true;

    this.options = {

      name:        'Button',

      enableColor: '#fff',

      bubble:      '',

      id:          false,

      panelid:     false,

      onClick:     function(event) {

        echo("[Button] click!");

        var instance = event.data;

        if(!instance.options.id) {
          return true;
        }

        /*
         * if we have an element id, then generate a click event for it that
         * the navigation elements can listen/react to.
         *
         */

        EventBus.dispatch(instance.options.id + '-clicked', instance);

        return true;
      }

    };

    /* merge in options */

    $.extend(this.options, options);

    /* add basic hover help */

    $(this.options.id).prop('title', this.options.bubble);

    /* is it enabled yet? */

    if(!this.enabled) {

      /* its not enabled */

      return ;
    }

    /* default click behavior for buttons */

    if(this.options.id) {

      $(this.options.id).unbind('click');

      $(this.options.id).click(this, function (event) {

        var instance = event.data;

        instance.options.onClick(event);

      });
    }
  },

  /**
   *
   * disable() - force the button to be disabled.
   *
   * @return boolean always true.
   *
   */

  disable: function() {

    if(!this.options.id) {

      /* no button element */

      return true;
    }

    this.enabled = false;

    $(this.options.id).css('background-color', '#515151');

    return true;
  },

  /**
   *
   * enable() - force the button to be enabled.
   *
   * @return boolean always true.
   *
   */

  enable: function() {

    if(!this.options.id) {

      /* no button element */

      return true;
    }

    this.enabled = true;

    $(this.options.id).css('background-color', this.options.enableColor);

    return true;
  },

  /**
   *
   * isEnabled() - test if the button is enabled or not.
   *
   * @returns boolean exactly false if not enabled
   *
   */

  isEnabled: function() {

    return  this.enabled;
  },

  pop: function() {

    if(!this.options.panelid) {

      /* this button doesn't have a panel element */

      return true;
    }

    /* is it enabled? */

    if(!this.enabled) {

      echo("[Button] not popping (disabled): " + this.options.id);

      return true;
    }

    /* is the panel up? */

    var panel     = $(this.options.panelid);
    var maxHeight = parseInt($(panel).css('max-height'));

    if(maxHeight > 0) {

      /* yes just hide it. */

      var panel  = $(this.options.panelid);

      $(panel).animate({
          'max-height': '0px'
        },
        400
      );

      return ;
    }

    /* nope, pop it */

    this.popPanel.call(this);

    return true;
  },

  popPanel: function() {

    if(!this.options.panelid) {

      /* this button doesn't have a panel element */

      return true;
    }

    /* close all panels */

    $('.panel').css('max-height', '0px');

    /* animate the max-height, so the panel appears to slide out from the nav bar */

    var panel  = $(this.options.panelid);

    $(panel).animate({
        'max-height': '380px'
      },
      400
    );





  }


});