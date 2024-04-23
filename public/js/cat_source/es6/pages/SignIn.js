import React from 'react'
import {mountPage} from './mountPage'
import Header from '../components/header/Header'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
const SignIn = () => {
  return (
    <>
      <header>
        <Header showLinks={true} />
      </header>
      <div>
        Sign In
        <div className="notifications-wrapper">
          <NotificationBox />
        </div>
      </div>
    </>
  )
}

export default SignIn

mountPage({
  Component: SignIn,
  rootElement: document.getElementsByClassName('signin__page')[0],
})
