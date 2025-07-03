import React from 'react'
import {useForm} from 'react-hook-form'

export const AltLang = ({addMTEngine, error, isRequestInProgress}) => {
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
              Key<sup>*</sup>
            </label>
            <input
              className="required"
              type="text"
              {...register('secret', {required: true})}
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
          <strong>AltLang</strong> is a language variant converter. It
          automatically replaces the existing differences between two varieties
          of the same language. By perfoming only the necessary changes, AltLang
          reliably adapts content from one variety to another in seconds. A
          quick review of the changes and the work is done!
        </p>
        <p>
          The main phenomena covered by <strong>AltLang</strong> include
          vocabulary, syntax, spelling, style (i.e. different use of
          punctuation, dates and hour formats, etc.) as well as other
          socio-linguistic differences. It currently supports English, French,
          Spanish and Portuguese language variants.
        </p>
        <p>
          <strong>AltLang</strong> is really fast, fully customisable, smart and
          accurate. Give it a try inside MateCat!
        </p>
        <p>
          More info on{' '}
          <a href="http://www.altlang.net/" title="AltLang">
            http://www.altlang.net/
          </a>
        </p>
        <a
          href="mailto:info@altlang.net"
          rel="noreferrer"
          className="ui positive button"
        >
          Contact AltLang
        </a>
      </div>
    </div>
  )
}
