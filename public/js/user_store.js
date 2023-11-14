import {getUserData} from './cat_source/es6/api/getUserData'
APP.USER = {}
APP.USER.STORE = {}
;(function (APP, $) {
  /**
   * Load all user information from server and update store.
   *
   * @returns {*|{type}|nothing}
   */
  const loadUserData = () => {
    return getUserData().then(function (data) {
      APP.USER.STORE = data
      $(document).trigger('userDataLoaded', data)
      return data
    })
  }
  const isUserLogged = () => {
    return !!config.isLoggedIn
  }
  const isGoogleUser = () => {
    return !APP.USER.STORE.user.has_password
  }
  function getDefaultConnectedService() {
    if (APP.USER.STORE.connected_services.length) {
      var selectable = $(APP.USER.STORE.connected_services).filter(function () {
        return !this.expired_at && !this.disabled_at
      })
      var defaults = $(selectable).filter(function () {
        return this.is_default
      })
      return defaults[0] || selectable[0]
    }
  }

  var upsertConnectedService = function (input_service) {
    APP.USER.STORE.connected_services = APP.USER.STORE.connected_services.map(
      function (service) {
        if (service.id == input_service.id) {
          return input_service
        }

        return service
      },
    )
  }

  $.extend(APP.USER, {
    loadUserData: loadUserData,
    getDefaultConnectedService: getDefaultConnectedService,
    upsertConnectedService: upsertConnectedService,
    isUserLogged: isUserLogged,
    isGoogleUser: isGoogleUser,
  })
})(APP, jQuery)
