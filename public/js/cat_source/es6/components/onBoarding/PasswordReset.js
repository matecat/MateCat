import React, {useContext, useState} from 'react'
import {useForm, Controller} from 'react-hook-form'
import {INPUT_TYPE, Input} from '../common/Input/Input'
import {
  BUTTON_HTML_TYPE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../common/Button/Button'
import {resetPasswordUser} from '../../api/resetPasswordUser'
import {ONBOARDING_STEP, OnBoardingContext} from './OnBoarding'

const PasswordReset = () => {
  const {handleSubmit, control} = useForm()
  const {setStep} = useContext(OnBoardingContext)

  const [errorMessage, setErrorMessage] = useState()
  const [showSuccess, setShowSuccess] = useState(false)
  const handleFormSubmit = (formData) => {
    setErrorMessage()
    resetPasswordUser(
      formData.password,
      formData.newpassword,
      formData.newpassword,
    )
      .then(() => {
        setShowSuccess(true)
      })
      .catch((errors) => {
        const text =
          errors && errors.length && errors[0].code === 0
            ? errors[0].message
            : 'There was a problem saving the data, please try again later or contact support.'
        setErrorMessage(text)
      })
  }

  const passwordRules = {
    required: 'This field is mandatory',
    minLength: {
      value: 12,
      message: 'Password must be at least 12 characters',
    },
    pattern: {
      value: /[!"#$%&'()*+,-./:;<=>?@[\]^_`{|}~]/g,
      message:
        'Password must contain at least one special character: !"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~',
    },
  }

  return (
    <div className="passwordreset-component">
      <h2>Reset password</h2>
      {showSuccess ? (
        <>
          <p>
            Your password has been changed. You can now use the new password to
            log in.
          </p>
          <div className="passwordreset-form">
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
          <form
            className="passwordreset-form"
            onSubmit={handleSubmit(handleFormSubmit)}
          >
            <fieldset>
              <Controller
                control={control}
                defaultValue=""
                name="password"
                rules={{
                  required: 'This field is mandatory',
                }}
                render={({
                  field: {name, onChange, value},
                  fieldState: {error},
                }) => (
                  <Input
                    type={INPUT_TYPE.PASSWORD}
                    placeholder="Password"
                    {...{name, value, onChange, error}}
                  />
                )}
              />
            </fieldset>
            <fieldset>
              <Controller
                control={control}
                defaultValue=""
                name="newPassword"
                rules={passwordRules}
                render={({
                  field: {name, onChange, value},
                  fieldState: {error},
                }) => (
                  <Input
                    type={INPUT_TYPE.PASSWORD}
                    placeholder="New password"
                    {...{name, value, onChange, error}}
                  />
                )}
              />
            </fieldset>
            <fieldset>
              <Controller
                control={control}
                defaultValue=""
                name="confirmNewPassword"
                rules={{
                  required: 'This field is mandatory',
                  validate: (value, formValues) =>
                    value !== formValues.newPassword
                      ? "Passwords don't match"
                      : true,
                }}
                render={({
                  field: {name, onChange, value},
                  fieldState: {error},
                }) => (
                  <Input
                    type={INPUT_TYPE.PASSWORD}
                    placeholder="Confirm new password"
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
              Reset
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

export default PasswordReset
