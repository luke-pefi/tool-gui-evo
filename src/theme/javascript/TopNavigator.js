/**
 *
 * TopNavigator handles  updating the status summary bar at the top.
 *
 */

var TopNavigator = my.Class({

  /* standard constructor */

  constructor: function(options) {

    echo("[TopNavigator] constructing...");

    /* WiFi status, false === not connected */

    this.wifiStatus  = {
      connected: false
    };

    /* Login status, false === not connected */

    this.loginStatus  = {
      connected: false
    };

    /* CAN Bus status, false === not connected */

    this.canBusStatus  = {
      connected: false
    };

    this.temperature = false;

    /* our configuration */

    this.options = {

      id:      false

    };

    this.nextScan   = 3;
    this.scanning   = false;

    /* merge in options */

    $.extend(this.options, options);

    /* initial rendering */

    this.update.call(this);

    /* listen for changes on the CANBusController */

    EventBus.addEventListener('canbus-status-changed', function(event) {

      var type          = event.type;
      var controller    = event.target;
      this.canBusStatus = controller.status;

      this.update.call(this);

    }, this);

    /* listen for changes on the LoginController */

    EventBus.addEventListener('login-status-changed', function(event) {

      var type         = event.type;
      var controller   = event.target;
      this.loginStatus = controller.status;

      this.update.call(this);

    }, this);

    /* listen for changes on the WiFiController */

    EventBus.addEventListener('wifi-status-changed', function(event) {

      var type        = event.type;
      var controller  = event.target;
      this.wifiStatus = controller.status;

      this.update.call(this);

    }, this);

    /* update the CPU temperature view every few seconds */

    window.setTimeout(function() {
      App.TopNavigator.scanTempCountDown.call(App.TopNavigator);
    }, 1000);

    /* do one right now, so we have a list to work with as soon as possible. */

    this.scanTemp.call(this);

  },

  /**
   *
   * scanCountDown() - helper to invoke in the background an ECU check every 5sec or so.
   *
   */

  scanTempCountDown: function() {

    if(!this.scanning) {
      this.nextScan = this.nextScan - 1;
    }

    if (this.nextScan == 0) {

      this.nextScan = 3;

      /* do a scan */

      this.scanTemp.call(this);

    }

    window.setTimeout(function() {
      App.TopNavigator.scanTempCountDown.call(App.TopNavigator);
    }, 1000);
  },

  /*
   * update our view of the CPU temperature
   *
   */

  scanTemp: function() {

    if(this.scanning) {

      /* don't overlap */

      return true;
    }

    this.scanning = true;

    /* ask the server for an update ... */

    var instance  = this;

    $.ajax({

      url: '/rest/system/temperature',

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        instance.temperature = false;
        instance.scanning = false;

        echo('[TopNavigator] ERROR problem fetching CPU temperature [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        instance.temperature = false;
        instance.scanning = false;

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[TopNavigator] there was a problem fetching CPU temperature: " + data.message);

          return false;
        }

        /* ok, update the temperature component ... */

        var temp = data.temp;
        var comp = $('.temp-comp');

        if(!$(comp).is('div')) {

          echo("[TopNavigator] skipping temp update, no component.");

          return true;
        }

        var html   = "";

        /* build the html */

        html = html + "|&nbsp;<i style=\"color: #fff;\" class=\"fa fa-thermometer-half\" aria-hidden=\"true\"></i>";
        html = html + "&nbsp; <span class=\"fw-semibold\">" + temp+ "</span>";

        $(comp).html(html);

      }

    });

  },

  /**
   *
   * update() - render out a fresh copy of the top nav.
   *
   */

  update: function() {

    this.renderWiFi.call(this);

    this.renderLogin.call(this);

    this.renderCANBus.call(this);
  },

  /**
   *
   * renderWiFi() - update the WiFI piece of the top nav
   *
   */

  renderWiFi: function() {

    var comp = $('.wifi-comp');

    if(!$(comp).is('div')) {

      echo("[TopNavigator] skipping no component.");

      return true;
    }

    var status = this.wifiStatus;
    var html   = "";

    /* build the html */

    if(status.connected) {

      html = html + "<i style=\"color: " + status.dBm.color + ";\" class=\"fa fa-wifi\" aria-hidden=\"true\"></i>";
      html = html + "&nbsp;" + status.dBm.dBm + " dBm";
      html = html + "&nbsp;(" + status.dBm.percent + "%)";

      html = html + "&nbsp; <span class=\"fw-semibold\">" + status.ip + "</span>";
    } else {

      html = html + "<span style=\"color: red;\">No Network</span>";
    }

    /* render */

    $(comp).html(html);

    return true;
  },

  /**
   *
   * renderLogin() - update the Login piece of the top nav
   *
   */

  renderLogin: function() {

    var comp = $('.login-comp');

    if(!$(comp).is('div')) {

      echo("[TopNavigator] skipping no component.");

      return true;
    }

    var status = this.loginStatus;
    var html   = "";

    /* build the html */

    if(status.connected) {

      html = html + "|&nbsp;<i style=\"color: rgb(51, 255, 0);\" class=\"fa fa-lock\" aria-hidden=\"true\"></i>";
      html = html + "&nbsp;<strong>" + status.userid + "</strong>";

    } else {

      html = html + "|&nbsp;<span style=\"color: red;\">No User</span>";
    }

    /* render */

    $(comp).html(html);

    return true;
  },

  /**
   *
   * renderCANBus() - update the Login piece of the top nav
   *
   */

  renderCANBus: function() {

    var comp = $('.canbus-comp');

    if(!$(comp).is('div')) {

      echo("[TopNavigator] skipping no component.");

      return true;
    }

    var status = this.canBusStatus;
    var html   = "";

    /* build the html */

    /* show the VIN # if we have it, a VIN # implies you are connected ok to an ECU */

    if(status.connected) {

      html = html + "|&nbsp;<i style=\"color: rgb(51, 255, 0);\" class=\"fa fa-check\" aria-hidden=\"true\"></i>";
      html = html + "&nbsp;<strong>" + status.vin.vin + "</strong>";

    } else {

      html = html + "|&nbsp;<span style=\"color: red;\">No VIN #</span>";
    }

    /* now do we have Dash connected? */

    if(/[aA]rctic/.test(status.dash)) {

      /* Arctic Cat dash */

      html = html + "&nbsp;&nbsp;<span style=\"padding-left: 4px; padding-right: 4px; font-weight: 600; line-height: 12px; font-size: 12px; border: 1px solid #00ff00; color: lime;\">A</span>";

    } else if(/[yY]amaha/.test(status.dash)) {

      /* Yamaha dash */

      html = html + "&nbsp;&nbsp;<span style=\"padding-left: 4px; padding-right: 4px; font-weight: 600; line-height: 12px; font-size: 12px; border: 1px solid #00ff00; color: lime;\">Y</span>";

    } else if(!empty(status.dash)) {

      /* not recognized  */

      html = html + "&nbsp;&nbsp;<span style=\"padding-left: 4px; padding-right: 4px; font-weight: 600; line-height: 12px; font-size: 12px; border: 1px solid #556b2f; color: darkolivegreen;\">U</span>";

    } else {

      /* not connected */

      html = html + "&nbsp;&nbsp;<span style=\"padding-left: 4px; padding-right: 4px; font-weight: 600; line-height: 12px; font-size: 12px; border: 1px solid #696969; color: dimgray;\">D</span>";

    }


    /* render */

    $(comp).html(html);

    return true;
  }

});