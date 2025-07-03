import React from 'react'
import {useForm} from 'react-hook-form'

export const Apertium = ({addMTEngine, error, isRequestInProgress}) => {
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
              name="secret"
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
          <strong>Apertium</strong> is a free/open-source platform for
          rule-based machine translation systems. It is aimed at
          related-language pairs, i.e. Spanish and Portuguese, Norwegian Nynorsk
          and Norwegian Bokmål or Kazakh and Tatar. We provide you with all
          language pairs released in the platform, but please take into account
          that not all of them are useful for postediting. A rule of thumb: the
          closer the languages, the better the performance.
        </p>
        <p>
          <strong>Apertium</strong> is superfast, fully customisable and very
          good for closely-related languages. Don&apos;t think twice and contact
          us if you want to try it out integrated in MateCat!
        </p>
        <p>
          More info on{' '}
          <a href="http://wiki.apertium.org/wiki/Main_Page" title="Apertium">
            http://wiki.apertium.org/wiki/Main_Page
          </a>
        </p>
        <a
          href="mailto:info@prompsit.com"
          rel="noreferrer"
          className="ui positive button"
        >
          Contact Prompsit
        </a>
      </div>
    </div>
  )
}
