import React, {
  createContext,
  useCallback,
  useEffect,
  useRef,
  useState,
} from 'react'
import useAuth from '../../../hooks/useAuth'
import Cookies from 'js-cookie'
import CatToolActions from '../../../actions/CatToolActions'
import {onModalWindowMounted} from '../../modals/ModalWindow'
import CommonUtils from '../../../utils/commonUtils'
import {FORCE_ACTIONS, ForcedActionModal} from './ForcedActionModal'
import ModalsActions from '../../../actions/ModalsActions'
import UserConstants from '../../../constants/UserConstants'
import ApplicationStore from '../../../stores/ApplicationStore'
import UserStore from '../../../stores/UserStore'

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
window.eventHandler = new EventHandlerClass()

export const ApplicationWrapperContext = createContext({})

export const ApplicationWrapper = ({children}) => {
  const {
    isUserLogged,
    userInfo,
    connectedServices,
    userDisconnected,
    setUserInfo,
    logout,
    forceLogout,
    setUserMetadataKey,
  } = useAuth()

  const [forceReload, setForceReload] = useState(false)

  const checkGlobalMassages = useCallback(() => {
    if (config.global_message) {
      const messages = JSON.parse(config.global_message)
      messages.forEach((elem) => {
        if (
          !isUserLogged ||
          (typeof Cookies.get('msg-' + elem.token) == 'undefined' &&
            new Date(elem.expire) > new Date())
        ) {
          const notification = {
            title: 'Notice',
            text: elem.msg,
            type: elem.level ? elem.level : 'warning',
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
  }, [isUserLogged])

  const checkForPopupToOpen = useRef()
  checkForPopupToOpen.current = () => {
    const openFromFlash = CommonUtils.lookupFlashServiceParam('popup')
    if (!openFromFlash) return

    switch (openFromFlash[0].value) {
      case 'passwordReset':
        ModalsActions.openResetPassword({setNewPassword: true})
        break
      case 'profile':
        // TODO: optimized this, establish a list of events to happen after user data is loaded
        ModalsActions.openSuccessModal({
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
        ModalsActions.openLoginModal()
        break
      case 'signup':
        ModalsActions.openRegisterModal()
        break
      default:
        break
    }
  }

  useEffect(() => {
    if (typeof isUserLogged === 'boolean') checkGlobalMassages()
  }, [isUserLogged, checkGlobalMassages])

  useEffect(() => {
    const forceReloadFn = () => {
      setForceReload(true)
    }
    onModalWindowMounted().then(() => checkForPopupToOpen.current())
    UserStore.addListener(UserConstants.FORCE_RELOAD, forceReloadFn)
    return () => {
      UserStore.removeListener(UserConstants.FORCE_RELOAD, forceReloadFn)
    }
  }, [])

  return (
    <ApplicationWrapperContext.Provider
      value={{
        isUserLogged,
        userInfo,
        connectedServices,
        setUserInfo,
        logout,
        forceLogout,
        setUserMetadataKey,
      }}
    >
      {userDisconnected && (
        <ForcedActionModal action={FORCE_ACTIONS.DISCONNECT} />
      )}
      {forceReload && !userDisconnected && (
        <ForcedActionModal action={FORCE_ACTIONS.RELOAD} />
      )}
      {children}
    </ApplicationWrapperContext.Provider>
  )
}
