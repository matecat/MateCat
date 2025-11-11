import React from 'react'
import {SourceLanguageSelect} from '../../../createProject/SourceLanguageSelect'

export const SourceLanguage = () => {
  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Source language</h3>
        <p>Select the source language for your project.</p>
      </div>
      <div className="options-select-container">
        <SourceLanguageSelect
          isRenderedInsideTab={true}
          dropdownClassName="select-dropdown__wrapper-portal"
        />
      </div>
    </div>
  )
}
