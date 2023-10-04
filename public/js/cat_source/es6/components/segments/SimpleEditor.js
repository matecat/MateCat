import React from 'react'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

const SimpleEditor = ({className = '', text}) => {
  let htmlText = DraftMatecatUtils.transformTagsToHtml(text)
  return (
    <div
      data-testid="simple-editor-test"
      className={className}
      dangerouslySetInnerHTML={{__html: htmlText}}
    />
  )
}

export default SimpleEditor
