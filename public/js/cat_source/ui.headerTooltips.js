import CatToolActions from './es6/actions/CatToolActions'
import CommonUtils from './es6/utils/commonUtils'

$.extend(window.UI, {
  showProfilePopUp: function (openProfileTooltip) {
    if (openProfileTooltip) {
      var self = this
      var tooltipTex =
        "<h4 class='header'>Manage your projects</h4>" +
        "<div class='content'>" +
        '<p>Click here, then "My projects" to retrieve and manage all the projects you have created in Matecat.</p>' +
        "<a class='close-popup-teams'>Next</a>" +
        '</div>'
      $('header #profile-menu')
        .popup({
          on: 'click',
          onHidden: function () {
            $('header #profile-menu').popup('destroy')
            CatToolActions.setPopupUserMenuCookie()
            return true
          },
          html: tooltipTex,
          closable: false,
          onCreate: function () {
            $('.close-popup-teams').on('click', function () {
              $('header #profile-menu').popup('hide')
              self.openPopupThreePoints()
            })
          },
          className: {
            popup: 'ui popup user-menu-tooltip',
          },
        })
        .popup('show')
    } else {
      this.openPopupThreePoints()
    }
  },
  openPopupThreePoints: function () {
    var closedPopup = localStorage.getItem(
      'infoThreeDotsMenu-' + config.userMail,
    )
    if (!closedPopup) {
      var self = this
      var tooltipTex =
        "<h4 class='header'>Easier tool navigation and new shortcuts</h4>" +
        "<div class='content'>" +
        '<p>Click here to navigate to:</br>' +
        '- Translate/Revise mode</br>' +
        '- Volume analysis</br>' +
        '- XLIFF-to-target converter</br>' +
        '- Shortcut guide</p>' +
        "<a class='close-popup-teams'>Got it!</a>" +
        '</div>'
      $('#action-three-dots')
        .popup({
          on: 'click',
          onHidden: function () {
            $('#action-three-dots').popup('destroy')
            CommonUtils.addInStorage(
              'infoThreeDotsMenu-' + config.userMail,
              true,
              'infoThreeDotsMenu',
            )
            return true
          },
          html: tooltipTex,
          closable: false,
          onCreate: function () {
            $('.close-popup-teams').on('click', function () {
              $('#action-three-dots').popup('hide')
              self.openPopupInstructions()
            })
          },
          className: {
            popup: 'ui popup three-dots-menu-tooltip',
          },
        })
        .popup('show')
    } else {
      this.openPopupInstructions()
    }
  },
  openPopupInstructions: function () {
    var closedPopup = localStorage.getItem(
      'infoInstructions-' + config.userMail,
    )
    if (!closedPopup && $('#files-instructions > div').length > 0) {
      var tooltipTex =
        "<h4 class='header'>Instructions and references</h4>" +
        "<div class='content'>" +
        '<p>You can view the instructions and references any time by clicking here.</p>' +
        "<a class='close-popup-teams'>Got it!</a>" +
        '</div>'
      $('#files-instructions')
        .popup({
          on: 'click',
          onHidden: function () {
            $('#files-instructions').popup('destroy')
            CommonUtils.addInStorage(
              'infoInstructions-' + config.userMail,
              true,
              'infoInstructions',
            )
            return true
          },
          html: tooltipTex,
          closable: false,
          onCreate: function () {
            $('.close-popup-teams').on('click', function () {
              $('#files-instructions').popup('hide')
            })
          },
          className: {
            popup: 'ui popup files-instructions-tooltip',
          },
        })
        .popup('show')
    }
  },
})
