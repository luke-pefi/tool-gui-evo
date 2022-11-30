/**
 *
 * FLash button controls the panel for for actually flashing licenses ecu images
 *
 */

var FlashButton = my.Class(Button, {

  constructor: function(options) {

    options.name    = 'Flash';
    options.bubble  = "Download a new ROM image to your ECU";
    options.id      = Button.MOD_FLASH;
    options.panelid = '#flash-panel';

    options.onClick = function(event) {

      echo("[FlashButton] toggling panel...");

      FlashButton.Super.prototype.pop.call(event.data);
    };

    this.flashes  = [];
    this.vin      = "";

    this.login    = {
      connected: false
    };

    this.canbus   = {
      connected: false
    };

    FlashButton.Super.call(this, options);

    /*
     * this button starts out disabled, we enable when the user is logged in.  We can only
     * go and fetch the licensed flashes when we can login to the main site.
     *
     */

    FlashButton.Super.prototype.disable.call(this);

    EventBus.dispatch('flash-actions-disabled', this);

    /* wait for status updates... */

    echo("[FlashButton] waiting on login ...");

    EventBus.addEventListener('login-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      this.login     = status;

      if(this.login.connected && this.canbus.connected) {

        echo("[FlashButton] enabling (login)... ");

        FlashButton.Super.prototype.enable.call(this);

        $(Button.MOD_FLASH).css('background-color', '#fff');

        EventBus.dispatch('flash-actions-enabled', this);

        /* update the panel with the current known licensed flashes */

        this.update.call(this);

      } else {

        if(FlashButton.Super.prototype.isEnabled.call(this)) {

          echo("[FlashButton] disabling... ");

          FlashButton.Super.prototype.disable.call(this);

          this.flashes = [];
          this.vin = "";

          this.render.call(this);

        } else {

          echo("[FlashButton] already disabled, do nothing.");
        }

      }

    }, this);

    echo("[FlashButton] waiting on can bus ...");

    EventBus.addEventListener('canbus-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      this.canbus    = status;

      if(this.login.connected && this.canbus.connected) {

        echo("[FlashButton] enabling (canbus)... ");

        FlashButton.Super.prototype.enable.call(this);

        $(Button.MOD_FLASH).css('background-color', '#fff');

        EventBus.dispatch('flash-actions-enabled', this);

        /* update the panel with the current known licenses */

        this.update.call(this);

      } else {

        if(FlashButton.Super.prototype.isEnabled.call(this)) {

          echo("[FlashButton] disabling... ");

          FlashButton.Super.prototype.disable.call(this);

          this.flashes = [];
          this.vin = "";

          this.render.call(this);

        } else {

          echo("[FlashButton] already disabled, do nothing.");
        }
      }

    }, this);


    /*
     * also watch for updates to the licenses, if something was activated, then
     * we have now burnables :)
     *
     */

    EventBus.addEventListener('activate-status-changed', function(event) {

      echo("[FlashButton] license activation changes, updating...");

      /* update the panel with the current known licenses */

      this.update.call(this);

    }, this);

  },

  /**
   *
   * update() - fetch the summary of flashes we can burn...
   *
   */

  update: function() {

    if(!isset(App.CANBusController)) {

      /* its not ready yet */

      echo("[FlashButton] skipping update, no can bus controller.");
      return true;
    }

    /* we can't flash if we don't have a VIN # and user credentials ... */

    var doUpdate = true;

    if(!isset(App.LoginController.status) || !isset(App.CANBusController.status)) {

      echo("[FlashButton] skipping update, no can bus status");
      doUpdate = false;

    } else {

      if(empty(App.LoginController.status.userid) || empty(App.LoginController.status.password)) {

        echo("[FlashButton] skipping uppdate, not logged in.");
        doUpdate = false;

      } else {

        if(!isset(App.CANBusController.status.vin)) {

          echo("[FlashButton] skipping uppdate, no vin field");
          doUpdate = false;

        } else {

          if(empty(App.CANBusController.status.vin.vin)) {

            echo("[FlashButton] skipping update, no vin #");
            doUpdate = false;
          }
        }
      }
    }

    if(!doUpdate) {

      /* no credentials yet */

      return true;
    }

    /* get the list of available flashes for this VIN # */

    var instance = this;

    lock_page();

    $.ajax({

      url:      '/rest/flashes/summary',

      method:   'POST',

      dataType: 'json',

      data:     {
        userid:   App.LoginController.status.userid,
        password: App.LoginController.status.password,
        vin:      App.CANBusController.status.vin.vin
      },

      error: function (jqXHR, textStatus, errorThrown) {

        unlock_page();

        echo('[FlashButton] ERROR problem fetching info [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[FlashButton] there was a problem fetching flashes summary: " + data.message);

          return false;
        }

        instance.flashes = data;
        instance.vin     = App.CANBusController.status.vin.vin;

        /* get the initial rendering of the panel done */

        instance.render.call(instance);

        return true;

      }

    });

  },

  /**
   *
   * startFlash() - kick off the flashing sequence and notify the main server that we've started to
   * flash this one.  While we are flashing the other parts of the GUI that chat with the ECU daemon
   * will be disabled; flashing is "modal" as far as the ECU is concerned.  We want to leave some
   * parts of the GUI enabled though; support button, WiFi access, so that you can still work with
   * the GUI if things get stuck or otherwise go south.
   *
   */

  startFlash: function(userid, password, flashId, kind, vin) {

    echo("[FlashButton] starting flash sequence " + userid + ":" + password + " | " + flashId + " | " + kind);

    /* disable the can button/controller */

    echo("[FlashButton] going modal...");

    App.CANBusButton.disable.call(App.CANBusButton);
    App.CANBusController.suspendBG = true;

    /* disable the activation button */

    App.ActivateButton.disable.call(App.ActivateButton);

    /* disable the refresh button */

    App.RefreshButton.disable.call(App.RefreshButton);

    /* once we start flashing, you can't quit the progress panel until an error or completion. */

    $('button#flash-retry-action').attr("disabled", "disabled");
    $('button#flash-ack-action').attr("disabled", "disabled");

    /*
     * chained action; notify the main server that this flash should get an increment on it its number of
     * times flashed, and after that, we actually go for it!
     *
     */

    echo("[FlashButton] updating main server...");

    var instance = this;
    lock_page();

    $.ajax({

      url: '/rest/flashes/update',

      method: 'POST',

      dataType: 'json',

      data: {
        userid:   App.LoginController.status.userid,
        password: App.LoginController.status.password,
        fid:      flashId,
        verb:     'flash_flashing',
        message:  'Flash process started on RPI',
        vin:      vin
      },

      error: function (jqXHR, textStatus, errorThrown) {

        unlock_page();

        echo('[FlashButton] ERROR problem fetching info [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[FlashButton] there was a problem fetching flashes summary: " + data.message);

          return false;
        }

        /* flash process initiated, do the actual flashing... */

        echo("[FlashButton] starting flash (" + flashId + " / " + kind + ")...");

        $('.flash-progress-bar').removeClass('bg-success');
        $('.flash-progress-bar').removeClass('bg-danger');
        $('.flash-progress-bar').addClass('bg-info');

        /* flip to progress dialog... */

        $('.panel-body-sub-wrapper-flash').animate({
          left: "-100%"
        }, 1000);

        if(kind != 'dash') {
          kind = 'flash';
        }

        /* start a Server Sent Events (SSE) listener so we can do live progress. */

        var url               = "/rest/licenses/" + kind + "/";
        url                  += window.encodeURIComponent(App.LoginController.status.userid)   + "/";
        url                  += window.encodeURIComponent(App.LoginController.status.password) + "/";
        url                  += flashId;
        var es                = new EventSource(url);

        var listener          = function (event) {

          echo("[watch][" + event.type + "] ...");

          var data = {};

          if(isset(event.data)) {
            data = $.parseJSON(event.data);
          }

          if(event.type == "progress") {

            /* update the progress */

            var soFar = data.sofar + "/" + data.total;

            /* update the summary line, it has Speed, Elapsed,  ETA, */

            var summary = "<div style=\"width: 100%; display: inline-block; float: left; margin-right: 8px;\"><span style=\"font-size: 16px; line-height: 16px; width: 50%; margin-right: 16px;\">ETA: " + "<span class=\"fw-semibold\">" + data.eta + "</span>" + "</span><span style=\"width: 50%;\">Elapsed: " + data.elapsed + "</span></div>";

            $('.flash-progress-summary').html(summary);

            /* update the actual progress bar dynamically ... */

            var percentF = parseFloat(data.progress);
            var percent  = percentF.toFixed(2);

            $('.flash-progress-bar').css('width', percent + '%').attr('aria-valuenow', percent);

            var text  = "<span>" + percent + '% ' + soFar + ' @ ' + data.speed + "</span>";

            if(percent < 15) {
              text = "<span>" + percent + '% ' + "</span>";

            }

            $('.flash-progress-bar').html(text);

            /* set the message area... */

            $('.flash-progress-message').html("<p><strong>" + data.message + "</strong></p>");

          } else if(event.type == "completed") {

            echo("[watch][info] flash completed: " + data.message);

            /* show progress message */

            $('.flash-progress-bar').removeClass('bg-info');
            $('.flash-progress-bar').addClass('bg-success');

            $('.flash-progress-message').html("<p><strong>" + data.message + "</strong></p>");

            /* enable ack button */

            $('button#flash-retry-action').removeAttr("disabled");
            $('button#flash-ack-action').removeAttr("disabled");

            /* all done, (OK) stop listening... */

            echo("[watch] done listening.");
            es.close();

          } else if(event.type == "failed") {

            echo("[watch][error] flash failed: " + data.message);

            /* show progress message */

            $('.flash-progress-bar').removeClass('bg-info');
            $('.flash-progress-bar').addClass('bg-danger');

            $('.flash-progress-message').html("<p><strong>" + data.message + "</strong></p>");

            /* enable ack button */

            $('button#flash-retry-action').removeAttr("disabled");
            $('button#flash-ack-action').removeAttr("disabled");

            /* all done, (FAIL) stop listening... */

            echo("[watch] done listening.");
            es.close();

          } else if(event.type == "error") {

            echo("[watch][error] " + data.message);

            /* show progress message */

            $('.flash-progress-bar').removeClass('bg-info');
            $('.flash-progress-bar').addClass('bg-danger');

            $('.flash-progress-message').html("<p><strong>" + data.message + "</strong></p>");

            /* enable ack button */

            $('button#flash-retry-action').removeAttr("disabled");
            $('button#flash-ack-action').removeAttr("disabled");

            echo("[watch] done listening.");
            es.close();

          } else if(event.type == "info") {

            echo("[watch][info] " + data.message);

            /* show progress message */

            $('.flash-progress-message').html("<p><strong>" + data.message + "</strong></p>");

          }

        };

        es.addEventListener("progress",  listener);
        es.addEventListener("completed", listener);
        es.addEventListener("failed",    listener);
        es.addEventListener("error",     listener);
        es.addEventListener("info",      listener);

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

    var panel      = $('#flash-panel .panel-body ul');

    if (!$(panel).is('ul')) {

      /* no visible anyways */

      console.log("[FlashButton] no scrollback; panel not visible.");
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

    console.log("[FlashButton] scrolled to: " + panel.scrollTop);
  },

  /**
   *
   * captureScrollPosition() - helper to grab the scroll info for the list of the panel,
   * so that on refresh, we save where we were, and then scroll the list back there after
   * refreshing the panel.
   *
   */

  captureScrollPosition: function () {

    var panel      = $('#flash-panel .panel-body ul');

    var result     = null;

    if (!$(panel).is('ul')) {

      console.log("[FlashButton] panel is not UL");

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

        console.log("[FlashButton] panel has no client height (hidden)");

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

    console.log("[FlashButton] current scroll position: ", result);

    /* pass it back */

    return result;
  },

  /**
   *
   * render() - render out the flashes panel, we use two sub-panels; main one is to show the list of
   * available flashes for this VIN #, but a second sub-panel is used for the flash progress dialog, which you
   * can only enter/leave during the flashing process.  While in the flashing process, the other major functions
   * of the GUI (related to the ECU) are all disabled...flashing is "modal" in nature.
   *
   */

  render: function() {

    var body = $(this.options.panelid).find('.panel-body');
    var html = "";

    if (!$(body).is('div')) {
      echo("[FlashButton] skipping, no panel body.");
      return true;
    }

    /*
     * capture the previous scroll position...so we jump back there after re-building the
     * list.
     *
     */

    var scrollLock = this.captureScrollPosition.call(this);

    /*
     * this panel has sub-sections, so we create a wrapper this is wider than the panel
     * (as wide as there are sub-sections), and then we "slide" in the whatever
     * sub-section we want to view.
     *
     */

    var sub    = "<div class=\"panel-body-sub-wrapper-flash\"></div>";
    $(body).html("");

    /* insert the carousel wrapper for both panels */

    $(body).append(sub);

    /* drop in the sub-panel area... */

    var body   = $(this.options.panelid).find('.panel-body-sub-wrapper-flash');
    var html   = "<div class=\"panel-body-sub-panel-flash\" id=\"flash-main-sub-panel\">";

    /* build the main status panel */

    var html = html + "<ul class=\"licenses-list list-group\">";

    for(var i=0; i<this.flashes.length; i++) {

      var flash = this.flashes[i];
      var attr  = "";

      attr = attr + " data-fid=\""        + flash.flash_id     + "\"";
      attr = attr + " data-lid=\""        + flash.license_id   + "\"";
      attr = attr + " data-version=\""    + flash.version      + "\"";
      attr = attr + " data-checksum=\""   + flash.checksum     + "\"";
      attr = attr + " data-key=\""        + flash.license_key  + "\"";
      attr = attr + " data-vin=\""        + flash.vin          + "\"";
      attr = attr + " data-pid=\""        + flash.product_id   + "\"";
      attr = attr + " data-pname=\""      + flash.product_name + "\"";
      attr = attr + " data-activated=\""  + flash.activated    + "\"";
      attr = attr + " data-kind=\""       + flash.kind         + "\"";

      html = html + "<li " + attr + " class=\"list-group-item\">";

      html = html + "<div class=\"left-col\">";

      /* when it was activated */

      var text = flash.activated.replace(' ', '<br/>');

      html = html + "<p>" + text + "</p>";

      html = html + "</div>";

      html = html + "<div class=\"mid-col\">";

      /* product name */

      html = html + "<h5><span class=\"fw-semibold\">" + flash.product_name + "</span></h5>";

      /* license key and vin */

      if(flash.status == "FACTORY") {
        html = html + "<h5><span class=\"\">*</span></h5>";
        html = html + "<h5><span class=\"\">*</span></h5>";
      } else {
        html = html + "<h5><span class=\"\">" + flash.license_key + "</span><span class=\"fw-semibold\">  (" + flash.version + ")</span></h5>";
        html = html + "<h5><span class=\"\">" + flash.vin + "</span></h5>";
      }

      html = html + "</div>";

      /* the action buttons if appropriate */

      html = html + "<div class=\"right-col\">";

      html = html + "<button type=\"button\" class=\"license-flash-action btn btn-primary\">FLASH</button>";

      html = html + "</div>";

      html = html + "</li>";

    }

    html = html + "</ul>";

    html = html + "</div>";

    /* append main panel */

    $(body).append(html);

    /* add the progress panel */

    html = "<div class=\"panel-body-sub-panel-flash\" id=\"flash-progress-sub-panel\">";

    html = html + "<div class=\"flash-progress-header\">";
    html = html + "<h3 style=\"display: inline-block; float: left; clear: none; width: 150px\" class=\"fw-semibold\">Flash Progress</h3>";
    html = html + "<div style=\"display: inline-block; float: left; margin-top: 10px; clear: none; width: 300px;\" class=\"flash-progress-summary\">";
    html = html + "</div>";
    html = html + "</div>";

    html = html + "<div class=\"flash-progress-wrapper\">";

    /* the actual progress bar */

    html = html + "<div style=\"background-color: #515151;\" class=\"flash-progress progress\">";
    html = html + "<div class=\"flash-progress-bar progress-bar-animated progress-bar progress-bar-striped bg-info\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\"></div>";
    html = html + "</div>";

    /* detailed message below where it can expand if needed. */

    html = html + "<div class=\"flash-progress-message\">";
    html = html + "</div>";

    html = html + "</div>";

    html = html + "<div class=\"flash-progress-action-bar\">";
    html = html + "<button type=\"button\" id=\"flash-ack-action\" class=\"btn btn-primary\">ACKNOWLEDGE</button>";
    html = html + "<button type=\"button\" id=\"flash-retry-action\" class=\"btn btn-primary\">FLASH AGAIN</button>";

    html = html + "<button style=\"padding: 0px; margin-left: 0px;\" type=\"button\" class=\"flash-send-logs-action btn btn-primary\"><div><img src=\"/src/theme/images/dropbox-icon-38.png\" alt=\"Send Logs to Dropbox\"><label style=\"margin-left: 8px; margin-right: 8px;\">Send Logs</label></div></button>\n";

    html = html + "<button type=\"button\" id=\"flash-refresh-action\" class=\"btn btn-danger\">Refresh</button>";

    html = html + "<div><p style=\"margin-top: 16px; margin-left: 16px;\">On error or hanging, Send Logs <strong>immediately</strong> (do not restart). Uploading may take ~5 minutes.  Please be patient.  Use Refresh <strong>ONLY</strong> when Flashing hangs.  Refreshing may brick your ECU.</p></div>";

    html = html + "<div class=\"flash-support-error-message\"></div>";

    html = html + "</div>";

    html = html + "</div>";

    /* append progress panel*/

    $(body).append(html);

    /* scroll back to where we were before... */

    this.scrollBack.call(this, scrollLock);

    /* add any behaviors that are needed ... */

    $('.license-flash-action').click(this, function(event) {

      echo("[FlashButton] flashing of license, gathering info ... ");

      var instance = event.data;

      var userid   = App.LoginController.status.userid;
      var password = App.LoginController.status.password;
      var flashId  = $(this).parents('li').data('fid');
      var kind     = $(this).parents('li').data('kind');
      var vin      = $(this).parents('li').data('vin');

      $('button#flash-retry-action').data('fid', flashId);
      $('button#flash-retry-action').data('kind', kind);
      $('button#flash-retry-action').data('vin', vin);

      instance.startFlash.call(instance, userid, password, flashId, kind, vin);

    });

    $('button#flash-refresh-action').click(function() {

      echo("[FlashButton] refreshing GUI...");

      location.reload();

    });

    $('button#flash-ack-action').click(function () {

      /* flip back to main panel ... */

      $('.panel-budy-sub-wrapper-can').animate({
        left: "0"
      }, 1000);

      /* drop out of modal mode ... */

      echo("[FlashButton] leaving modal...");

      App.CANBusButton.enable.call(App.CANBusButton);
      App.CANBusController.suspendBG = false;

      /* enable the activation button */

      App.ActivateButton.enable.call(App.ActivateButton);

      /* enable the refresh button */

      App.RefreshButton.enable.call(App.RefreshButton);

      $('button#flash-retry-action').removeAttr("disabled");
      $('button#flash-ack-action').removeAttr("disabled");

      /* make sure all screens are up to date */

      echo("[FlashButton] refreshing flash/activation screens ...");

      /* refresh the flash screen */

      App.FlashButton.update.call(App.FlashButton);

      /* make sure the license summary screen is up to date as well */

      App.ActivateButton.update.call(App.ActivateButton);

    });

    $('button#flash-retry-action').click(this, function (event) {

      echo("[FlashButton] retrying...");

      var instance = event.data;

      var userid   = App.LoginController.status.userid;
      var password = App.LoginController.status.password;
      var flashId  = $('button#flash-retry-action').data('fid');
      var kind     = $('button#flash-retry-action').data('kind');
      var vin      = $('button#flash-retry-action').data('vin');

      instance.startFlash.call(instance, userid, password, flashId, kind, vin);

    });

    /* on the support tab allow them to upload the logs to Precision EFI's dropbox ... */

    $('button.flash-send-logs-action').click(function (){

      echo("[flash] sending logs...");

      var userid = 'anonymous';

      if(isset(App.LoginController.status.userid) && !empty(App.LoginController.status.userid)) {
        userid = App.LoginController.status.userid;
      }

      lock_page();

      $.ajax({
        url:      '/rest/support/sendlogs/' + userid,
        method:   'POST',
        dataType: 'json',

        error: function (jqXHR, textStatus, errorThrown) {

          unlock_page();

          var container = $('div.flash-support-error-message');

          $(container).html("<p style=\"margin-top: 16px; margin-left: 16px;\">" + textStatus + "</p>");

        },

        success: function (data, textStatus, jqXHR) {

          unlock_page();

          var container = $('div.flash-support-error-message');

          if(isset(data.status) && data.status == "ERROR") {
            $(container).html("<p style=\"margin-top: 16px; margin-left: 16px; color: #ff9999;\">" + data.message + "</p>");
            return ;
          }

          $(container).html("<p style=\"margin-top: 16px; margin-left: 16px; color: #99ff99;\">" + data.message + "</p>");

        }

      });

    });

    /* let other components know that the licenses status has (maybe) changed */

    EventBus.dispatch('flash-status-changed', this);

  }

});
