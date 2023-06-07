import React from 'react'
import Switch from '../../../common/Switch'

export const Lexiqa = () => {
  return (
    <div className="options-box qa-box">
      {/*  Lexiqa
    TODO: check lexiqa licence active
  */}
      <h3>
        QA by <img src="/public/img/lexiqa-new-2.png" />
      </h3>
      <p>
        <span className="option-qa-box-languages">
          Not available for:
          <span className="option-notsupported-languages"></span>.
          <br />
        </span>
        Linguistic QA with automated checks for punctuation, numerals, links,
        symbols, etc.
        <span className="tooltip-lexiqa">Supported languages</span>
      </p>
      {/*<p >
        Request your license key at <a href="https://www.lexiqa.net">https://www.lexiqa.net</a>
      </p>*/}
      <Switch />
    </div>
  )
}
