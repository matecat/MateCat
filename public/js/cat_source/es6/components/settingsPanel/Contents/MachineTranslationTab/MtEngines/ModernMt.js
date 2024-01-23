import React, {useRef} from 'react'
import {useForm} from 'react-hook-form'
import Tooltip from '../../../../common/Tooltip'
import InfoIcon from '../../../../../../../../img/icons/InfoIcon'

export const ModernMt = ({addMTEngine, error, isRequestInProgress}) => {
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
          </div>
          <div className="provider-field checkbox first">
            {config.isAnInternalUser ? (
              <input
                className="required"
                type="checkbox"
                disabled="disabled"
                {...register('preimport')}
              />
            ) : (
              <input
                className="required"
                type="checkbox"
                {...register('preimport')}
              />
            )}
            <label className="checkbox-label">Pre-import your TMs </label>
            <Tooltip
              content={
                <div>
                  If the option is enabled, all the TMs linked to your Matecat
                  account
                  <br />
                  will be automatically imported to your ModernMT account for
                  adaptation purposes.
                  <br />
                  If the option is not enabled, the only TMs imported to your
                  ModernMT account
                  <br />
                  will be those used on projects that use ModernMT as their MT
                  engine.
                </div>
              }
            >
              <div ref={infoIcon1}>
                <InfoIcon />
              </div>
            </Tooltip>
          </div>
          <div className="provider-field checkbox">
            {config.isAnInternalUser ? (
              <input
                className="required"
                type="checkbox"
                disabled="disabled"
                {...register('context_analyzer')}
              />
            ) : (
              <input
                className="required"
                type="checkbox"
                {...register('context_analyzer')}
              />
            )}
            <label className="checkbox-label">Activate context analyzer</label>
            <Tooltip
              content={
                <div>
                  If the option is enabled, ModernMT will adapt the suggestions
                  provided for a job
                  <br />
                  using mainly the content of the TMs that you activate for that
                  job and your corrections during translation,
                  <br />
                  but it will also scan all your other TMs for further
                  adaptation based on the context of the document that you are
                  translating.
                  <br />
                  If the option is not enabled, ModernMT will only adapt based
                  on the TMs that you activate for a job and on your corrections
                  during translation.
                </div>
              }
            >
              <div ref={infoIcon2}>
                <InfoIcon />
              </div>
            </Tooltip>
          </div>
          <div className="provider-field checkbox">
            {config.isAnInternalUser ? (
              <input
                className="required"
                type="checkbox"
                disabled="disabled"
                {...register('pretranslate')}
              />
            ) : (
              <input
                className="required"
                type="checkbox"
                {...register('pretranslate')}
              />
            )}
            <label className="checkbox-label">Pre-translate files</label>
            <Tooltip
              content={
                <div>
                  If the option is enabled, ModernMT is used during the analysis
                  phase.
                  <br />
                  This makes downloading drafts from the translation interface
                  quicker,
                  <br />
                  but may lead to additional charges for plans other than the
                  "Professional" one.
                  <br />
                  If the option is not enabled, ModernMT is only used to provide
                  adaptive
                  <br />
                  suggestions when opening segments.
                </div>
              }
            >
              <div ref={infoIcon3}>
                <InfoIcon />
              </div>
            </Tooltip>
          </div>
          <div className="provider-field">
            {error && <span className={'mt-error'}>{error.message}</span>}
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

        <a
          href="https://www.modernmt.com/license/buy/?plan=professional"
          rel="noreferrer"
          className="ui positive button"
          target="_blank"
        >
          Buy Online
        </a>
      </div>
    </div>
  )
}
