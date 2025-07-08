import React, {createContext, useEffect, useRef, useState} from 'react'
import useAuth from '../../../hooks/useAuth'
import {onModalWindowMounted} from '../../modals/ModalWindow'
import CommonUtils from '../../../utils/commonUtils'
import {FORCE_ACTIONS, ForcedActionModal} from './ForcedActionModal'
import ModalsActions from '../../../actions/ModalsActions'
import UserConstants from '../../../constants/UserConstants'
import UserStore from '../../../stores/UserStore'
import {ApplicationWrapperContext} from './ApplicationWrapperContext'

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
        // CommonUtils.dispatchAnalyticsEvents({
        //   event: !userInfo.user.has_password
        //     ? 'new_signup_google'
        //     : 'new_signup_email',
        //   userId: userInfo.user.uid,
        // })

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
