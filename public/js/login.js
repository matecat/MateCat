import React from 'react'

import SuccessModal from './cat_source/es6/components/modals/SuccessModal'
import ConfirmRegister from './cat_source/es6/components/modals/ConfirmRegister'
import PreferencesModal from './cat_source/es6/components/modals/PreferencesModal'
import ResetPasswordModal from './cat_source/es6/components/modals/ResetPasswordModal'
import ForgotPasswordModal from './cat_source/es6/components/modals/ForgotPasswordModal'
import RegisterModal from './cat_source/es6/components/modals/RegisterModal'
import LoginModal from './cat_source/es6/components/modals/LoginModal'
import ModalsActions from './cat_source/es6/actions/ModalsActions'
import {onModalWindowMounted} from './cat_source/es6/components/modals/ModalWindow'

$.extend(APP, {
  setLoginEvents: function () {
    $('#modal').on('closemodal', function () {
      ModalsActions.onCloseModal()
    })

    $('#modal').on('opensuccess', function (e, param) {
      ModalsActions.showModalComponent(SuccessModal, param, param.title)
    })

    $('#modal').on('confirmregister', function (e, param) {
      var style = {
        width: '25%',
        maxWidth: '450px',
      }
      ModalsActions.showModalComponent(
        ConfirmRegister,
        param,
        'Confirm Registration',
        style,
      )
    })

    $('#modal').on('openpreferences', function (e, param) {
      e.preventDefault()
      e.stopPropagation()
      var props = {
        user: APP.USER.STORE.user,
        metadata: APP.USER.STORE.metadata ? APP.USER.STORE.metadata : {},
      }
      if (
        APP.USER.STORE.connected_services &&
        APP.USER.STORE.connected_services.length
      ) {
        props.service = APP.USER.getDefaultConnectedService()
      }
      if (param) {
        $.extend(props, param)
      }
      var style = {
        width: '700px',
        maxWidth: '700px',
      }
      ModalsActions.showModalComponent(
        PreferencesModal,
        props,
        'Profile',
        style,
      )
    })
    $('#modal').on('openresetpassword', function () {
      ModalsActions.showModalComponent(ResetPasswordModal, {}, 'Reset Password')
    })
    $('#modal').on('openforgotpassword', function () {
      var props = {}
      if (config.showModalBoxLogin == 1) {
        props.redeemMessage = true
      }
      var style = {
        width: '577px',
      }
      ModalsActions.showModalComponent(
        ForgotPasswordModal,
        props,
        'Forgot Password',
        style,
      )
    })
    $('#modal').on('openregister', function (e, param) {
      var props = {
        googleUrl: config.authURL,
      }
      if (config.showModalBoxLogin == 1) {
        props.redeemMessage = true
      }
      if (param) {
        $.extend(props, param)
      }
      ModalsActions.showModalComponent(RegisterModal, props, 'Register Now')
    })
    $('#modal').on('openlogin', function (e, param) {
      if ($('.popup-tm.open').length) {
        UI.closeTMPanel()
      }
      APP.openLoginModal(param)
    })

    $('.link-manage-page').on('click', function (e) {
      APP.openManagePage(e)
    })

    //Link footer
    // $('.user-menu-preferences').on('click', function (e) {
    //     e.preventDefault();
    //     e.stopPropagation();
    //     $('#modal').trigger('openpreferences');
    //     return false;
    // });

    $('.open-login-modal').click(function (e) {
      e.preventDefault()
      e.stopPropagation()
      $('#modal').trigger('openlogin')
      return false
    })

    $('#sign-in').click(function (e) {
      e.preventDefault()
      e.stopPropagation()
      $('#modal').trigger('openlogin')
      return false
    })

    $('#sign-in-o, #sign-in-o-mt').click(function (e) {
      e.preventDefault()
      e.stopPropagation()
      UI.closeTMPanel()
      APP.openLoginModal()
      return false
    })

    if (config.showModalBoxLogin == 1) {
      $('#modal').trigger('openlogin')
    }

    onModalWindowMounted().then(() => this.checkForPopupToOpen())
  },

  checkForPopupToOpen: function () {
    var openFromFlash = APP.lookupFlashServiceParam('popup')
    if (!openFromFlash) return
    var modal$ = $('#modal')

    switch (openFromFlash[0].value) {
      case 'passwordReset':
        modal$.trigger('openresetpassword')
        break
      case 'profile':
        // TODO: optimized this, establish a list of events to happen after user data is loaded
        APP.USER.loadUserData().then(function () {
          modal$.trigger('openpreferences')
        })
        break
      case 'login':
        modal$.trigger('openlogin')
        break
      case 'signup':
        if (!config.isLoggedIn) {
          if (APP.lookupFlashServiceParam('signup_email')) {
            var userMail = APP.lookupFlashServiceParam('signup_email')[0].value
            modal$.trigger('openregister', [{userMail: userMail}])
          } else {
            modal$.trigger('openregister')
          }
        }
        break
    }
  },

  openManagePage: function (e) {
    if (!config.isLoggedIn) {
      e.preventDefault()
      e.stopPropagation()
      $('#modal').trigger('openlogin', [{goToManage: true}])
    }
  },

  openLoginModal: function (param) {
    var title = 'Add project to your management panel'
    var style = {
      width: '80%',
      maxWidth: '800px',
      minWidth: '600px',
    }
    var props = {
      googleUrl: config.authURL,
    }

    if (config.showModalBoxLogin == 1) {
      props.redeemMessage = true
      title = 'Add project to your management panel'
    }

    if (param) {
      $.extend(props, param)
    }
    ModalsActions.showModalComponent(LoginModal, props, title, style)
  },
})
