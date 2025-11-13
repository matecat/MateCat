import React, {useRef} from 'react'
import {useForm} from 'react-hook-form'
import Tooltip from '../../../../common/Tooltip'
import InfoIcon from '../../../../../../img/icons/InfoIcon'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'
import ExternalLink from '../../../../../../img/icons/ExternalLink'
import IconClose from '../../../../icons/IconClose'
import Checkmark from '../../../../../../img/icons/Checkmark'

export const Lara = ({
  addMTEngine,
  setAddMTVisible,
  error,
  isRequestInProgress,
}) => {
  const infoIcon1 = useRef()

  const {
    register,
    handleSubmit,
    watch,
    formState: {errors},
  } = useForm()

  const laraAccessKeyID = watch('lara-access-key-id')
  const laraLicense = watch('secret')

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
            >
              200+ languages
            </a>
          </strong>
          . <br />
          Lara is only available for machine translation post-editing,
          pre-translation is always performed with ModernMT Lite, or your
          personal ModernMT Full engine if a valid license is provided.
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
            <div className="provider-field-row">
              <div className="provider-license-label-with-icon">
                <label>ModernMT License</label>
                <Tooltip
                  content={
                    <div>
                      (Optional) Enter your ModernMT license to use your
                      personal ModernMT Full engine for pre-translation.
                    </div>
                  }
                >
                  <div ref={infoIcon1}>
                    <InfoIcon />
                  </div>
                </Tooltip>
              </div>
              <input
                className="required"
                type="text"
                placeholder="Enter your ModernMT license (optional)"
                {...register('mmt-license')}
              />
            </div>
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
