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

const PasswordReset = () => {
  const {handleSubmit, control} = useForm()

  const handleFormSubmit = (formData) => {
    console.log(formData)
  }

  return (
    <div className="passwordreset-component">
      <h2>Reset password</h2>
      <p>Copy</p>
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
            render={({field: {name, onChange, value}, fieldState: {error}}) => (
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
            name="newpassword"
            rules={{
              required: 'This field is mandatory',
            }}
            render={({field: {name, onChange, value}, fieldState: {error}}) => (
              <Input
                type={INPUT_TYPE.PASSWORD}
                placeholder="New Password"
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
      </form>
    </div>
  )
}

export default PasswordReset
