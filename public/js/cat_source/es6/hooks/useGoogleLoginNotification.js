import {useEffect} from 'react'
import CatToolActions from '../actions/CatToolActions'

export const GOOGLE_LOGIN_NOTIFICATION = {
  title: 'Google login warning',
  text:
    'Hi There! We are experiencing some problems with our Google integration. ' +
    'If you are having issues signing in or uploading Drive files from the homepage, ' +
    'please <a href="https://guides.matecat.com/matecat-google-sign-in-issues" target="_blank" title="Guide">read this guide</a> to find out how to solve them. ' +
    'If the issues persist, please contact our support.',
  type: 'warning',
  autoDismiss: false,
  position: 'bl',
  allowHtml: true,
  closeCallback: function () {
    localStorage.removeItem(GOOGLE_LOGIN_LOCAL_STORAGE)
  },
}

export const GOOGLE_LOGIN_LOCAL_STORAGE = 'google_login_notification'

export const shouldShowNotificationGoogleLogin = () => {
  return (
    localStorage.getItem(GOOGLE_LOGIN_LOCAL_STORAGE) === 'true' &&
    !config.isLoggedIn
  )
}

export const useGoogleLoginNotification = () => {
  const shouldShowNotification = shouldShowNotificationGoogleLogin()

  useEffect(() => {
    let tmOut

    if (shouldShowNotification) {
      tmOut = setTimeout(
        () => CatToolActions.addNotification(GOOGLE_LOGIN_NOTIFICATION),
        100,
      )
    } else if (config.isLoggedIn) {
      localStorage.setItem(GOOGLE_LOGIN_LOCAL_STORAGE, false)
    }

    return () => clearTimeout(tmOut)
  }, [shouldShowNotification])
}
