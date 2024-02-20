import React from 'react'
import {useForm} from 'react-hook-form'

export const DeepL = ({addMTEngine, error, isRequestInProgress}) => {
  const {
    register,
    handleSubmit,
    formState: {errors},
  } = useForm({
    defaultValues: {name: 'DeepL'},
  })
  const onSubmit = (data) => {
    addMTEngine(data)
  }
  return (
    <div className="add-provider-container">
      <div className="add-provider-fields">
        <div className="provider-data">
          <div className="provider-field">
            <label>DeepL API key</label>
            <input
              className="required"
              type="text"
              placeholder="Enter your license"
              {...register('client_id', {required: true})}
            />
            {errors.secret && (
              <span className="field-error">Required field</span>
            )}
          </div>

          <div className="provider-field">
            {error && <span className={'mt-error'}>{error.message}</span>}
            <button
              disabled={isRequestInProgress}
              className="ui primary button"
              onClick={handleSubmit(onSubmit)}
            >
              Confirm
            </button>
          </div>
        </div>
      </div>
      <div className="add-provider-message">
        <p>
          <strong>DeepL Translator</strong> is a neural machine translation
          engine available in 31 languages that can be used to retrieve machine
          translation suggestions for post editing. <br />
          The engine's output can be customized by choosing a formality level
          for the target language and uploading a glossary to make sure that the
          MT output reflects your preferred terminology.
        </p>
        <p>
          <strong>Note: </strong>as per DeepL's policy, Matecat's integration
          with DeepL is only available for subscribers of DeepL's "Advanced" and
          "Ultimate" plans.
        </p>
        <a
          href="https://www.deepl.com/pro/change-plan?cta=header-pro#single"
          rel="noreferrer"
          className="ui positive button"
          target="_blank"
        >
          More details
        </a>
      </div>
    </div>
  )
}
