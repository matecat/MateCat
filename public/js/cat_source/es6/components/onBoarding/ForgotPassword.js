import React from 'react'
import {useForm, Controller} from 'react-hook-form'
import {EMAIL_PATTERN} from '../../constants/Constants'
import {INPUT_TYPE, Input} from '../common/Input/Input'
import {
  BUTTON_HTML_TYPE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../common/Button/Button'

const ForgotPassword = () => {
  const {handleSubmit, control} = useForm()

  const handleFormSubmit = (formData) => {
    console.log(formData)
  }

  return (
    <div className="forgotpassword-component">
      <h2>Forgot password</h2>
      <p>
        Enter your email address below and we’ll send you a password reset link.
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
            render={({field: {name, onChange, value}, fieldState: {error}}) => (
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
      </form>
    </div>
  )
}

export default ForgotPassword
