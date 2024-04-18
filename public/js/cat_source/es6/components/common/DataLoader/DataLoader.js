import React, {createContext, useEffect} from 'react'
import useAuth from '../../../hooks/useAuth'
import Cookies from 'js-cookie'
import CatToolActions from '../../../actions/CatToolActions'
import {onModalWindowMounted} from '../../modals/ModalWindow'
import CommonUtils from '../../../utils/commonUtils'
import {UserDisconnectedBox} from './UserDisconnectedBox'
export const DataLoaderContext = createContext({})

export const DataLoader = ({children}) => {
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

  const checkForPopupToOpen = () => {
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
        const data = {
          event: !userInfo.user.has_password
            ? 'new_signup_google'
            : 'new_signup_email',
          userId: userInfo.user.uid,
        }
        CommonUtils.dispatchAnalyticsEvents(data)

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
      onModalWindowMounted().then(() => checkForPopupToOpen())
    }
  }, [isUserLogged])

  return (
    <DataLoaderContext.Provider
      value={{isUserLogged, userInfo, connectedServices}}
    >
      {userDisconnected && <UserDisconnectedBox />}
      {children}
    </DataLoaderContext.Provider>
  )
}
