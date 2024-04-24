import React from 'react'
import PropTypes from 'prop-types'
import Login from './Login'
import Register from './Register'
import PasswordReset from './PasswordReset'
import ForgotPassword from './ForgotPassword'

export const ONBOARDING_STEP = {
  LOGIN: 'login',
  REGISTER: 'register',
  PASSWORD_RESET: 'passwordReset',
  FORGOT_PASSWORD: 'forgotPassword',
}
const onBoarding = ({step = ONBOARDING_STEP.LOGIN}) => {
  return (
    <>
      {step === ONBOARDING_STEP.LOGIN && <Login />}
      {step === ONBOARDING_STEP.REGISTER && <Register />}
      {step === ONBOARDING_STEP.PASSWORD_RESET && <PasswordReset />}
      {step === ONBOARDING_STEP.FORGOT_PASSWORD && <ForgotPassword />}
    </>
  )
}

onBoarding.propTypes = {
  step: PropTypes.oneOf(Object.values(ONBOARDING_STEP)),
}

export default onBoarding
