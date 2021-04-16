'use strict'

APP.USER = {}
APP.USER.STORE = {}
;(function (APP, $) {
  /**
   * Load all user information from server and update store.
   *
   * @returns {*|{type}|nothing}
   */
  var loadUserData = function () {
    return $.ajax({
      url: APP.getRandomUrl() + 'api/app/user',
      dataType: 'json',
      async: true,
      xhrFields: {withCredentials: true},
    }).done(function (data) {
      APP.USER.STORE = data
      $(document).trigger('userDataLoaded')
    })
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
    APP.USER.STORE.connected_services = _.map(
      APP.USER.STORE.connected_services,
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
  })
})(APP, jQuery)
