import React from 'react'
import {useForm} from 'react-hook-form'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'
import ExternalLink from '../../../../../../img/icons/ExternalLink'
import IconClose from '../../../../../../img/icons/IconClose'
import Checkmark from '../../../../../../img/icons/Checkmark'

export const Lara = ({
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
          <strong>Lara</strong> is a groundbreaking machine translation engine
          powered by Large Language Models. It surpasses traditional machine
          translation by{' '}
          <b>
            understanding context and learning from previously translated
            content
          </b>
          , delivering high-quality, nuanced translations.
          <br />
          Lara currently supports all combinations of{' '}
          <strong>
            <a
              href="https://guides.matecat.com/what-languages-does-lara-support"
              target={'_blank'}
              rel="noreferrer"
            >
              200+ languages
            </a>
          </strong>
          . <br />
          Lara is only available for machine translation post-editing,
          pre-translation is always performed with ModernMT Lite.
        </p>
        <br />
        <Button
          className="green-button"
          size={BUTTON_SIZE.MEDIUM}
          onClick={() =>
            window.open('https://lara.translated.com/about-lara', '_blank')
          }
        >
          Learn more
          <ExternalLink size={16} />
        </Button>
      </div>
      <div className="add-provider-fields">
        <div className="provider-data provider-data-lara">
          <div className="provider-field">
            <div className="provider-field-row">
              <label>
                Lara Access Key ID<sup>*</sup>
              </label>
              <input
                className="required"
                type="text"
                placeholder="Enter your access key ID"
                {...register('lara-access-key-id', {required: true})}
              />
            </div>
            <div className="provider-field-row">
              <label>
                Lara Access Key Secret<sup>*</sup>
              </label>
              <input
                className="required"
                type="text"
                placeholder="Enter your access key secret"
                {...register('secret', {required: true})}
              />
            </div>
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
              size={BUTTON_SIZE.ICON_STANDARD}
              onClick={() => setAddMTVisible(false)}
            >
              <IconClose size={18} />
            </Button>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              mode={BUTTON_MODE.BASIC}
              disabled={isRequestInProgress}
              onClick={handleSubmit(onSubmit)}
            >
              <Checkmark size={18} />
              Confirm
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
