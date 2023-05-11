import React from 'react'

export const ModernMt = () => {
  return (
    <div className="add-provider-container">
      <div className="add-provider-fields">
        <div className="provider-data">
          <div className="provider-field">
            <label>ModernMT License</label>
            <input
              className="required"
              name="secret"
              type="text"
              placeholder="Enter your license"
            />
          </div>
          <div className="provider-field checkbox first">
            {config.isAnInternalUser ? (
              <input
                className="required"
                name="preimport"
                type="checkbox"
                disabled="disabled"
              />
            ) : (
              <input
                className="required"
                name="preimport"
                type="checkbox"
                checked="checked"
              />
            )}
            <label className="checkbox-label">Pre-import your TMs </label>
          </div>
          <div className="provider-field checkbox">
            {config.isAnInternalUser ? (
              <input
                className="required"
                name="context_analyzer"
                type="checkbox"
                disabled="disabled"
              />
            ) : (
              <input
                className="required"
                name="context_analyzer"
                type="checkbox"
                checked="checked"
              />
            )}
            <label className="checkbox-label">Activate context analyzer</label>
          </div>
          <div className="provider-field checkbox">
            {config.isAnInternalUser ? (
              <input
                className="required"
                name="pretranslate"
                type="checkbox"
                checked="checked"
                disabled="disabled"
              />
            ) : (
              <input className="required" name="pretranslate" type="checkbox" />
            )}
            <label className="checkbox-label">Pre-translate files</label>
          </div>
          <button className="ui primary button">Confirm</button>
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
