import React, {useRef} from 'react'
import {useForm} from 'react-hook-form'
import Tooltip from '../../../../common/Tooltip'
import InfoIcon from '../../../../../../img/icons/InfoIcon'

export const Lara = ({addMTEngine, error, isRequestInProgress}) => {
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
          <div className="provider-field">
            {error && <span className={'mt-error'}>{error.message}</span>}
            <button
              className="ui primary button"
              disabled={isRequestInProgress || !laraLicense || !laraAccessKeyID}
              onClick={handleSubmit(onSubmit)}
            >
              Confirm
            </button>
          </div>
        </div>
      </div>
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
        <a
          href="https://lara.translated.com/about-lara"
          rel="noreferrer"
          className="ui positive button"
          target="_blank"
        >
          Learn More
        </a>
      </div>
    </div>
  )
}
