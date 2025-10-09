import React, {useRef} from 'react'
import {useForm} from 'react-hook-form'
import Tooltip from '../../../../common/Tooltip'
import InfoIcon from '../../../../../../img/icons/InfoIcon'
import Checkmark from '../../../../../../img/icons/Checkmark'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'
import IconClose from '../../../../icons/IconClose'
import ExternalLink from '../../../../../../img/icons/ExternalLink'

export const ModernMt = ({
  addMTEngine,
  setAddMTVisible,
  error,
  isRequestInProgress,
}) => {
  const infoIcon1 = useRef()
  const infoIcon2 = useRef()
  const infoIcon3 = useRef()
  const {
    register,
    handleSubmit,
    formState: {errors},
  } = useForm({
    defaultValues: {
      preimport: config.isAnInternalUser ? false : true,
      context_analyzer: config.isAnInternalUser ? false : true,
      pretranslate: config.isAnInternalUser ? true : false,
    },
  })
  const onSubmit = (data) => {
    addMTEngine(data)
  }
  return (
    <div className="add-provider-container">
      <div className="add-provider-message">
        <p>
          <strong>ModernMT</strong> is the first machine translation system that
          adapts to the context of the document and to your translation style,
          learning from your corrections for unprecedented quality output and
          maximum data confidentiality.
        </p>
        <strong>Professional plan for translators:</strong>
        <ul>
          <li>1-month free trial</li>
          <li>$25 per month</li>
          <li>
            Use-based billing: if you don&apos;t use ModernMT during a month, no
            charge is made
          </li>
          <li>Unlimited personal use</li>
        </ul>

        <Button
          className="green-button"
          size={BUTTON_SIZE.MEDIUM}
          onClick={() =>
            window.open(
              'https://www.modernmt.com/license/buy/?plan=professional',
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
            <label>
              ModernMT License<sup>*</sup>
            </label>
            <input
              className="required"
              type="text"
              placeholder="Enter your license"
              {...register('secret', {required: true})}
            />
            {errors.secret && (
              <span className="field-error">Required field</span>
            )}
            {error && <span className={'mt-error'}>{error.message}</span>}
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
