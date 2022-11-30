/**
 *
 * CANBusController is our base class for working with the vehicle's ECU via CAN Bus.
 *
 */

var CANBusController = my.Class({

  /* standard constructor */

  constructor: function(options) {

    echo("[CANBusController] constructing...");

    /* Login status, false === not connected */

    this.status   = false;

    this.oldvin   = "";

    /*
    this.status   = {
      connected: true,
      vin: {
        vin:          '4UF17SNW5HT808095',
        serial:       '808095',
        year:         2017,
        manufacturer: 'Arctic Cat',
        wmi:          '4UF',
        plant:        'T',
        vds:          '17SNW',
        country:      'United States'
      }
    };
    */

    this.nextScan   = 3;
    this.scanning   = false;
    this.updating   = false;
    this.suspendBG  = false;

    /* our configuration */

    this.options  = {

      id:      false

    };

    /* merge in options */

    $.extend(this.options, options);

    /* do scans in the background */

    window.setTimeout(function() {
      App.CANBusController.scanCountDown.call(App.CANBusController);
    }, 1000);

    /* do one right now, so we have a list to work with as soon as possible. */

    this.scan.call(this);
  },

  /**
   *
   * scanCountDown() - helper to invoke in the background an ECU check every 5sec or so.
   *
   */

  scanCountDown: function() {

    if(!this.scanning) {
      this.nextScan = this.nextScan - 1;
    }

    if (this.nextScan == 0) {

      this.nextScan = 3;

      /* do a scan */

      this.scan.call(this);

    }

    window.setTimeout(function() {
      App.CANBusController.scanCountDown.call(App.CANBusController);
    }, 1000);
  },

  /**
   *
   * scan() - in the background we update the available ECU info.
   *
   */

  scan: function() {

    if(this.scanning || this.updating || this.suspendBG) {

      /* don't overlap */

      return true;
    }

    if(this.status) {
      this.oldvin = this.status.vin.vin + "@" + this.status.dash;
    }

    this.scanning = true;

    /* ask the server for an update ... */

    var instance  = this;

    $.ajax({

      url: '/rest/ecu/info',

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        instance.status   = false;
        instance.scanning = false;

        echo('[CANBusController] ERROR problem fetching ECU info [' + textStatus + '] ' + errorThrown);

        instance.oldvin = "ERROR";
        instance.render.call(instance);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        instance.scanning = false;
        instance.status   = false;

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[CANBusController] there was a problem fetching the ECU info: " + data.message);

          instance.oldvin = "ERROR";
          instance.render.call(instance);

          return false;
        }

        if(this.suspendBG) {

          echo("[CANBusController] ECU info updated (but skipping because background work is suspended.");

        } else {

          instance.status   = {
            connected:   true,
            vin:         data.vin,
            shopcode:    data.shopcode,
            programdate: data.programdate,
            allinfos:    data.allinfos,
            version:     data.version,
            checksum:    data.checksum,
            dash:        data.dash,
            dashblob:    data.dashblob,
            ecu:         data.ecu,
            ecu_model:   data.ecu_model,
            dash_model:  data.dash_model
          };

          /* update the panel */

          instance.render.call(instance);

        }

        return true;
      }

    });

  },

  /**
   *
   * render() - update the CAN Bus panel
   *
   */

  render: function() {

    var newvin = "";

    if(this.status) {
      newvin   = this.status.vin.vin + "@" + this.status.dash;
    }

    /* if we haven't actually changed the VIN #, there is no need to update */

    if(newvin == this.oldvin) {
      return true;
    }

    /* figure out the panel we are rendering to... */

    var body = $(this.options.id).find('.panel-body');
    var html = "";

    if(!$(body).is('div')) {
      echo("[CANBusController] skipping, no panel body.");
      return true;
    }

    /*
     * this panel has sub-sections, so we create a wrapper this is wider than the panel
     * (as wide as there are sub-sections), and then we "slide" in the whatever
     * sub-section we want to view.
     *
     */

    var sub    = "<div class=\"panel-budy-sub-wrapper-can\"></div>";
    $(body).html("");

    /* sub-panel carousel on top */

    $(body).append(sub);

    /* sub-pane navigation buttons */

    $(body).append(this.renderActions.call(this));

    /* drop in the sub-panel area... */

    var body   = $(this.options.id).find('.panel-budy-sub-wrapper-can');

    /* build the main status panel */

    /* append panel #1 */

    if(this.status) {

      $(body).append(this.renderConnected.call(this));

    } else {
      $(body).append(this.renderDefault.call(this));
    }

    /* add the trouble codes panel */

    var html = "<div class=\"panel-body-sub-panel\" id=\"can-bus-dtc-sub-panel\">";

    html = html + "<h3 class=\"\">Trouble Codes</h3>";

    html = html + "</div>";

    /* append panel #2 */

    $(body).append(html);

    /* add the snapshot panel */

    var html = "<div class=\"panel-body-sub-panel\" id=\"can-bus-snapshot-sub-panel\">";

    html = html + "<h3 class=\"\">Snapshot</h3>";

    html = html + "</div>";

    /* append panel #3 */

    $(body).append(html);

    /* add any behaviors that are needed ... */

    $('button#can-status-action').click(function () {
      $('.panel-budy-sub-wrapper-can').animate({
        left: "0"
      }, 1000);
    });

    $('button#can-dtc-action').click(function () {

      $('.panel-budy-sub-wrapper-can').animate({
        left: "-100%"
      }, 1000);

      /* fill in the trouble codes  */

      instance.renderTroubleCodes.call(instance);
    });

    var instance = this;

    $('button#can-snapshot-action').click(function () {

      $('.panel-budy-sub-wrapper-can').animate({
        left: "-200%"
      }, 1000);

      /* fill in a snap shot of ECU data */

      instance.renderSnapshot.call(instance);
    });

    /* enable/disable the panel changing action buttons as appropriate */

    if(this.status) {

      $('.can-panel-action').removeAttr("disabled");

    } else {

      $('.can-panel-action').attr("disabled", "disabled");
    }

    /*
     * let other components that we changed, only do this when the VIN # actually changes, because a VIN # change
     * can cause other bigger queries, like fetching the licenses for that VIN #.
     *
     */

    EventBus.dispatch('canbus-status-changed', this);
  },

  renderTroubleCodes: function() {

    var body = $('div#can-bus-dtc-sub-panel');
    var html = "";

    if(!$(body).is('div')) {
      echo("[CANBusController] skipping dtc render, no sub-panel body.");
      return true;
    }

    $('div#can-bus-dtc-sub-panel').html("<p>Please wait...fatching trouble codes...</p>");

    lock_page();

    var instance = this;

    $.ajax({

      url: '/rest/ecu/dtc',

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        unlock_page();

        echo('[CANBusController] ERROR problem fetching trouble codes [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[CANBusController] there was a problem fetching the trouble codes: " + data.message);

          return false;
        }

        echo("[CANBusController] showing trouble codes...");

        var codes = data.codes;
        var body = $('div#can-bus-dtc-sub-panel');
        var html = "";

        html = html + " <ul class=\"dtc-list\">";

        for(var i=0; i<codes.length; i+=2) {

          var sample = codes[i];

          html = html + "<li>";

          html = html + "<span style=\"font-size: 20px; line-height: 24px;\">[<span class=\"fw-semibold\">" + sample.code + "</span>]  <span class=\"fw-thin\">" + sample.error + "</span>  (" + sample.attr + ")</span>";

          html = html + "<div style=\"clear: both; height: 1px;\"></div>";
          html = html + "</li>";

        }

        /* add refresh / clear buttons at the bottom */

        html = html + "<li>";

        html = html + "<button style=\"margin-left: 8px; margin-top: 16px; margin-bottom: 16px; margin-right: 16px;\" type=\"button\" class=\"dtc-panel-refresh-action btn btn-primary\">REFRESH</button>";
        html = html + "<button style=\"margin-left: 8px; margin-top: 16px; margin-bottom: 16px\" type=\"button\" class=\"dtc-panel-clear-action btn btn-primary\">CLEAR DTC</button>";

        html = html + "</li>";


        html = html + "</ul>";

        $(body).html(html);

        $('.dtc-panel-refresh-action').click(instance, function(event) {

          var instance = event.data;
          instance.renderTroubleCodes.call(instance);

        });

        $('.dtc-panel-clear-action').click(instance, function(event) {

          var instance = event.data;

          lock_page();

          $.ajax({

            url: '/rest/ecu/dtcclr',

            dataType: 'json',

            error: function (jqXHR, textStatus, errorThrown) {

              unlock_page();

              echo('[CANBusController] ERROR problem clearing trouble codes [' + textStatus + '] ' + errorThrown);

              return false;
            },

            success: function (data, textStatus, jqXHR) {

              unlock_page();

              if (isset(data.status) && (data.status == "ERROR")) {

                echo("[CANBusController] there was a problem clearing the trouble codes: " + data.message);

                return false;
              }

              /* refresh the list */

             instance.renderTroubleCodes.call(instance);
            }

          });

        });

        return true;
      }

    });

  },

  /*
   * helper to update the snapshot sub-panel
   *
   */

  renderSnapshot: function() {

    var body = $('div#can-bus-snapshot-sub-panel');
    var html = "";

    if(!$(body).is('div')) {
      echo("[CANBusController] skipping snapshot render, no sub-panel body.");
      return true;
    }

    $('div#can-bus-snapshot-sub-panel').html("<p>Please wait...fatching data channel snapshot...</p>");

    lock_page();

    var instance = this;

    $.ajax({

      url: '/rest/ecu/sample',

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        unlock_page();

        echo('[CANBusController] ERROR problem fetching ECU snapshot [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[CANBusController] there was a problem fetching the ECU snapshot: " + data.message);

          return false;
        }

        echo("[CANBusController] showing snapshot...");

        var samples = data.samples;
        var body = $('div#can-bus-snapshot-sub-panel');
        var html = "";

        html = html + " <ul class=\"snapshot-list\">";

        for(var i=0; i<samples.length; i+=2) {

          var sample1 = samples[i];
          var sample2 = false;

          if((i+1) < samples.length) {
            sample2 = samples[i+1];
          }

          html = html + "<li>";

          html = html + "<div style=\"width: 50%; display: block; float: left;\">";
          html = html + "<h3 class=\"fw-thin\">" + sample1.name + ":</h3><h3 style=\"width: 80%; display: inline-block; float: left;\" class=\"fw-semibold\">" + sample1.value + "</h3> <span style=\"display: inline-block; float: left; margin-top: 10px;\">" + sample1.units + "</span>";
          html = html + "</div>";

          if(sample2) {

            html = html + "<div style=\"width: 50%; display: block; float: left;\">";
            html = html + "<h3 class=\"fw-thin\">" + sample2.name + ":</h3><h3 style=\"width: 80%; display: inline-block; float: left;\"  class=\"fw-semibold\">" + sample2.value + "</h3> <span style=\"display: inline-block; float: left; margin-top: 10px;\">" + sample2.units + "</span>";
            html = html + "</div>";
          }

          html = html + "<div style=\"clear: both; height: 1px;\"></div>";
          html = html + "</li>";

        }

        /* add a refresh button at the bottom */

        html = html + "<li><button style=\"margin-left: 8px; margin-top: 16px; margin-bottom: 16px\" type=\"button\" class=\"snapshot-panel-refresh-action btn btn-primary\">REFRESH</button></li>";

        html = html + "</ul>";

        $(body).html(html);

        $('.snapshot-panel-refresh-action').click(instance, function(event) {

          var instance = event.data;
          instance.renderSnapshot.call(instance);

        });

        return true;
      }

    });

  },

  /**
   *
   * renderConnected() - panel rendering when the ECU is connected and we have a VIN #
   *
   * @returns {string}
   *
   */

  renderConnected: function() {

    /* layout the status details */

    var html = "<div class=\"panel-body-sub-panel\" id=\"can-bus-status-sub-panel\">";

    var vin   = this.status.vin;
    var dash  = this.status.dash;
    var flash = this.status.version;

    if(empty(dash)) {
      dash = "No Dash";
    }

    if(empty(flash) || (flash == "Unknown")) {
      flash = "Not PEFI flash";
    }

    html = html + "<div style=\"width: 60%; display: block; float: left; padding-left: 8px; padding-top: 8px;\">";
    html = html + "<span><h4 class=\"fw-thin leftcol\">VIN #:</h4><h4 class=\"fw-semibold rightcol\">" + vin.vin + "</h4></span>";
    html = html + "<span><h4 class=\"fw-thin leftcol\">Serial:</h4><h4 class=\"fw-semibold rightcol\">" + vin.serial + "</h4></span>";
    html = html + "<span><h4 class=\"fw-thin leftcol\">Year:</h4><h4 class=\"fw-semibold rightcol\">" + vin.year + "</h4></span>";
    html = html + "<span><h4 class=\"fw-thin leftcol\">Dash:</h4><h4 class=\"fw-semibold rightcol\">" + dash + "</h4></span>";
    html = html + "</div>";

    html = html + "<div style=\"width: 39%; display: block; float: left; padding-top: 8px;\">";
    html = html + "<span><h4 class=\"fw-thin leftcol\">Maker:</h4><h4 class=\"fw-semibold rightcol\">" + vin.manufacturer + " (" + vin.wmi + ")</h4></span>";
    html = html + "<span><h4 class=\"fw-thin leftcol\">VDS:</h4><h4 class=\"fw-semibold rightcol\">" + vin.vds + "</h4></span>";
    html = html + "<span><h4 class=\"fw-thin leftcol\">Country:</h4><h4 class=\"fw-semibold rightcol\">" + vin.country + "</h4></span>";
    html = html + "<span><h4 class=\"fw-thin leftcol\">Flash:</h4><h4 class=\"fw-semibold rightcol\">" + flash + "</h4></span>";
    html = html + "</div>";

    html = html + "</div>";

    return html;
  },

  /*
   * renderActions() - helper to generate common button bar at the bottom of the sub-panels
   *
   */

  renderActions: function() {

    var html = "<div class=\"can-button-bar\">";

    html = html + "<button type=\"button\" id=\"can-status-action\" class=\"can-panel-action btn btn-primary\">Status</button>";
    html = html + "<button type=\"button\" id=\"can-dtc-action\" class=\"can-panel-action btn btn-primary\">Trouble Codes</button>";
    html = html + "<button type=\"button\" id=\"can-snapshot-action\" class=\"can-panel-action btn btn-primary\">Snapshot</button>";

    html = html + "</div>";

    return html;
  },

  /**
   *
   * renderDefault() - panel rendering when there is no ECU connected.
   *
   * @returns {string}
   *
   */

  renderDefault: function() {

    var html = "<div class=\"panel-body-sub-panel\" id=\"can-bus-status-sub-panel\">";

    html = html + "<p style=\"margin-top: 20px; margin-left: 20px; margin-right: 20px;\">";
    html = html + "Before you can view or activate your ECU flash license(s), you must connect to your vehicle.</br>";
    html = html + "Please connect the CAN Bus cable.  ECU and VIN identification is automatic once connected.";
    html = html + "</p>";

    html = html + "</div>";

    return html;
  }

});