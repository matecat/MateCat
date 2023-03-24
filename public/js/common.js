import Cookies from 'js-cookie'
import _ from 'lodash'
import TeamsActions from './cat_source/es6/actions/TeamsActions'
import ConfirmMessageModal from './cat_source/es6/components/modals/ConfirmMessageModal'
import {downloadFileGDrive} from './cat_source/es6/api/downloadFileGDrive'
import ModalsActions from './cat_source/es6/actions/ModalsActions'
import CommonUtils from './cat_source/es6/utils/commonUtils'

window.APP = null

window.APP = {
  teamStorageName: 'defaultTeam',
  init: function () {
    this.setLoginEvents()
    if (config.isLoggedIn) {
      var self = this
      APP.USER.loadUserData().then((data) => {
        TeamsActions.updateUser(data)
        self.setTeamNameInMenu()
        self.setUserImage()
      })
    }
    this.isCattool = $('body').hasClass('cattool')
    setTimeout(() => this.checkGlobalMassages(), 1000)
  },

  fitText: function (
    container,
    child,
    limitHeight,
    escapeTextLen,
    actualTextLow,
    actualTextHi,
  ) {
    if (typeof escapeTextLen == 'undefined') escapeTextLen = 4
    if (typeof $(child).attr('data-originalText') == 'undefined') {
      $(child).attr('data-originalText', $(child).text())
    }

    var originalText = $(child).text()

    //tail recursion exit control
    if (
      originalText.length < escapeTextLen ||
      (actualTextLow + actualTextHi).length < escapeTextLen
    ) {
      return false
    }

    if (
      typeof actualTextHi == 'undefined' &&
      typeof actualTextLow == 'undefined'
    ) {
      //we are in window.resize
      if (originalText.match(/\[\.\.\.]/)) {
        originalText = $(child).attr('data-originalText')
      }

      actualTextLow = originalText.substr(0, Math.ceil(originalText.length / 2))
      actualTextHi = originalText.replace(actualTextLow, '')
    }

    actualTextHi = actualTextHi.substr(1)
    actualTextLow = actualTextLow.substr(0, actualTextLow.length - 1)

    child.text(actualTextLow + '[...]' + actualTextHi)

    var loop = true
    // break recursion for browser width resize below 1024 px to avoid infinite loop and stack overflow
    while (container.height() >= limitHeight && loop == true) {
      loop = this.fitText(
        container,
        child,
        limitHeight,
        escapeTextLen,
        actualTextLow,
        actualTextHi,
      )
    }
    return false
  },

  /*************************************************************************************************************/

  lookupFlashServiceParam: function (name) {
    if (config.flash_messages && config.flash_messages.service) {
      return _.filter(config.flash_messages.service, function (service) {
        return service.key == name
      })
    }
  },

  checkGlobalMassages: function () {
    if (config.global_message) {
      var messages = JSON.parse(config.global_message)
      $.each(messages, function () {
        var elem = this
        if (
          typeof Cookies.get('msg-' + this.token) == 'undefined' &&
          new Date(this.expire) > new Date()
        ) {
          var notification = {
            title: 'Notice',
            text: this.msg,
            type: 'warning',
            autoDismiss: false,
            position: 'bl',
            allowHtml: true,
            closeCallback: function () {
              var expireDate = new Date(elem.expire)
              Cookies.set('msg-' + elem.token, '', {
                expires: expireDate,
                secure: true,
              })
            },
          }
          CatToolActions.addNotification(notification)
          return false
        }
      })
    }
  },

  getLastTeamSelected: function (teams) {
    if (config.isLoggedIn) {
      if (localStorage.getItem(this.teamStorageName)) {
        var lastId = localStorage.getItem(this.teamStorageName)
        var team = teams.find(function (t) {
          return parseInt(t.id) === parseInt(lastId)
        })
        if (team) {
          return team
        } else {
          return teams[0]
        }
      } else {
        return teams[0]
      }
    }
  },
  setTeamNameInMenu: function () {
    if (APP.USER.STORE.teams) {
      var team = this.getLastTeamSelected(APP.USER.STORE.teams)
      $('.user-menu-container .organization-name').text(team.name) //??
    } else {
      var self = this
      APP.USER.loadUserData().then(function () {
        self.setTeamNameInMenu.bind(self)
      })
    }
  },

  setUserImage: function () {
    if (APP.USER.STORE.user) {
      if (!APP.USER.STORE.metadata || !APP.USER.STORE.metadata.gplus_picture)
        return
      var urlImage = APP.USER.STORE.metadata.gplus_picture
      var html =
        '<img class="ui-user-top-image-general user-menu-preferences" src="' +
        urlImage +
        '"/>'
      $('.user-menu-container .ui-user-top-image').replaceWith(html)
      /*$('.user-menu-preferences').on('click', function (e) {*/
    } else {
      setTimeout(this.setUserImage.bind(this), 500)
    }
  },

  setTeamInStorage(teamId) {
    localStorage.setItem(this.teamStorageName, teamId)
  },

  downloadFile: function (idJob, pass, callback) {
    //create an iFrame element
    var iFrameDownload = $(document.createElement('iframe')).hide().prop({
      id: 'iframeDownload',
      src: '',
    })

    //append iFrame to the DOM
    $('body').append(iFrameDownload)

    //generate a token download
    var downloadToken =
      new Date().getTime() + '_' + parseInt(Math.random(0, 1) * 10000000)

    //set event listner, on ready, attach an interval that check for finished download
    iFrameDownload.ready(function () {
      //create a GLOBAL setInterval so in anonymous function it can be disabled
      var downloadTimer = window.setInterval(function () {
        //check for cookie
        var token = Cookies.get(downloadToken)

        //if the cookie is found, download is completed
        //remove iframe an re-enable download button
        if (typeof token != 'undefined') {
          /*
           * the token is a json and must be read with "parseJSON"
           * in case of failure:
           *      error_message = Object {code: -110, message: "Download failed.
           *      Please contact the owner of this MateCat instance"}
           *
           * in case of success:
           *      error_message = Object {code: 0, message: "Download Complete."}
           *
           */
          var tokenData = $.parseJSON(token)
          if (parseInt(tokenData.code) < 0) {
            APP.showDownloadErrorMessage()
          }
          if (callback) {
            callback()
          }

          window.clearInterval(downloadTimer)
          Cookies.set(downloadToken, null, {
            path: '/',
            expires: -1,
            secure: true,
          })
          iFrameDownload.remove()
        }
      }, 2000)
    })

    //clone the html form and append a token for download
    // var iFrameForm = $("#fileDownload").clone().append(
    //     $( document.createElement( 'input' ) ).prop({
    //         type:'hidden',
    //         name:'downloadToken',
    //         value: downloadToken
    //     })
    // );

    var iFrameForm = $(
      '<form id="fileDownload" action="' +
        config.basepath +
        '" method="post">' +
        '<input type="hidden" name="action" value="downloadFile" />' +
        '<input type="hidden" name="id_job" value="' +
        idJob +
        '" />' +
        '<input type="hidden" name="id_file" value="" />' +
        '<input type="hidden" name="password" value="' +
        pass +
        '"/>' +
        '<input type="hidden" name="download_type" value="all" />' +
        '<input type="hidden" name="downloadToken" value="' +
        downloadToken +
        '" />' +
        '</form>',
    )

    //append from to newly created iFrame and submit form post
    iFrameDownload.contents().find('body').append(iFrameForm)
    iFrameDownload.contents().find('#fileDownload').submit()
  },

  showDownloadErrorMessage: function () {
    const notification = {
      title: 'Error',
      text:
        'Download failed. Please, fix any tag issues and try again in 5 minutes. If it still fails, please, contact ' +
        config.support_mail,
      type: 'error',
    }
    CatToolActions.addNotification(notification)
  },

  downloadGDriveFile: function (openOriginalFiles, jobId, pass, callback) {
    if (typeof openOriginalFiles === 'undefined') {
      openOriginalFiles = 0
    }

    // TODO: this should be relative to the current USER, find a
    // way to generate this at runtime.
    //

    if (typeof window.googleDriveWindows == 'undefined') {
      window.googleDriveWindows = {}
    }

    if (CommonUtils.isSafari) {
      var windowReference = window.open()
    }
    var driveUpdateDone = function (data) {
      if (!data.urls || data.urls.length === 0) {
        var props = {
          text:
            'Matecat was not able to update project files on Google Drive. Maybe the project owner revoked privileges to access those files. Ask the project owner to login again and' +
            ' grant Google Drive privileges to Matecat.',
          successText: 'Ok',
          successCallback: function () {
            ModalsActions.onCloseModal()
          },
        }
        ModalsActions.showModalComponent(
          ConfirmMessageModal,
          props,
          'Download fail',
        )
        return
      }

      var winName

      $.each(data.urls, function (index, item) {
        winName = 'window' + item.localId
        if (CommonUtils.isSafari) {
          windowReference.location = item.alternateLink
        } else if (
          window.googleDriveWindows[winName] &&
          !window.googleDriveWindows[winName].closed &&
          window.googleDriveWindows[winName].location != null
        ) {
          window.googleDriveWindows[winName].location.href = item.alternateLink
          window.googleDriveWindows[winName].focus()
        } else {
          window.googleDriveWindows[winName] = window.open(item.alternateLink)
        }
      })
    }
    var downloadToken =
      new Date().getTime() + '_' + parseInt(Math.random(0, 1) * 10000000)

    downloadFileGDrive(openOriginalFiles, jobId, pass, downloadToken)
      .then((data) => {
        driveUpdateDone(data)
        if (callback) {
          callback()
        }
      })
      .catch(() => {
        if (callback) {
          callback()
        }
        var cookie = Cookies.get(downloadToken)
        if (cookie) {
          this.showDownloadErrorMessage()
          var props = {
            text: cookie.message,
            successText: 'Ok',
            successCallback: function () {
              ModalsActions.onCloseModal()
            },
          }
          ModalsActions.showModalComponent(
            ConfirmMessageModal,
            props,
            'Download fail',
          )
          Cookies.remove(downloadToken)
        }
      })
  },
}

$(document).ready(function () {
  APP.init()
})
