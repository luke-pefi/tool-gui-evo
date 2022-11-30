
/* App is our global application container */

var App    = {};

/**
 *
 * lock_page() - stop user input until we complete a sequential action
 *
 */

function lock_page() {

  $(".page-content").LoadingOverlay("show", {
    image       : "",
    fontawesome : "fa fa-spinner fa-spin"
  });

}

/**
 *
 * unlock_page() - sequential action completed, allow user input again
 *
 */

function unlock_page() {

  $(".page-content").LoadingOverlay("hide", {

  });

}

/**
 *
 * info_message() - convenience to pop an information dialog
 *
 */

function info_message(title, message) {

  var dialog = $('#info-message-dialog');

  $('#info-message-title').html(title);
  $('#info-message-content').html(message);

  $(dialog).modal({
    backdrop: true,
    focus:    true,
    show:     true,
    keyboard: true
  });
}

/**
 *
 * init_page() - should be called on page load for all our theme pages.
 *
 */

function init_page() {

  echo("[init] page is loaded, setting up behaviors...");

  /* install the top navigation */

  App.TopNavigator = new TopNavigator({});

  /* install the bottom nav bar buttons */

  App.RefreshButton  = new RefreshButton({});

  App.ActivateButton = new ActivateButton({});
  App.FlashButton    = new FlashButton({});
  App.LoginButton    = new LoginButton({});
  App.WiFiButton     = new WiFiButton({});
  App.CANBusButton   = new CANBusButton({});
  App.PefiButton     = new PefiButton({});

  /* clicking the collapse button on any panel should roll it down */

  $('.panel-collapse').click(function(e) {

    var parent = $('.panel-collapse').parents('.panel');

    $(parent).animate({
        'max-height': '0px'
      },
      400
    );

  });

  /* on the support tab allow them to upload the logs to Precision EFI's dropbox ... */

  $('button.send-logs-action').click(function (){

    echo("[support] sending logs...");

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

        var container = $('div.support-error-message');

        $(container).html("<p style=\"margin-top: 16px; margin-left: 16px;\">" + textStatus + "</p>");

      },

      success: function (data, textStatus, jqXHR) {

        unlock_page();

        var container = $('div.support-error-message');

        if(isset(data.status) && data.status == "ERROR") {
          $(container).html("<p style=\"margin-top: 16px; margin-left: 16px; color: #ff9999;\">" + data.message + "</p>");
          return ;
        }

        $(container).html("<p style=\"margin-top: 16px; margin-left: 16px; color: #99ff99;\">" + data.message + "</p>");

      }

    });

  });

  /* on the support tab they can launch a (password login) terminal for remote support access */

  $('button.start-terminal-action').click(function (){

    echo("[support] launching terminal...");

    $.ajax({
      url: '/rest/support/terminal',
      method: 'GET'
    });

  });

  /* fetch the TeamViewer Partner ID */

  $.ajax({

    url: '/rest/support/partnerid',
    method: 'GET',
    dataType: 'json',

    success: function (data, textStatus, jqXHR) {

      var partnerID = data.partnerid;
      $('button.team-viewer-action label').text("Partner ID: " + partnerID);
    }
  });



}