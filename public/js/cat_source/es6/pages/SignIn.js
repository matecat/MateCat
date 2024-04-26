import React, {createContext} from 'react'
import {mountPage} from './mountPage'
import Header from '../components/header/Header'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import OnBoarding from '../components/onBoarding/OnBoarding'

export const SignInContext = createContext({})

const SignIn = () => {
  return (
    <>
      <header>
        <Header showLinks={true} />
      </header>
      <div className="signin-page">
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
