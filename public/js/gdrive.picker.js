import {getUserConnectedService} from './cat_source/es6/api/getUserConnectedService'
import UserStore from './cat_source/es6/stores/UserStore'
import ModalsActions from './cat_source/es6/actions/ModalsActions'

var GDrive = function () {
  'use strict'

  var scope = ['https://www.googleapis.com/auth/drive.file']

  this.pickerApiLoaded = false
  this.authApiLoaded = false

  function onAuthApiLoad() {
    gdrive.authApiLoaded = true
  }

  function onPickerApiLoad() {
    gdrive.pickerApiLoaded = true
  }

  this.createPicker = function (service) {
    const token = JSON.parse(service.oauth_access_token)

    const picker = new google.picker.PickerBuilder()
      .setAppId(window.clientId)
      .addView(google.picker.ViewId.DOCUMENTS)
      .addView(google.picker.ViewId.PRESENTATIONS)
      .addView(google.picker.ViewId.SPREADSHEETS)
      .setOAuthToken(token.access_token)
      .setDeveloperKey(window.developerKey)
      .setCallback(pickerCallback)
      .enableFeature(google.picker.Feature.MINE_ONLY)
      .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
      .build()
    try {
      picker.setVisible(true)
    } catch (e) {
      UserStore.updateConnectedService({service, is_default: false})
      throw new Error('Picker Error')
    }
  }

  function pickerCallback(data) {
    if (data[google.picker.Response.ACTION] == google.picker.Action.PICKED) {
      var exportIds = []

      var countDocuments = data[google.picker.Response.DOCUMENTS].length

      for (var i = 0; i < countDocuments; i++) {
        var doc = data[google.picker.Response.DOCUMENTS][i]
        var id = doc.id

        exportIds[i] = id
      }

      APP.addGDriveFile(exportIds)
    }
  }

  this.loadPicker = function () {
    gapi.load('auth', {callback: onAuthApiLoad})
    gapi.load('picker', {callback: onPickerApiLoad})
  }
}

var gdrive = new GDrive()

;(function ($, gdrive, undefined) {
  /**
   * Reads the store and returns the first selectable or first default or null
   *
   * @returns {*}
   */
  function tryToRefreshToken(service) {
    return getUserConnectedService(service.id)
  }

  function gdriveInitComplete() {
    return gdrive.pickerApiLoaded && gdrive.authApiLoaded
  }

  function showPreferencesWithMessage() {
    ModalsActions.openPreferencesModal({showGDriveMessage: true})
  }

  function openGoogleDrivePickerIntent() {
    const userInfo = UserStore.getUser()
    if (!gdriveInitComplete()) {
      console.log('gdriveInitComplete not complete')
      return
    }

    // TODO: is this enough to know if the user is logged in?
    if (!userInfo) {
      ModalsActions.openLoginModal()
      return
    }

    var default_service = UserStore.getDefaultConnectedService()

    if (!default_service) {
      showPreferencesWithMessage()
      return
    }

    tryToRefreshToken(default_service)
      .then(function (data) {
        UserStore.updateConnectedService(data.connected_service)
        gdrive.createPicker(UserStore.getDefaultConnectedService())
      })
      .catch(function () {
        UserStore.updateConnectedService({default_service, is_default: false})
        showPreferencesWithMessage()
      })
  }

  $(document).on('click', '.load-gdrive', function (e) {
    e.preventDefault()
    openGoogleDrivePickerIntent()
  })
})(jQuery, gdrive)

window.onGDriveApiInit = function () {
  gdrive.loadPicker()
}
