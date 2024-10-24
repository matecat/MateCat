import React, {useContext, useState} from 'react'
import {useForm, Controller} from 'react-hook-form'
import {EMAIL_PATTERN} from '../../constants/Constants'
import {INPUT_TYPE, Input} from '../common/Input/Input'
import {
  BUTTON_HTML_TYPE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../common/Button/Button'
import {forgotPassword} from '../../api/forgotPassword'
import {ONBOARDING_STEP, OnBoardingContext} from './OnBoarding'

const ForgotPassword = () => {
  const {setStep} = useContext(OnBoardingContext)
  const {handleSubmit, control} = useForm()
  const [errorMessage, setErrorMessage] = useState()
  const [showSuccess, setShowSuccess] = useState(false)
  const handleFormSubmit = (formData) => {
    setErrorMessage()
    forgotPassword(formData.email, window.location.href)
      .then(() => {
        setShowSuccess(true)
      })
      .catch(({errors}) => {
        const error = errors?.[0]
          ? errors[0].message
          : 'There was a problem saving the data, please try again later or contact support.'
        setErrorMessage(error)
      })
  }

  return (
    <div className="forgotpassword-component">
      <h2>Forgot password</h2>
      {showSuccess ? (
        <>
          <p>
            Success! Check your email and click the link to reset your password.
          </p>
          <div className="forgotpassword-form">
            <Button
              type={BUTTON_TYPE.PRIMARY}
              size={BUTTON_SIZE.MEDIUM}
              htmlType={BUTTON_HTML_TYPE.SUBMIT}
              onClick={() => setStep(ONBOARDING_STEP.LOGIN)}
            >
              Back to sign in
            </Button>
          </div>
        </>
      ) : (
        <>
          <p>
            Enter your email address below and we’ll send you a password reset
            link.
          </p>
          <form
            className="forgotpassword-form"
            onSubmit={handleSubmit(handleFormSubmit)}
          >
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
                render={({
                  field: {name, onChange, value},
                  fieldState: {error},
                }) => (
                  <Input
                    type={INPUT_TYPE.EMAIL}
                    placeholder="Email"
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
              Send link
            </Button>
            {errorMessage && (
              <span className="form-errorMessage">{errorMessage}</span>
            )}
          </form>
        </>
      )}
    </div>
  )
}

export default ForgotPassword
