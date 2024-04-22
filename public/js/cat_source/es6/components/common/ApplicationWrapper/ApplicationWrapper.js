import React, {createContext, useEffect, useRef} from 'react'
import useAuth from '../../../hooks/useAuth'
import Cookies from 'js-cookie'
import CatToolActions from '../../../actions/CatToolActions'
import {onModalWindowMounted} from '../../modals/ModalWindow'
import CommonUtils from '../../../utils/commonUtils'
import {UserDisconnectedBox} from './UserDisconnectedBox'

// Custom event handler class: allows namespaced events
class EventHandlerClass {
  constructor() {
    this.functionMap = {}
  }

  addEventListener(event, func) {
    this.functionMap[event] = func
    document.addEventListener(event.split('.')[0], this.functionMap[event])
  }

  removeEventListener(event) {
    document.removeEventListener(event.split('.')[0], this.functionMap[event])
    delete this.functionMap[event]
  }
}

export const ApplicationWrapperContext = createContext({})

window.eventHandler = new EventHandlerClass()

export const ApplicationWrapper = ({children}) => {
  const {isUserLogged, userInfo, connectedServices, userDisconnected} =
    useAuth()

  const checkGlobalMassages = () => {
    if (config.global_message) {
      var messages = JSON.parse(config.global_message)
      messages.forEach((elem) => {
        if (
          typeof Cookies.get('msg-' + elem.token) == 'undefined' &&
          new Date(elem.expire) > new Date()
        ) {
          const notification = {
            title: 'Notice',
            text: elem.msg,
            type: 'warning',
            autoDismiss: false,
            position: 'bl',
            allowHtml: true,
            closeCallback: function () {
              const expireDate = new Date(elem.expire)
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
  }

  const checkForPopupToOpen = useRef()
  checkForPopupToOpen.current = () => {
    const openFromFlash = APP.lookupFlashServiceParam('popup')
    if (!openFromFlash) return

    switch (openFromFlash[0].value) {
      case 'passwordReset':
        APP.openResetPassword()
        break
      case 'profile':
        // TODO: optimized this, establish a list of events to happen after user data is loaded
        APP.openSuccessModal({
          title: 'Registration complete',
          text: 'You are now logged in and ready to use Matecat.',
        })
        //After confirm email or google register
        CommonUtils.dispatchAnalyticsEvents({
          event: !userInfo.user.has_password
            ? 'new_signup_google'
            : 'new_signup_email',
          userId: userInfo.user.uid,
        })

        break
      case 'login':
        APP.openLoginModal()
        break
      case 'signup':
        APP.openRegisterModal()
        break
      default:
        break
    }
  }

  useEffect(() => {
    setTimeout(checkGlobalMassages, 1000)
  }, [])

  useEffect(() => {
    if (isUserLogged) {
      onModalWindowMounted().then(() => checkForPopupToOpen.current())
    }
  }, [isUserLogged])

  return (
    <ApplicationWrapperContext.Provider
      value={{isUserLogged, userInfo, connectedServices}}
    >
      {userDisconnected && <UserDisconnectedBox />}
      {children}
    </ApplicationWrapperContext.Provider>
  )
}
