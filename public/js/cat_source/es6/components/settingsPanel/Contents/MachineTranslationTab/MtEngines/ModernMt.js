import React from 'react'
import {useForm} from 'react-hook-form'

export const ModernMt = ({addMTEngine, error}) => {
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
                checked="checked"
                {...register('preimport')}
              />
            )}
            <label className="checkbox-label">Pre-import your TMs </label>
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
                checked="checked"
                {...register('context_analyzer')}
              />
            )}
            <label className="checkbox-label">Activate context analyzer</label>
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
          </div>
          <div className="provider-field">
            {error && <span className={'mt-error'}>{error.message}</span>}
            <button
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
