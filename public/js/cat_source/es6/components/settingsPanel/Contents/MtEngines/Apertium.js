import React from 'react'

export const Apertium = () => {
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
              name="engine-name"
              type="text"
            />
          </div>
          <div className="provider-field">
            <label>
              Key<sup>*</sup>
            </label>
            <input className="required" name="secret" type="text" />
          </div>

          <button className="ui primary button">Confirm</button>
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
          good for closely-related languages. Don't think twice and contact us
          if you want to try it out integrated in MateCat!
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
