import React, {createContext, useState} from 'react'
import PropTypes from 'prop-types'
import Login from './Login'
import Register from './Register'
import PasswordReset from './PasswordReset'
import ForgotPassword from './ForgotPassword'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../common/Button/Button'
import IconClose from '../icons/IconClose'
import ChevronDown from '../../../../../img/icons/ChevronDown'

export const ONBOARDING_STEP = {
  LOGIN: 'login',
  REGISTER: 'register',
  PASSWORD_RESET: 'passwordReset',
  FORGOT_PASSWORD: 'forgotPassword',
}

export const OnBoardingContext = createContext({})
export const socialUrls = {
  googleUrl: config.authURL,
  github: config.githubAuthUrl,
  microsoft: config.microsoftAuthUrl,
  linkedIn: config.linkedInAuthUrl,
  meta: config.facebookAuthUrl,
}
const onBoarding = ({
  step = ONBOARDING_STEP.LOGIN,
  shouldShowControls = true,
}) => {
  const [stepState, setStep] = useState(step)

  const backHandler = () =>
    setStep((prevState) =>
      prevState === ONBOARDING_STEP.PASSWORD_RESET ||
      prevState === ONBOARDING_STEP.FORGOT_PASSWORD
        ? ONBOARDING_STEP.LOGIN
        : prevState,
    )

  const closeHandler = () => {}

  const isBackButtonEnabled =
    stepState === ONBOARDING_STEP.PASSWORD_RESET ||
    stepState === ONBOARDING_STEP.FORGOT_PASSWORD

  const socialLogin = (url) => {
    const newWindow = window.open(url, 'name', 'height=600,width=900')
    if (window.focus) {
      newWindow.focus()
    }
    const interval = setInterval(function () {
      if (newWindow.closed) {
        clearInterval(interval)
        window.location.reload()
      }
    }, 600)
  }

  return (
    <OnBoardingContext.Provider value={{setStep, socialLogin}}>
      {shouldShowControls && (
        <div className="onboarding-controls">
          <div className="container-buttons">
            <div>
              {isBackButtonEnabled && (
                <Button
                  className="button-back"
                  mode={BUTTON_MODE.OUTLINE}
                  size={BUTTON_SIZE.ICON_STANDARD}
                  onClick={backHandler}
                >
                  <ChevronDown />
                </Button>
              )}
            </div>
            <Button
              className="button-close"
              size={BUTTON_SIZE.ICON_SMALL}
              onClick={closeHandler}
            >
              <IconClose size={10} />
            </Button>
          </div>
        </div>
      )}

      {stepState === ONBOARDING_STEP.LOGIN && <Login />}
      {stepState === ONBOARDING_STEP.REGISTER && <Register />}
      {stepState === ONBOARDING_STEP.PASSWORD_RESET && <PasswordReset />}
      {stepState === ONBOARDING_STEP.FORGOT_PASSWORD && <ForgotPassword />}
    </OnBoardingContext.Provider>
  )
}

onBoarding.propTypes = {
  step: PropTypes.oneOf(Object.values(ONBOARDING_STEP)),
  shouldShowControls: PropTypes.bool,
}

export default onBoarding
