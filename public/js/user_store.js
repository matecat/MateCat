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
      return data
    })
  }
  const isUserLogged = () => {
    return !!config.isLoggedIn
  }
  const isGoogleUser = () => {
    return !APP.USER.STORE.user.has_password
  }

  $.extend(APP.USER, {
    loadUserData: loadUserData,
    isUserLogged: isUserLogged,
    isGoogleUser: isGoogleUser,
  })
})(APP, jQuery)
