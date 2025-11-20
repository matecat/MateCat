import React from 'react'
import {useForm} from 'react-hook-form'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'
import IconClose from '../../../../icons/IconClose'
import Checkmark from '../../../../../../img/icons/Checkmark'
import ExternalLink from '../../../../../../img/icons/ExternalLink'

export const DeepL = ({
  addMTEngine,
  setAddMTVisible,
  error,
  isRequestInProgress,
}) => {
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
        <Button
          className="green-button"
          size={BUTTON_SIZE.MEDIUM}
          onClick={() =>
            window.open(
              'https://www.deepl.com/pro/change-plan?cta=header-pro#single',
              '_blank',
            )
          }
        >
          Learn more
          <ExternalLink size={16} />
        </Button>
      </div>
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
            {typeof error?.message === 'string' && (
              <span className="field-error">{error?.message}</span>
            )}
          </div>

          <div className="provider-field container-actions">
            <Button
              type={BUTTON_TYPE.WARNING}
              onClick={() => setAddMTVisible(false)}
            >
              <IconClose size={11} />
            </Button>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              mode={BUTTON_MODE.BASIC}
              size={BUTTON_SIZE.MEDIUM}
              disabled={isRequestInProgress}
              onClick={handleSubmit(onSubmit)}
            >
              <Checkmark size={12} />
              Confirm
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
