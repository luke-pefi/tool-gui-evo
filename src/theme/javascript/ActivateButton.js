/**
 *
 * Activate button controls the panel for activating licenses with the VIN # from the
 * attached vehicle (implicitly its ECU).
 *
 */

var ActivateButton = my.Class(Button, {

  constructor: function(options) {

    options.name    = 'Activate';
    options.bubble  = "Activate licenses for your VIN #";
    options.id      = Button.MOD_ACTIVATE;
    options.panelid = '#activate-panel';

    options.onClick = function(event) {

      echo("[ActivateButton] toggling panel...");

      ActivateButton.Super.prototype.pop.call(event.data);
    };

    this.licenses      = [];

    this.lastActivated = false;

    this.login         = {
      connected: false
    };

    /*
     * if we aren't connected to an ECU, then we can't Activate or do any other operation that requires a
     * a VIN #...since we obviously don't have a VIN #.  But...we should still allow other operations,
     * such as viewing/managing licenses...if the user is at least logged in with a valid userid.
     *
     */

    this.canbus        = false;

    ActivateButton.Super.call(this, options);

    /*
     * this button starts out disabled, we enable when the user is logged in.  We can only
     * go and fetch the licenses when we can login to the main site.
     *
     */

    echo("[ActivateButton] disabling ...");

    ActivateButton.Super.prototype.disable.call(this);

    EventBus.dispatch('activate-actions-disabled', this);

    /* wait for status updates... */

    echo("[ActivateButton] waiting on login ...");

    EventBus.addEventListener('login-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      this.login     = status;

      /*
       * if the user is at least logged in, then they can use this panel to view licenses.  But to do
       * things like activate licenses, you have to be connected to an ECU.
       *
       */

      if(this.login.connected) {

        echo("[ActivateButton] enabling (login)... ");

        ActivateButton.Super.prototype.enable.call(this);

        $(Button.MOD_ACTIVATE).css('background-color', '#fff');

        EventBus.dispatch('activate-actions-enabled', this);

        /* update the panel with the current known licenses */

        this.update.call(this);

      } else {

        echo("[ActivateButton] disabling... ");

        ActivateButton.Super.prototype.disable.call(this);

        this.licenses = [];
        this.render.call(this);
      }

    }, this);

    echo("[ActivateButton] waiting on can bus ...");

    EventBus.addEventListener('canbus-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      /*
       * just update the canbus status; we use it to decide which actions are available within the panel.  The panel
       * is always active, as long as they are at least logged in.
       *
       */

      echo("[ActivateButton] can bus status changed, rendering...");

      this.canbus    = status;

      this.render.call(this);

    }, this);

  },

  /**
   *
   * update() - fetch the summary of licenses that we can activate...
   *
   */

  update: function() {

    echo("[ActivateButton] updating...");

    var instance = this;
    lock_page();

    $.ajax({

      url:      '/rest/licenses/summary',

      method:   'POST',

      dataType: 'json',

      data: {
        userid:   App.LoginController.status.userid,
        password: App.LoginController.status.password
      },

      error: function (jqXHR, textStatus, errorThrown) {

        unlock_page();

        echo('[ActivateButton] ERROR problem fetching info [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[ActivateButton] there was a problem fetching licenses summary: " + data.message);

          /* show an alert... */

          info_message("Problem fetching licenses: ", data.message);

          return false;
        }

        instance.licenses = data;

        /* get the initial rendering of the panel done */

        instance.render.call(instance);

        return true;

      }

    });

  },

  /**
   *
   * activate a license (convert it to a flash we can download and burn)
   *
   */

  activate: function(userid, password, pid, vin, pgmDate, shopCode, dashblob, version, checksum) {

    echo("[ActivateButton] activating " + userid + ":" + password + " | " + pid + " | " + vin + " | " + pgmDate + " | " + shopCode + " | " + dashblob + " | " + version + " | " + checksum);

    lock_page();

    var instance = this;

    $.ajax({

      url:      '/rest/licenses/activate',

      method:   'POST',

      dataType: 'json',

      data: {
        userid:      userid,
        password:    password,
        pid:         pid,
        vin:         vin,
        programdate: pgmDate,
        dashblob:    dashblob,
        version:     version,
        shopcode:    shopCode,
        checksum:    checksum
      },

      error: function (jqXHR, textStatus, errorThrown) {

        unlock_page();

        echo('[ActivateButton] ERROR problem activating [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[ActivateButton] there was a problem activating: " + data.message);

          /* show an alert... */

          info_message("Problem fetching licenses: ", data.message);

          return false;
        }

        instance.lastActivated = data;

        /* get the initial rendering of the panel done */

        instance.update.call(instance);

        return true;

      }

    });

  },

  /**
   *
   * scrollBack() - helper to reset the scroll bar to where it was before.
   *
   * @param scrollLock - the previous capture of the scroll bar
   *
   */

  scrollBack: function(scrollLock) {

    if (typeof scrollLock == 'undefined') {

      /* nothing to do, no scroll lock */

      return ;
    }

    var panel      = $('#activate-panel .panel-body ul');

    if (!$(panel).is('ul')) {

      /* no visible anyways */

      console.log("[ActivateButton] no scrollback; panel not visible.");
      return ;
    }

    if(scrollLock.pos >= scrollLock.max) {
      scrollLock.pos = scrollLock.max;
    }

    if(scrollLock.pos < 0) {
      scrollLock.pos = 0;
    }

    panel = panel[0];

    panel.scrollTop = scrollLock.pos;

    console.log("[ActivateButton] scrolled to: " + panel.scrollTop);
  },

  /**
   *
   * captureScrollPosition() - helper to grab the scroll info for the list of the panel,
   * so that on refresh, we save where we were, and then scroll the list back there after
   * refreshing the panel.
   *
   */

  captureScrollPosition: function () {

    var panel      = $('#activate-panel .panel-body ul');
    var result     = null;

    if (!$(panel).is('ul')) {

      result = {
        pos:     0,
        max:     0,
        percent: 0,
        sHeight: 0,
        sTop:    0,
        sMax:    0
      };

    } else {

      panel = panel[0];

      if(panel.clientHeight == 0) {

        /* not being displayed right now */

        result = {
          pos:     0,
          max:     0,
          percent: 0,
          sHeight: 0,
          sTop:    0,
          sMax:    0
        };

      } else {

        var pos        = panel.scrollTop;
        var maxPos     = panel.scrollHeight - panel.clientHeight;
        var posPercent = (pos / maxPos);

        result = {
          pos:     pos,
          max:     maxPos,
          percent: posPercent,
          sHeight: panel.scrollHeight,
          sTop:    panel.scrollTop,
          sMax:    panel.scrollTopMax
        };
      }
    }

    console.log("[ActivateButton] current scroll position: ", result);

    /* pass it back */

    return result;
  },

  /**
   *
   * render() - update the licenses panel
   *
   */

  render: function() {

    /* figure out the panel we are rendering to... */

    var body = $(this.options.panelid).find('.panel-body');
    var html = "";

    if (!$(body).is('div')) {
      echo("[ActivateButton] skipping, no panel body.");
      return true;
    }

    /*
     * capture the previous scroll position...so we jump back there after re-building the
     * list.
     *
     */

    var scrollLock = this.captureScrollPosition.call(this);

    /* build the panel */

    var html = html + "<ul class=\"licenses-list list-group\">";

    for(var i=0; i<this.licenses.length; i++) {

      var license = this.licenses[i];
      var attr    = "";

      attr = attr + " data-pid=\""    + license.pid + "\"";
      attr = attr + " data-status=\"" + license.status + "\"";
      attr = attr + " data-total=\""  + license.total + "\"";
      attr = attr + " data-name=\""  + license.pname + "\"";

      html = html + "<li " + attr + " class=\"list-group-item\">";

      /* the license is only flashable if there are some VALID ones left to flash */

      var usable = false;

      if((license.status == "VALID") && (parseInt(license.total) > 0)) {
        usable = true;
      }

      /* status // count  & name */

      html = html + "<div class=\"left-col\">";

      if(usable) {
        html = html + "<h5><span class=\"badge badge-success\">" + license.status + "</span></h5>";
      } else {
        html = html + "<h5><span class=\"badge badge-default\">" + license.status + "</span></h5>";
      }

      html = html + "<h5>" + license.total + "</h5>";

      html = html + "</div>";

      html = html + "<div class=\"mid-col\">";

      html = html + "<h5><span class=\"fw-semibold\">" + license.pname + "</span></h5>";

      html = html + "</div>";

      /* the action buttons if appropriate */

      html = html + "<div class=\"right-col\">";

      if(usable) {
        html = html + "<button type=\"button\" class=\"license-activate-action btn btn-primary\">ACTIVATE</button>";
      }

      html = html + "</div>";

      html = html + "</li>";
    }

    /*
     * add a refresh button at the bottom, so we can manually refresh the list, without having to re-login
     * this can be necessary if they went and bought more licenses and we just want to see them, instead of having
     * to logout and login again just to see the new ones.
     *
     */

    html = html + "<li><button style=\"margin-left: 8px; margin-top: 16px; margin-bottom: 16px\" type=\"button\" class=\"license-summary-refresh-action btn btn-primary\">REFRESH</button></li>";


    html = html + "</ul>";

    /* render! */

    $(body).html(html);

    /* scroll back to where we were before... */

    this.scrollBack.call(this, scrollLock);

    /* add any behaviors that are needed ... */

    $('.license-activate-action').click(this, function(event) {

      echo("[ActivateButton] activation of license, gathering info ... ");

      var instance = event.data;

      var userid   = App.LoginController.status.userid;
      var password = App.LoginController.status.password;

      var pid      = $(this).parents('li').data('pid');
      var vin      = App.CANBusController.status.vin.vin;
      var pgmDate  = App.CANBusController.status.programdate;
      var shopCode = App.CANBusController.status.shopcode;
      var dashblob = App.CANBusController.status.dashblob;
      var version  = App.CANBusController.status.version;
      var checksum = App.CANBusController.status.checksum;

      instance.activate.call(instance, userid, password, pid, vin, pgmDate, shopCode, dashblob, version, checksum);

    });

    $('.license-summary-refresh-action').click(this, function(event) {

      var instance = event.data;
      instance.update.call(instance);

    });

    /* enable/disable the refresh button as appropriate... */

    if(this.login.connected) {

      /* normally allow the usr to refresh the license list... */

      $('.license-summary-refresh-action').removeAttr("disabled");

    } else {

      /* if we aren't logged in...we can't do a refresh */

      $('.license-summary-refresh-action').attr("disabled", "disabled");
    }

    /* enable/disable the license activation buttons as needed */

    if(this.canbus) {

      /* normally allow the usr to activate licenses... */

      $('.license-activate-action').removeAttr("disabled");

    } else {

      /* if we aren't connected to an ECU, so there will be no VIN #, disallow activation. */

      $('.license-activate-action').attr("disabled", "disabled");
    }

    /* refresh the flash screen */

    App.FlashButton.update.call(App.FlashButton);

    /* let other components know that the licenses status has (maybe) changed */

    EventBus.dispatch('activate-status-changed', this);

  },

  /**
   *
   * disable() - force the button to be disabled.
   *
   * @return boolean always true.
   *
   */

  disable: function() {
    ActivateButton.Super.prototype.disable.call(this);
  },

  /**
   *
   * enable() - force the button to be enabled.
   *
   * @return boolean always true.
   *
   */

  enable: function() {
    ActivateButton.Super.prototype.enable.call(this);
  }

});