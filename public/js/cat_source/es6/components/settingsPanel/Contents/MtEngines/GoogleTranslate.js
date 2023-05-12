import React from 'react'

export const GoogleTranslate = () => {
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
              API-key<sup>*</sup>
            </label>
            <input className="required" name="secret" type="text" />
          </div>

          <button className="ui primary button">Confirm</button>
        </div>
      </div>
      <div className="add-provider-message">
        <p>
          Google Translate is a free multilingual machine translation service
          developed by Google, to translate text from one language into another.
          It offers a website interface, mobile apps for Android and iOS, and an
          API that helps developers build browser extensions and software
          applications. Google Translate supports over 100 languages at various
          levels and as of May 2017, serves over 500 million people daily.
        </p>
        <a
          href="https://cloud.google.com/translate/"
          target="_blank"
          rel="noreferrer"
          className="ui positive button"
        >
          Find out more
        </a>
      </div>
    </div>
  )
}
