import React from 'react'
import {SourceLanguageSelect} from '../../../createProject/SourceLanguageSelect'

export const SourceLanguage = () => {
  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Source language</h3>Select source.
      </div>
      <div className="options-select-container">
        <SourceLanguageSelect shouldHideLabel={true} />
      </div>
    </div>
  )
}
