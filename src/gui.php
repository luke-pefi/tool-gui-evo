<html>

<head>

  <meta charset="utf-8">

  <title>Precision EFI</title>

  <meta name="description" content="Rasperry PI GUI">
  <meta name="title" content="Precision EFI">
  <meta name="author" content="littlemdesign">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- tutorial on favicon: http://www.jonathantneal.com/blog/understand-the-favicon -->

  <link rel="apple-touch-icon-precomposed" href="/src/theme/images/apple-precomposed.png">
  <link rel="icon" type="image/x-icon" href="/src/theme/images/favicon.ico?v=2">
  <meta name="msapplication-TileColor" content="#D83434">
  <meta name="msapplication-TileImage" content="/src/theme/images/tileicon.png">

  <link href="/src/theme/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/src/theme/vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <link href="/src/theme/fonts/localfonts.css" rel="stylesheet">
  <link href="/src/theme/css/burgerbutton.css" rel="stylesheet">
  <link href="/src/theme/vendor/x-editable/dist/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet">
  <link href="/src/theme/css/validation.css" rel="stylesheet">
  <link href="/src/theme/vendor/jquery-autocomplete/jquery.auto-complete.css" rel="stylesheet">
  <link href="/src/theme/vendor/keyboard/dist/css/keyboard.min.css" rel="stylesheet">
  <link href="/src/theme/vendor/keyboard/dist/css/keyboard-previewkeyset.min.css" rel="stylesheet">
  <link href="/src/theme/css/theme.css" rel="stylesheet">

  <script src="/src/theme/javascript/jquery.js"></script>
  <script src="/src/theme/vendor/tether/dist/js/tether.min.js"></script>
  <script src="/src/theme/vendor/bootstrap/js/bootstrap.min.js"></script>
  <script src="/src/theme/vendor/x-editable/dist/bootstrap3-editable/js/bootstrap4-editable.js"></script>
  <script src="/src/theme/vendor/jquery-autocomplete/jquery.auto-complete.min.js"></script>
  <script src="/src/theme/vendor/keyboard/dist/js/jquery.keyboard.min.js"></script>
  <script src="/src/theme/vendor/keyboard/dist/js/jquery.keyboard.extension-all.min.js"></script>
  <script src="/src/theme/vendor/my-class/my.class.js"></script>
  <script src="/src/theme/vendor/event-bus/event-bus-min.js"></script>
  <script src="/src/theme/vendor/jquery-loading-overlay/src/loadingoverlay.min.js"></script>
  <script src="/src/theme/vendor/EventSource/src/eventsource.min.js"></script>
  <script src="/src/theme/javascript/util.js"></script>
  <script src="/src/theme/javascript/bottomnav.js"></script>
  <script src="/src/theme/javascript/TopNavigator.js"></script>
  <script src="/src/theme/javascript/WiFiController.js"></script>
  <script src="/src/theme/javascript/LoginController.js"></script>
  <script src="/src/theme/javascript/CANBusController.js"></script>
  <script src="/src/theme/javascript/Button.js"></script>
  <script src="/src/theme/javascript/RefreshButton.js"></script>
  <script src="/src/theme/javascript/WiFiButton.js"></script>
  <script src="/src/theme/javascript/LoginButton.js"></script>
  <script src="/src/theme/javascript/CANBusButton.js"></script>
  <script src="/src/theme/javascript/ActivateButton.js"></script>
  <script src="/src/theme/javascript/FlashButton.js"></script>
  <script src="/src/theme/javascript/PefiButton.js"></script>
  <script src="/src/theme/javascript/theme.js"></script>

</head>

  <body>

    <div class="page-wrapper">

      <div class="page-content">

        <!-- top nav bar -->

        <div class="top-nav-container">

          <div class="wifi-comp">
          </div>

          <div class="login-comp">
          </div>

          <div class="canbus-comp">
          </div>

          <div class="temp-comp">
          </div>

        </div>

        <!-- bottom nav bar -->

        <div class="bottom-nav-wrapper">
          <div class="bottom-nav-container">

            <div id="btn-wifi" style="background-image: url(/src/theme/images/wifi_button_mask.png);" class="nav-button-wrapper"></div>

            <div id="btn-login" style="background-color: #515151; background-image: url(/src/theme/images/login_button_mask.png);" class="nav-button-wrapper"></div>

            <div id="btn-canbus" style="background-color: #515151; background-image: url(/src/theme/images/canbus_button_mask.png);" class="nav-button-wrapper"></div>

            <div id="btn-activate" style="background-color: #515151; background-image: url(/src/theme/images/activate_button_mask.png);" class="nav-button-wrapper"></div>

            <div id="btn-flash" style="background-color: #515151; background-image: url(/src/theme/images/flash_button_mask.png);" class="nav-button-wrapper"></div>

            <div id="btn-refresh" style="background-color: #fff; background-image: url(/src/theme/images/refresh_button_mask.png);" class="nav-button-wrapper"></div>

            <div id="btn-pefi" style="background-color: #fff; float: right; margin-right: 0px;" class="nav-button-wrapper">

            </div>

            <div id="btn-pefi" class="version-label">
              <span style="color: grey;">ver.</span>&nbsp;&nbsp;<b>
                <?php

                echo file_get_contents(dirname(dirname(dirname(dirname(__FILE__))))."/VERSION");

                ?>
              </b>
            </div>

          </div>
        </div>

        <div style="max-height: 0px;" id="wifi-panel" class="panel">

          <div class="panel-header">
            <i class="fa fa-chevron-down panel-collapse"></i>
            <h4>WIFI</h4>
          </div>

          <div class="panel-body">

          </div>

        </div>

        <div style="max-height: 0px;" id="login-panel" class="panel">

          <div class="panel-header">
            <i class="fa fa-chevron-down panel-collapse"></i>
            <h4>LOGIN</h4>
          </div>

          <div class="panel-body">

          </div>

        </div>

        <div style="max-height: 0px;" id="canbus-panel" class="panel">

          <div class="panel-header">
            <i class="fa fa-chevron-down panel-collapse"></i>
            <h4>CAN Bus</h4>
          </div>

          <div class="panel-body">

          </div>

        </div>

        <div style="max-height: 0px;" id="activate-panel" class="panel">

          <div class="panel-header">
            <i class="fa fa-chevron-down panel-collapse"></i>
            <h4 style="width: 140px;">Activate Licenses</h4>
          </div>

          <div class="panel-body">

          </div>

        </div>

        <div style="max-height: 0px;" id="flash-panel" class="panel">

          <div class="panel-header">
            <i class="fa fa-chevron-down panel-collapse"></i>
            <h4 style="width: 140px;">ECU Flashing</h4>
          </div>

          <div class="panel-body">

          </div>

        </div>

        <div style="max-height: 0px;" id="pefi-panel" class="panel">

          <div class="panel-header">
            <i class="fa fa-chevron-down panel-collapse"></i>
            <h4 style="width: 140px;">Precision EFI</h4>
          </div>

          <div class="panel-body">

            <div id="infocard" class="logo-infocard" style="margin-top: 20px;">
              <div class="custom">
                <div class="row">
                  <div class="col-sm-5">
                    <div class="infocard-wrapper text-center">
                      <p><img src="/src/theme/images/target-56-semi-white.png" alt="Precision EFI"></p>
                      <p style="font-size: 20px; line-height: 24px;">Whatever your project is, <br>
                        If it burns fuel, <br>
                        <strong>We can tune it!</strong></p>
                    </div>
                  </div>
                  <div class="col-sm-7">

                    <div class="custom contact-details">
                      <p style="font-size: 20px; line-height: 24px;"><strong>T (450) 983-3966</strong><br>
                        Email:&nbsp;<a class="email-link-white">sebastien@precisionefi.com</a>
                      </p>
                      <p style="font-size: 20px; line-height: 24px;">PrecisionEFI<br>
                        1016 boulevard Arthur-Sauvé, local J,<br>
                        St-Eustache</p>
                    </div>

                    <div style="height:20px;"></div>

                  </div>
                </div> <!--  row -->
              </div> <!--  custom -->
            </div>

            <div class="support-action-buttons">

              <button style="padding: 0px; margin-left: 16px;" type="button" class="send-logs-action btn btn-primary"><div><img src="/src/theme/images/dropbox-icon-38.png" alt="Send Logs to Dropbox"><label style="margin-left: 8px; margin-right: 8px;">Send Logs</label></div></button>
              <button style="padding: 0px; margin-left: 16px;" type="button" class="start-terminal-action btn btn-primary"><div><img src="/src/theme/images/terminal-icon-38.png" alt="Start Terminal"><label style="margin-left: 8px; margin-right: 8px;">Terminal</label></div></button>
              <button style="padding: 0px; margin-left: 8px;"  type="button" class="team-viewer-action btn btn-primary"><div><img src="/src/theme/images/teamviewer-icon-38.png" alt="Start Terminal"><label style="margin-left: 8px; margin-right: 8px;">Partner ID: </label></div></button>
            </div>

            <div><p style="margin-top: 16px; margin-left: 16px;">When uploading logs, it may take 2-3 minutes.  Please be patient.</p></div>

            <div class="support-error-message"></div>

          </div>

        </div>

      </div> <!-- end of page-content -->

    </div> <!-- end of page wrapper -->

    <!-- WiFi password Modal -->
    <div style="overflow-y: hidden;" class="modal fade" id="wifi-password-dialog" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
      <div style="overflow-y: hidden;" class="modal-dialog col-sm-12" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">WIFI Password Required</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div style="overflow-y: hidden;" class="modal-body">
            <div class="container">
              <div class="row form-group">
                <div class="col-sm-2">
                  <label style="float: left;">Pasword:</label>
                </div>
                <div class="col-sm-10">
                  <input style="float: left;" type="text" value="" class="form-control" id="wifi-password-input" placeholder="Enter Password">
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">CANCEL</button>
            <button type="button" id="wifi-password-accept" class="btn btn-primary">JOIN</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Login password Modal -->
    <div style="overflow-y: hidden;" class="modal fade" id="login-password-dialog" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
      <div style="overflow-y: hidden;" class="modal-dialog col-sm-12" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">User Login Required</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div style="overflow-y: hidden;" class="modal-body">
            <div class="container">
              <div class="row form-group">

                <div class="col-sm-2">
                  <label style="float: left;">Userid:</label>
                </div>
                <div class="col-sm-4">
                  <input style="float: left;" type="text" value="" class="form-control login-form-input" id="login-userid-input" placeholder="Enter Userid">
                </div>

                <div class="col-sm-2">
                  <label style="float: left;">Pasword:</label>
                </div>
                <div class="col-sm-4">
                  <input style="float: left;" type="text" value="" class="form-control login-form-input" id="login-password-input" placeholder="Enter Password">
                </div>

              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">CANCEL</button>
            <button type="button" id="login-password-accept" class="btn btn-primary">JOIN</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Info Message Modal -->
    <div style="overflow-y: hidden;" class="modal fade" id="info-message-dialog" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
      <div style="overflow-y: hidden;" class="modal-dialog col-sm-12" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="info-message-title"></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div style="overflow-y: hidden;" class="modal-body">
            <div class="container">
              <div class="row form-group">
                <div class="col-sm-12" id="info-message-content">

                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    </div>



  </body>
</html>

<script>

  $(function() {

    /* go! */

    init_page();

  });

</script>