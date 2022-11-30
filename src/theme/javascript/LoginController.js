/**
 *
 * LoginController is our base class for establishing a user session with the main site.
 * Basically login credentials.
 *
 */

var LoginController = my.Class({

  /* standard constructor */

  constructor: function(options) {

    echo("[LoginController] constructing...");

    /* Login status, false === not connected */

    this.status   = {
      connected: false
    };

    /* our configuration */

    this.options  = {

      id:      false

    };

    /* merge in options */

    $.extend(this.options, options);

    /* do the initial rendering */

    this.render.call(this);

  },

  /**
   *
   * render() - update the Login panel
   *
   */

  render: function() {

    echo("[LoginController] rendering...");

    /* figure out the panel we are rendering to... */

    var body = $(this.options.id).find('.panel-body');
    var html = "";

    if(!$(body).is('div')) {
      echo("[LoginController] skipping, no panel body.");
      return true;
    }

    /* build the panel */

    if(this.status.connected == true) {

      html = this.renderConnected.call(this);

      /* when connected, make sure the login button shows that we're good... */

      App.LoginButton.loginOK.call(App.LoginButton);

    } else {

      html = this.renderLogin.call(this);
    }

    /* render! */

    $(body).html(html);

    /* add any behaviors that are needed ... */

    if(this.status.connected == true) {

      /* actions when already connected */

      $('#login-logout-action').click(this, function(event) {

        var instance = event.data;

        instance.logout.call(instance);

      });

    } else {

      /* actions when you want to connect */

      $('#login-login-action').click(function(event) {

        var instance = event.data;

        /* we want to login, but first we need the userid/password... */

        echo("[LoginController] fresh password is required.");

        var dialog = $('#login-password-dialog');

        /* fresh password, don't let them continue with login unless its non-empty */

        $('#login-userid-input').val('');
        $('#login-password-input').val('');

        $('#login-password-accept').attr("disabled", "disabled");

        $('.login-form-input').on('change keyup paste', function() {

          var u = $('#login-userid-input').val();
          var p = $('#login-password-input').val();

          if(!empty(u) && !empty(p)) {
            $('#login-password-accept').removeAttr("disabled");
          } else {
            $('#login-password-accept').attr("disabled", "disabled");
          }

        });

        $('#login-password-dialog').on('shown.bs.modal', function () {
          $('#login-userid-input').focus()
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

        $('#login-password-accept').unbind('click');
        $('#login-password-accept').bind('click', function(event) {

          var userid   = $('#login-userid-input').val();
          var password = $('#login-password-input').val();

          $(dialog).modal('hide');

          App.LoginController.login.call(App.LoginController, userid, password);

        });

        $('.login-form-input').on('keyup', function (e) {

          if (e.keyCode == 13) {

            var u = $('#login-userid-input').val();
            var p = $('#login-password-input').val();

            if(!empty(u) && !empty(p)) {
              $('#login-password-accept').click();
            }
          }

        });

        /* allow a virtual keyboard, so that they can type without actually having a keyboard (i.e. on the RPI) */

        $(dialog).on('shown.bs.modal', function(event) {

          $('.login-form-input').keyboard({
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

            appendTo: $('#login-userid-input').parents('.row'),

            // Used by jQuery UI position utility
            position: {
              // null = attach to input/textarea;
              // use $(sel) to attach elsewhere
              of: $('#login-userid-input').parents('.row'),
              my: 'center top',
              at: 'center top',
              // used when "usePreview" is false
              at2: 'center bottom'
            },
            usePreview: false,

            accepted: function(e, keyboard, el) {

              var u = $('#login-userid-input').val();
              var p = $('#login-password-input').val();

              if(!empty(u) && !empty(p)) {
                $('#login-password-accept').removeAttr("disabled");
                $('#login-password-accept').click();
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

      });

    }

  },

  /**
   *
   * renderConnected() - panel rendering when logged in already
   *
   * @returns {string}
   *
   */

  renderConnected: function() {

    /* layout the status details */

    var html = "";

    var name = this.status.name;

    if(empty(name)) {
      name = this.status.first_name + " " + this.status.last_name;
    }

    var userid = this.status.userid;
    var email  = this.status.email;
    var phone  = this.status.phone;
    var mobile = this.status.mobile;
    var incept = this.status.created_at.date;

    html = html + "<span><h3 class=\"fw-thin leftcol\">Userid:</h3><h3 class=\"fw-semibold rightcol\">" + userid + "</h3></span>";
    html = html + "<span><h3 class=\"fw-thin leftcol\">eMail:</h3><h3 class=\"fw-semibold rightcol\">" + email + "</h3></span>";
    html = html + "<span><h3 class=\"fw-thin leftcol\">Phone:</h3><h3 class=\"fw-semibold rightcol\">" + phone + "</h3></span>";
    html = html + "<span><h3 class=\"fw-thin leftcol\">Mobile:</h3><h3 class=\"fw-semibold rightcol\">" + mobile + "</h3></span>";
    html = html + "<span><h3 class=\"fw-thin leftcol\">Inception:</h3><h3 class=\"fw-semibold rightcol\">" + incept + "</h3></span>";

    /* finally the disconnect button to allow them to actually leave the network if they want to. */

    html = html + "<button style=\"clear: both; float: left; margin-top: 20px; margin-left: 20px;\" type=\"button\" id=\"login-logout-action\" class=\"btn btn-danger\">LOGOUT</button>"

    return html;
  },

  /**
   *
   * renderLogin() - panel rendering when you want to login
   *
   * @returns {string}
   *
   */

  renderLogin: function() {

    var html     = "";

    html = html + "<p style=\"margin-top: 20px; margin-left: 20px; margin-right: 20px;\">Before you can view or activate your ECU flash license(s), you must login.  If you do not yet ";
    html = html + "have an account, please visit <strong>https://flash.precisionefi.com</strong> for FREE registration.";
    html = html + "</p>";

    html = html + "<button style=\"margin-top: 20px; margin-left: 20px;\" type=\"button\" id=\"login-login-action\" class=\"btn btn-primary\">LOGIN</button>"


    return html;
  },

  /**
   *
   * logout() - drop the current user session
   *
   */

  logout: function() {

    echo("[LoginController] Logging out...");

    /* we don't maintain a session other than what we have in this GUI, so just drop the credentials */

    this.status = {
      connected: false
    };

    this.render.call(this);

    /* let other components know that the login status has (maybe) changed */

    echo("[LoginController] notifying login (out) change.");

    EventBus.dispatch('login-status-changed', this);

    /* update appearance of the login button */

    App.LoginButton.logoutOK.call(App.LoginButton);

    return true;
  },

  /**
   *
   * autologin() - when we first load the GUI (i.e. after a refresh) we can try to re-use whatever user was in play
   * just before, that is...the user "session" (server side it gets stored in PHP session).
   *
   */

  autologin: function() {

    echo("[LoginController] attempting auto-login...");

    /* pause user action until we're done either way */

    lock_page();

    $.ajax({

      url: '/rest/pefi/autologin',
      method: 'GET',
      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        echo('[LoginController] can not do auto-login [' + textStatus + '] ' + errorThrown);

        unlock_page();

        App.LoginController.status = {
          connected: false
        };

        /* let other components know that the login status has (maybe) changed */

        echo("[LoginController] notifying login (in) change.");

        EventBus.dispatch('login-status-changed', App.LoginController);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[LoginController] could not do auto-login: " + data.message);

          return false;
        }

        /* if auto-login actually worked then update the login panel */

        App.LoginController.status = data;

        App.LoginController.render.call(App.LoginController);

        /* let other components know that the login status has (maybe) changed */

        echo("[LoginController] notifying login (in) change.");

        EventBus.dispatch('login-status-changed', App.LoginController);

        return true;

      }

    });

  },

  /**
   *
   * login() - actually do a login at the main site, this should result in a user profile object.
   *
   * @param userid   the user's userid
   * @param password the user's password
   *
   */

  login: function(userid, password) {

    echo("[LoginController] attempting main site login: " + userid + ":" + password);

    /* pause user action until we're done either way */

    lock_page();

    var instance = this;

    $.ajax({

      url: '/rest/pefi/login',

      method: 'POST',

      data: {
        userid: userid,
        password: password
      },

      dataType: 'json',

      error: function (jqXHR, textStatus, errorThrown) {

        echo('[LoginController] ERROR problem logging in [' + textStatus + '] ' + errorThrown);

        unlock_page();

        instance.status = {
          connected: false
        };

        instance.render.call(instance);

        /* let other components know that the login status has (maybe) changed */

        echo("[LoginController] notifying login (in) change.");

        EventBus.dispatch('login-status-changed', instance);

        return false;
      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        if (isset(data.status) && (data.status == "ERROR")) {

          echo("[LoginController] there was a problem logging in: " + data.message);

          /* show an alert... */

          info_message("Problem Logging in '" + userid + "'", data.message);

          /* do a full update, since things may now be message */

          instance.render.call(instance);

          return false;
        }

        instance.status = data;

        instance.render.call(instance);

        /* let other components know that the login status has (maybe) changed */

        echo("[LoginController] notifying login (in) change.");

        EventBus.dispatch('login-status-changed', instance);

        return true;

      }

    });


  }
});