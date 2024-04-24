import React from 'react'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import SocialButtons from './SocialButtons'

const Login = () => {
  return (
    <div className="login-component">
      <h2>Sing in to Matecat</h2>
      <SocialButtons />
      <div className="login-divider">
        <div />
        <span>Or</span>
        <div />
      </div>
      <div className="login-form">
        <input type="text" placeholder="Email" />
        <input type="text" placeholder="Password" />
        <Button type={BUTTON_TYPE.PRIMARY} size={BUTTON_SIZE.MEDIUM}>
          Sign in
        </Button>
      </div>
    </div>
  )
}

export default Login
