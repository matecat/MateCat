import React, {useContext, useEffect} from 'react'
import {mountPage} from './mountPage'
import Header from '../components/header/Header'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import OnBoarding from '../components/onBoarding/OnBoarding'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper'

const SignIn = () => {
  const {isUserLogged} = useContext(ApplicationWrapperContext)
  useEffect(() => {
    if (isUserLogged) window.location.href = window.location.origin
  }, [isUserLogged])
  return (
    <>
      <header>
        <Header showLinks={true} showUserMenu={false} />
      </header>
      <div className="signin-page">
        <div className="signin-bg " />
        <div className="signin-overlay">
          <div className="signin-content">
            <OnBoarding />
          </div>
        </div>
      </div>
      <div className="notifications-wrapper">
        <NotificationBox />
      </div>
    </>
  )
}

export default SignIn

mountPage({
  Component: SignIn,
  rootElement: document.getElementsByClassName('signin__page')[0],
})
