import React from 'react'

export const MicrosoftHub = () => {
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
              KeyId<sup>*</sup>
            </label>
            <input className="required" name="secret" type="text" />
          </div>
          <div className="provider-field">
            <label>Category</label>
            <input name="category" type="text" />
          </div>

          <button className="ui primary button">Confirm</button>
        </div>
      </div>
      <div className="add-provider-message">
        <p>
          With <strong>Microsoft Translator Hub</strong> you can build your own
          machine translation system starting from your own data and/or public
          data.
        </p>
        <p>
          <strong>Microsoft Translator Hub</strong> will require many hours or
          days to build the system: time varies based on the amount of data
          provided.
        </p>
        <p>
          Once the system is ready, use the parameters provided by{' '}
          <strong>Microsoft Translator Hub</strong> to fill out the form above.
        </p>
        <a
          href="https://hub.microsofttranslator.com/SignIn?returnURL=%2FHome%2FIndex"
          target="_blank"
          rel="noreferrer"
          className="ui positive button"
        >
          Connect your MT system
        </a>
      </div>
    </div>
  )
}
