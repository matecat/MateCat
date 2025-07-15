import React from 'react'
import {useForm} from 'react-hook-form'

export const MicrosoftHub = ({addMTEngine, error, isRequestInProgress}) => {
  const {
    register,
    handleSubmit,
    formState: {errors},
  } = useForm()
  const onSubmit = (data) => {
    addMTEngine(data)
  }
  return (
    <div className="add-provider-container">
      <div className="add-provider-fields">
        <div className="provider-data">
          <div className="provider-field">
            <label>
              Engine Name<sup>*</sup>
            </label>
            <input
              className="new-engine-name required"
              type="text"
              {...register('name', {required: true})}
            />
            {errors.name && <span className="field-error">Required field</span>}
          </div>
          <div className="provider-field">
            <label>
              KeyId<sup>*</sup>
            </label>
            <input
              className="required"
              name="secret"
              type="text"
              {...register('secret', {required: true})}
            />
            {errors.secret && (
              <span className="field-error">Required field</span>
            )}
          </div>
          <div className="provider-field">
            <label>Category</label>
            <input name="category" type="text" {...register('category')} />
          </div>

          <div className="provider-field">
            {error && (
              <span className={'mt-error'}>
                {error.message ? error.message : 'KeyId not valid'}
              </span>
            )}
            <button
              className="ui primary button"
              disabled={isRequestInProgress}
              onClick={handleSubmit(onSubmit)}
            >
              Confirm
            </button>
          </div>
        </div>
      </div>
      <div className="add-provider-message">
        <p>
          With <strong>Microsoft Translator Hub</strong> you can build your own
          machine translation system starting from your own data and/or public
          data.
        </p>
        <p>
          <strong>Microsoft Translator Hub</strong> will require many hours or
          days to build the system: time varies based on the amount of data
          provided.
        </p>
        <p>
          Once the system is ready, use the parameters provided by{' '}
          <strong>Microsoft Translator Hub</strong> to fill out the form above.
        </p>
        <a
          href="https://hub.microsofttranslator.com/SignIn?returnURL=%2FHome%2FIndex"
          target="_blank"
          rel="noreferrer"
          className="ui positive button"
        >
          Connect your MT system
        </a>
      </div>
    </div>
  )
}
