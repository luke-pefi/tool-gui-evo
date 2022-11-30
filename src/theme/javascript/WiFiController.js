/**
 *
 * WiFiController is the base class for WiFi management, knowing if we are connected or
 * not, what the details are, and what networks are available to join with.  Also provides
 * methods for actually joining and leaving networks.
 *
 */

var WiFiController = my.Class({

  /* standard constructor */

  constructor: function(options) {

    echo("[WiFiController] constructing...");

    /* WiFi status, false === not connected */

    this.status     = false;

    /* WiFi networks, the list of available networks that we can join */

    this.networks   = [];

    /* the count down for when the next scan is happening */

    this.nextScan   = 5;
    this.scanning   = false;
    this.updating   = false;
    this.suspendBG  = false;  /* when we want foreground work to have priority */

    /* our configuration */

    this.options    = {

      id:      false

    };

    /* merge in options */

    $.extend(this.options, options);

    /*
     * At this point we *could* render the panel, but if there isn't a WiFI connection, the
     * needs to connection version of the panel will need the wifi list, so we have to
     * scan first.
     *
     * NOTE: we do this in parallel, and if its not done before they pop up the wifi panel,
     * and there is no current connect...then they will just see no connections with a note
     * on when the next scan will be.
     *
     * We don't want scanning to block the GUI, we want it to be a background thing that
     * just happens, so their network list is always up to date (within some amount of time).
     *
     */

    /* do scans in the background */

    window.setTimeout(function() {
      App.WiFiController.scanCountDown.call(App.WiFiController);
    }, 1000);

    /* do one right now, so we have a list to work with as soon as possible. */

    this.scan.call(this);

  },

  /**
   *
   * scanCountDown() - helper to invoke in the background a wifi network scan every 5sec or so.
   *
   */

  scanCountDown: function() {

    if(!this.scanning) {
      this.nextScan = this.nextScan - 1;
    }

    if (this.nextScan == 0) {

      this.nextScan = 5;

      /* do a scan */

      this.scan.call(this);

    }

    window.setTimeout(function() {
      App.WiFiController.scanCountDown.call(App.WiFiController);
    }, 1000);
  },

  /**
   *
   * scan() - in the background we update the available wifi networks.
   *
   */

  scan: function() {

    if(this.scanning || this.updating || this.suspendBG) {

      /* don't overlap */

      return true;
    }

    this.scanning = true;

    /* ask the server for an update ... */

    var instance  = this;

    $.ajax({

      url: '/rest/wifi/networks',

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        instance.scanning = false;

        echo('[WiFiController] ERROR problem fetching networks [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        instance.scanning = false;

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[WiFiController] there was a problem fetching the WIFI networks: " + data.message);


          return false;
        }

        instance.networks = data;

        if(this.suspendBG) {

          echo("[WiFiController] networks updated (but skipping becuase background work is suspended.");

        } else {

          /* update the panel */

          instance.render.call(instance);

          /*
           * also do an update to ensure we know if we are connected or not and to refresh the
           * connections status.
           *
           */

          instance.update.call(instance);
        }

        return true;
      }

    });

  },

  /**
   *
   * update() - fetch the WIFI status from the server and re-render the WiFi panel as needed.
   *
   */

  update: function() {

    if(this.updating || this.scanning || this.suspendBG) {

      /* already updating */

      return ;
    }

    this.updating = true;

    var instance = this;

    $.ajax({

      url: '/rest/wifi/status',

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        instance.updating = false;

        echo('[WiFiController] ERROR problem fetching info [' + textStatus + '] ' + errorThrown);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        instance.updating = false;

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[WiFiController] there was a problem fetching the WIFI status: " + data.message);

          return false;
        }

        instance.status = data;

        if(this.suspendBG) {

          echo("[WiFiController] can update panel, but skipping, background work suspended.");

        } else {

          /* get the initial rendering of the panel done */

          instance.render.call(instance);
        }
        return true;

      }

    });

  },

  /**
   *
   * render() - update the WiFI panel
   *
   */

  render: function() {

    /* figure out the panel we are rendering to... */

    var body = $(this.options.id).find('.panel-body');
    var html = "";

    if(!$(body).is('div')) {
      echo("[WiFiController] skipping, no panel body.");
      return true;
    }

    /* build the panel */

    if(this.status.connected == true) {

      html = this.renderConnected.call(this);

    } else {

      html = this.renderJoin.call(this);
    }

    /* render! */

    $(body).html(html);

    /* add any behaviors that are needed ... */

    if(this.status.connected == true) {

      /* actions when already connected */

      $('#wifi-disconnect-action').click(this, function(event) {

        var instance = event.data;

        instance.disconnect.call(instance);

      });

    } else {

      /* actions when you want to connect */

      $('.wifi-join-action').click(function(event) {

        /* which one? */

        var bssid = $(this).parents('li').data('bssid');

        App.WiFiController.join.call(App.WiFiController, bssid);

      });

      $('.wifi-forget-action').click(function(event) {

        /* which one? */

        var bssid = $(this).parents('li').data('bssid');

        App.WiFiController.forget.call(App.WiFiController, bssid);

      });

    }

    /* let other components know that the WiFi status has (maybe) changed */

    EventBus.dispatch('wifi-status-changed', this);
  },

  findNetwork: function(bssid) {

    var network = false;

    for (var property in this.networks) {

      if (!this.networks.hasOwnProperty(property)) {
        continue;
      }

      if(this.networks[property].bssid == bssid) {
        network = this.networks[property];
        break;
      }

    } /* end of network walk */

    return network;
  },

  /**
   *
   * join() - join a network!
   *
   * @param bssid the binary ssid (key) of the network in focus.
   *
   */

  join: function(bssid) {

    var instance = this;
    var network  = this.findNetwork.call(this, bssid);

    if(!is_object(network)) {

      echo("[WiFiController] join: can not find " + bssid);

      return false;
    }

    echo("[WiFiController] joining " + bssid + ": " + network.ssid);

    /* time to join, but if its no public, and we don't have a password yet...we have to grab a password */

    if((!network.secured) || !empty(network.password)) {

      /* we can just join now */

      this.joinWithPassword.call(this, network.ssid, network.password);

      return true;
    }

    /* pop the password dialog... */

    echo("[WiFiController] fresh password is required.");

    var dialog = $('#wifi-password-dialog');

    /* fresh password, don't let them continue with join unless its non-empty */

    $('#wifi-password-input').val('');

    $('#wifi-password-accept').attr("disabled", "disabled");

    $('#wifi-password-input').on('change keyup paste', function() {

      if(!empty($(this).val())) {
        $('#wifi-password-accept').removeAttr("disabled");
      } else {
        $('#wifi-password-accept').attr("disabled", "disabled");
      }

    });

    $('#wifi-password-dialog').on('shown.bs.modal', function () {
      $('#wifi-password-input').focus()
    });

    /* pop up */

    $(dialog).modal({
      backdrop: true,
      focus:    true,
      show:     true,
      keyboard: true
    });

    /* override positioning */

    $(dialog).on('shown.bs.modal', function (event) {

      $(dialog).css('position', 'absolute');
      $(dialog).css('width', '720px');
      $(dialog).css('max-width', '720px');
      $(dialog).css('top', '24px');
      $(dialog).css('left', '24px');
      $(dialog).css('right', '24px');
      $(dialog).css('bottom', '40px');

      $('.modal-dialog').css('margin-top', '-10px');
      $('.modal-dialog').css('width', '720px');
      $('.modal-dialog').css('max-width', '720px');
    });

    /* react when the enter the password */

    $('#wifi-password-accept').unbind('click');
    $('#wifi-password-accept').bind('click', function(event) {

      var password = $('#wifi-password-input').val();

      $(dialog).modal('hide');

      instance.joinWithPassword.call(instance, network.ssid, password);

    });

    $('#wifi-password-input').on('keyup', function (e) {

      if (e.keyCode == 13) {

        var password = $(this).val();

        if(!empty(password)) {
          $('#wifi-password-accept').click();
        }
      }

    });

    /* allow a virtual keyboard, so that they can type without actually having a keyboard (i.e. on the RPI) */

    $(dialog).on('shown.bs.modal', function(event) {

      $('#wifi-password-input').keyboard({
        layout: 'qwerty',
        autoAccept: true,
        css: {
          // input & preview
          input: 'form-control input-sm',

          // keyboard container
          container: 'center-block dropdown-menu', // jumbotron
          // default state
          buttonDefault: 'btn btn-default',
          // hovered button
          buttonHover: 'btn-primary',
          // Action keys (e.g. Accept, Cancel, Tab, etc);
          // this replaces "actionClass" option
          buttonAction: 'active',
          // used when disabling the decimal button {dec}
          // when a decimal exists in the input area
          buttonDisabled: 'disabled'
        },

        appendTo: $('#wifi-password-input').parents('.row'),

        // Used by jQuery UI position utility
        position: {
          // null = attach to input/textarea;
          // use $(sel) to attach elsewhere
          of:  $('#wifi-password-input').parents('.row'),
          my: 'center top',
          at: 'center top',
          // used when "usePreview" is false
          at2: 'center bottom'
        },
        usePreview: false,

        accepted: function(e, keyboard, el) {

          var password = $('#wifi-password-input').val();

          if(!empty(password)) {
            $('#wifi-password-accept').removeAttr("disabled");
            $('#wifi-password-accept').click();
          }

        },

        beforeInsert: function(e, keyboard, el, txt) {

          return txt;
        }
      })

        .addCaret({
          // extra class name added to the caret
          // "ui-keyboard-caret" class is always added
          caretClass : 'blinking-cursor',
          // *** for future use ***
          // data-attribute containing the character(s) next to the caret
          charAttr   : 'data-character',
          // # character(s) next to the caret (can be negative for RTL)
          charIndex  : 1,

          // *** caret adjustments ***
          // adjust horizontal position (pixels)
          offsetX    : 0,
          // adjust vertical position (pixels); also adjust margin-top in css
          offsetY    : 0,
          // adjust caret height (pixels)
          adjustHt   : 0
        })

      // activate the typing extension
        .addTyping({
          showTyping: true,
          delay: 250,
          hoverDelay: 250
        });
    }).on('hide.bs.modal', '.modal', function() {
      // remove keyboards to free up memory
      $('#wifi-password-input').each(function() {
        $(this).data('keyboard').destroy();
      });
    });

    $.keyboard.keyaction.enter = function(base) {
      base.accept();
    }

  },

  /**
   *
   * joinWithPassword
   *
   * @param ssid     id of the network to join
   * @param password password for the network to join (i.e. WEP/WPA/WPA2)
   *
   */

  joinWithPassword: function(ssid, password) {

    echo("[WiFiController] join attempt: " + ssid + ":" + password);

    /* pause user action until we're done either way */

    lock_page();

    var instance   = this;
    this.suspendBG = true;

    $.ajax({

      url: '/rest/wifi/join',

      method: 'POST',

      data: {
        ssid: ssid,
        password: password
      },

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        echo('[WiFiController] ERROR problem joining [' + textStatus + '] ' + errorThrown);

        unlock_page();

        this.suspendBG = false;

        /* don't just re-render, get an updated status, since things might be messy now. */

        instance.update.call(instance);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[WiFiController] there was a problem joining: " + data.message);

          /* show an alert... */

          info_message("Problem Joining '" + ssid + "'", data.message);

          /* do a full update, since things may now be message */

          this.suspendBG = false;

          instance.update.call(instance);

          return false;
        }

        instance.status = data;

        this.suspendBG  = false;

        instance.render.call(instance);

        return true;

      }

    });

  },

  /**
   *
   * forget() - stronger version of disconnecting, also forget any stored password.
   *
   * @param bssid the binary ssid (key) of the network in focus.
   *
   */

  forget: function(bssid) {

    var instance = this;
    var network  = this.findNetwork.call(this, bssid);

    if(!is_object(network)) {

      echo("[WiFiController] forget: can not find " + bssid);

      return false;
    }

    echo("[WiFiController] forgetting " + bssid + ": " + network.ssid);

    /* block user actions until this completes (one way or the other) */

    lock_page();

    this.suspendBG = true;

    /* do it */

    var instance = this;

    $.ajax({

      url: '/rest/wifi/forget',

      method: 'POST',

      data: {
        ssid: network.ssid
      },

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        echo('[WiFiController] ERROR problem forgetting [' + textStatus + '] ' + errorThrown);

        unlock_page();

        this.suspendBG = false;

        /* don't just re-render, get an updated status, since things might be messy now. */

        instance.update.call(instance);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[WiFiController] there was a problem forgetting: " + data.message);

          this.suspendBG = false;

          return false;
        }

        instance.status = data;

        this.suspendBG = false;

        unlock_page();

        instance.render.call(instance);

        return true;
      }

    });

  },

  /**
   *
   * renderConnected() - panel rendering when connected
   *
   * @returns {string}
   *
   */

  renderConnected: function() {

    /* layout the status details */

    var html    = "";
    var secured = "NO";
    var freq    = (parseFloat(this.status.freq) / 1000).toFixed(4);

    if(this.status.secured) {
      secured = "YES";
    }

    html = html + "<span><h3 class=\"fw-thin leftcol\">Network:</h3><h3 style=\"overflow: hidden;\" class=\"fw-semibold rightcol\">" + this.status.ssid + "</h3></span>";
    html = html + "<span><h3 class=\"fw-thin leftcol\">MAC:</h3><h3 class=\"fw-semibold rightcol\">" + this.status.mac + "</h3></span>";
    html = html + "<span><h3 class=\"fw-thin leftcol\">IP:</h3><h3 class=\"fw-semibold rightcol\">" + this.status.ip + "</h3></span>";
    html = html + "<span><h3 class=\"fw-thin leftcol\">Frequency:</h3><h3 class=\"fw-semibold rightcol\">" + freq + " Ghz</h3></span>";
    html = html + "<span><h3  class=\"fw-thin leftcol\">Secured:</h3><h3 class=\"fw-semibold rightcol\">" + secured + "</h3></span>";

    html = html + "<span><h3 class=\"fw-thin leftcol\">Strength:</h3>";
    html = html + "<h3 class=\"fw-semibold rightcol\"><i style=\"color: " + this.status.dBm.color + ";\" class=\"fa fa-wifi\" aria-hidden=\"true\"></i>";
    html = html + "&nbsp;" + this.status.dBm.dBm + " dBm";
    html = html + "&nbsp;(" + this.status.dBm.percent + "%)</h3>";
    html = html + "</span>";

    html = html + "<h3 class=\"fw-thin leftcol\">Features:</h3>";

    html = html + "<h3 class=\"fw-thin rightcol\">";

    for(var i=0; i<this.status.features.length; i++) {
      html = html + this.status.features[i] + " &nbsp; &nbsp;";
    }
    html = html + "</h3>";

    /* finally the disconnect button to allow them to actually leave the network if they want to. */

    html = html + "<button style=\"margin-top: 20px; margin-left: 20px;\" type=\"button\" id=\"wifi-disconnect-action\" class=\"btn btn-danger\">Disconnect</button>"

    return html;
  },

  /**
   *
   * renderJoin() - panel rendering when you want to connect
   *
   * @returns {string}
   *
   */

  renderJoin: function() {

    var html     = "";

    var html = html + "<ul class=\"wifi-connections list-group\">";

    for (var property in this.networks) {

      if(!this.networks.hasOwnProperty(property)) {
        continue;
      }

      var network  = this.networks[property];
      var ssid     = network.ssid;
      var bssid    = network.bssid;
      var password = network.password;
      var secured  = network.secured;
      var freq     = network.freq;
      var dBm      = network.dBm;

      /* data() */

      var attr     = "";

      if(secured) {
        attr = attr + " data-secured=\"true\"";
      } else {
        attr = attr + " data-secured=\"false\"";
      }

      attr = attr + " data-bssid=\"" + bssid + "\"";

      /* build the html for this network */

      html = html + "<li " + attr + " class=\"list-group-item\">";

      if(secured) {
        html = html + "<i style=\"color: green;\" class=\"fa fa-lock\" aria-hidden=\"true\"></i>";
      } else {
        html = html + "<i style=\"color: red;\" class=\"fa fa-unlock\" aria-hidden=\"true\"></i>";
      }

      html = html + "<i style=\"color: " + dBm.color + ";\" class=\"fa fa-wifi\" aria-hidden=\"true\"></i>";
      html = html + "&nbsp;[" + dBm.percent + "%]&nbsp;<span class=\"fw-semibold\">" + ssid + "</span>";

      /* add the action buttons for this net work */

      html = html + "<span class=\"wifi-list-action-buttons\">";
      html = html + "<button type=\"button\" class=\"wifi-join-action btn btn-primary\">JOIN</button>";
      if(!empty(password)) {
        html = html + "<button type=\"button\" class=\"wifi-forget-action btn btn-warning\">FORGET</button>";
      }
      html = html + "</span>";

      html = html + "</li>";

    }

    var html = html + "</ul>";

    return html;

  },

  /**
   *
   * disconnect() the user wants to drop the current network, so they can join another...
   *
   */

  disconnect: function() {

    echo("[WiFiController] disconnecting...");

    /* block user actions until this completes (one way or the other) */

    lock_page();

    this.suspendBG = true;

    /* do it */

    var instance   = this;

    $.ajax({

      url: '/rest/wifi/disconnect',

      method: 'POST',

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        echo('[WiFiController] ERROR problem disconnecting [' + textStatus + '] ' + errorThrown);

        unlock_page();

        this.suspendBG = false;

        /* don't just re-render, get an updated status, since things might be messy now. */

        instance.update.call(instance);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[WiFiController] there was a problem disconnecting: " + data.message);

          this.suspendBG = false;

          return false;
        }

        instance.status = data;

        this.suspendBG  = false;

        unlock_page();

        instance.render.call(instance);

        return true;
      }

    });

  }

});