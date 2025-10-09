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

export const Yandex = ({
  addMTEngine,
  setAddMTVisible,
  error,
  isRequestInProgress,
}) => {
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
      <div className="add-provider-message">
        <p>
          Yandex.Translate is a modern statistical MT system available as an
          online public service, an application for major mobile platforms and a
          commercial API. The service currently supports more than 90 languages
          and this number is continuously growing. Yandex.Translate API
          processes billions of translations daily for its customers ranging
          from LSPs and language learning applications to worldwide media
          monitoring companies. Customer data security is a key priority for
          Yandex.Translate API - it uses secure connections and carefully
          handles sensitive data.
        </p>

        <Button
          className="green-button"
          size={BUTTON_SIZE.MEDIUM}
          onClick={() =>
            window.open('https://translate.yandex.com/developers', '_blank')
          }
        >
          Learn more
          <ExternalLink size={16} />
        </Button>
      </div>
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
              API-key<sup>*</sup>
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
