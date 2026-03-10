import React, {createContext, useState} from 'react'
import PropTypes from 'prop-types'
import Login from './Login'
import Register from './Register'
import PasswordReset from './PasswordReset'
import ForgotPassword from './ForgotPassword'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../common/Button/Button'
import IconClose from '../icons/IconClose'
import ChevronDown from '../../../img/icons/ChevronDown'
import ModalsActions from '../../actions/ModalsActions'
import CommonUtils from '../../utils/commonUtils'

export const ONBOARDING_STEP = {
  LOGIN: 'login',
  REGISTER: 'register',
  PASSWORD_RESET: 'passwordReset',
  FORGOT_PASSWORD: 'forgotPassword',
  SET_NEW_PASSWORD: 'setNewPassword',
}

export const OnBoardingContext = createContext({})
export const socialUrls = {
  googleUrl: config.googleAuthURL,
  github: config.githubAuthUrl,
  microsoft: config.microsoftAuthUrl,
  linkedIn: config.linkedInAuthUrl,
  meta: config.facebookAuthUrl,
}
const OnBoarding = ({
  step = ONBOARDING_STEP.LOGIN,
  isCloseButtonEnabled = false,
}) => {
  const [stepState, setStep] = useState(step)

  const backHandler = () =>
    setStep((prevState) =>
      prevState === ONBOARDING_STEP.FORGOT_PASSWORD
        ? ONBOARDING_STEP.LOGIN
        : prevState,
    )

  const closeHandler = () => ModalsActions.onCloseModal()

  const isBackButtonEnabled = stepState === ONBOARDING_STEP.FORGOT_PASSWORD

  const redirectAfterLogin = () => {
    location.reload()
  }

  const socialLogin = (url) => {
    const data = {
      event: 'open_register',
      type: 'social',
    }
    CommonUtils.dispatchAnalyticsEvents(data)
    const newWindow = window.open(url, 'name', 'height=600,width=900')
    if (newWindow.focus) {
      newWindow.focus()
    }
    const interval = setInterval(function () {
      if (newWindow.closed) {
        clearInterval(interval)
        redirectAfterLogin()
      }
    }, 600)
  }

  return (
    <OnBoardingContext.Provider
      value={{setStep, socialLogin, redirectAfterLogin}}
    >
      <div className="onboarding-wrapper">
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
            {isCloseButtonEnabled && (
              <Button
                className="button-close"
                size={BUTTON_SIZE.ICON_SMALL}
                onClick={closeHandler}
              >
                <IconClose size={10} />
              </Button>
            )}
          </div>
        </div>

        {stepState === ONBOARDING_STEP.LOGIN && <Login />}
        {stepState === ONBOARDING_STEP.REGISTER && <Register />}
        {stepState === ONBOARDING_STEP.PASSWORD_RESET && <PasswordReset />}
        {stepState === ONBOARDING_STEP.FORGOT_PASSWORD && <ForgotPassword />}
        {stepState === ONBOARDING_STEP.SET_NEW_PASSWORD && (
          <PasswordReset newPassword={true} />
        )}
      </div>
    </OnBoardingContext.Provider>
  )
}

OnBoarding.propTypes = {
  step: PropTypes.oneOf(Object.values(ONBOARDING_STEP)),
  isCloseButtonEnabled: PropTypes.bool,
}

export default OnBoarding
