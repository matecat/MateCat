import React, {useContext} from 'react'
import {
  Button,
  BUTTON_HTML_TYPE,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import SocialButtons from './SocialButtons'
import {useForm, Controller} from 'react-hook-form'
import {EMAIL_PATTERN} from '../../constants/Constants'
import {Input, INPUT_TYPE} from '../common/Input/Input'
import {ONBOARDING_STEP, OnBoardingContext} from './OnBoarding'

const Login = () => {
  const {setStep} = useContext(OnBoardingContext)

  const {handleSubmit, control} = useForm()

  const handleFormSubmit = (formData) => {
    console.log(formData)
  }

  const goToSignup = () => setStep(ONBOARDING_STEP.REGISTER)
  const goToForgotPassword = () => setStep(ONBOARDING_STEP.FORGOT_PASSWORD)

  return (
    <div className="login-component">
      <h2>Sign in to Matecat</h2>
      <SocialButtons />
      <div className="login-divider">
        <div />
        <span>Or</span>
        <div />
      </div>
      <form className="login-form" onSubmit={handleSubmit(handleFormSubmit)}>
        <fieldset>
          <Controller
            control={control}
            defaultValue=""
            name="email"
            rules={{
              required: 'This field is mandatory',
              pattern: {
                value: EMAIL_PATTERN,
                message: 'Enter a valid email address',
              },
            }}
            render={({field: {name, onChange, value}, fieldState: {error}}) => (
              <Input
                type={INPUT_TYPE.EMAIL}
                placeholder="Email"
                {...{name, value, onChange, error}}
              />
            )}
          />
        </fieldset>
        <fieldset>
          <Controller
            control={control}
            defaultValue=""
            name="password"
            rules={{
              required: 'This field is mandatory',
            }}
            render={({field: {name, onChange, value}, fieldState: {error}}) => (
              <Input
                type={INPUT_TYPE.PASSWORD}
                placeholder="Password"
                {...{name, value, onChange, error}}
              />
            )}
          />
        </fieldset>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.MEDIUM}
          htmlType={BUTTON_HTML_TYPE.SUBMIT}
        >
          Sign in
        </Button>
      </form>

      <div className="footer-links-container">
        <span>
          Don't have an account?{' '}
          <Button
            className="link-underline"
            type={BUTTON_TYPE.PRIMARY}
            mode={BUTTON_MODE.LINK}
            size={BUTTON_SIZE.LINK_SMALL}
            onClick={goToSignup}
          >
            Sign up
          </Button>
        </span>
        <Button
          className="link-underline"
          type={BUTTON_TYPE.PRIMARY}
          mode={BUTTON_MODE.LINK}
          size={BUTTON_SIZE.LINK_SMALL}
          onClick={goToForgotPassword}
        >
          Forgot your password?
        </Button>
      </div>
    </div>
  )
}

export default Login
