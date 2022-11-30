/**
 *
 * Login button handles user session (login at the main site)
 *
 */

var LoginButton = my.Class(Button, {

  constructor: function(options) {

    options.name        = 'Login';
    options.enableColor = '#fff';
    options.bubble      = "Authenticate and view your licenses";
    options.id          = Button.MOD_LOGIN;
    options.panelid     = '#login-panel';

    options.autoStatus  = "NO_ATTEMPT";

    options.onClick     = function(event) {

      echo("[LoginButton] toggling panel...");

      LoginButton.Super.prototype.pop.call(event.data);

    };

    LoginButton.Super.call(this, options);

    /*
     * start out assuming we're logged out, once the user has logged in, then we're super green.
     *
     */

    EventBus.dispatch('logout', this);

    /*
     * this button starts out disabled, we can't attempt to login until WiFI is up.
     *
     */

    LoginButton.Super.prototype.disable.call(this);

    EventBus.dispatch('login-actions-disabled', this);

    EventBus.addEventListener('login-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      if(!status.connected) {

        /* login failed */

        $(this.options.id).css('background-color', 'red');

        EventBus.dispatch('post-login-actions-disabled', this);

        App.LoginButton.options.enableColor = '#fff';

      } else {

        /* login was ok! */

        EventBus.dispatch('post-login-actions-enabled', this);

        App.LoginButton.options.enableColor = '#00ff00';

        App.LoginButton.loginOK.call(App.LoginButton);

      }

    }, this);

    /* set up the Login controller and listen for updates to the Login status... */

    echo("[LoginButton] making controller...");

    App.LoginController = new LoginController({
      id: options.panelid
    });

    /* wait for WiFi status updates... */

    echo("[LoginButton] waiting on WiFi...");

    EventBus.addEventListener('wifi-status-changed', function(event) {

      var type       = event.type;
      var controller = event.target;
      var status     = controller.status;

      /*
       * when the WiFi status changes to connected, then we can allow login.
       *
       */

      if(!status.connected) {

        LoginButton.Super.prototype.disable.call(this);

        /* auto-logout when wifi drops */

        App.LoginController.logout.call(App.LoginController);
        
        EventBus.dispatch('login-actions-disabled', this);

        App.LoginButton.options.autoStatus = "NO_ATTEMPT";

        return ;
      }

      LoginButton.Super.prototype.enable.call(this);

      EventBus.dispatch('login-actions-enabled', this);

      /*
       * when networking comes up, try to login as the current user, if we can't figure out the current user,
       * then nothing happens, and they have to manually login (as usual). Auto-login confirms the user's
       * credentials with the main site, so even if they got banned or something, we're good, they can't auto-login
       * unless they are ok.
       *
       */

      if(App.LoginButton.options.autoStatus == "NO_ATTEMPT") {

        App.LoginButton.options.autoStatus = "ATTEMPTED";

        App.LoginController.autologin.call(this);
      }

    }, this);

  },

  /**
   *
   * loginOK() - helper to make sure the button is updated in appearance/ability once we login ok.
   *
   */

  loginOK: function() {

    $(this.options.id).css('background-color', '#00ff00');

  },

  /**
   *
   * logoutOK() - helper to make sure the button is updated in appearance/ability once we logout ok.
   *
   */

  logoutOK: function() {

    $(this.options.id).css('background-color', '#fff');

  }

});