import React from 'react'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

const SimpleEditor = ({className = '', text, isRtl}) => {
  let htmlText = DraftMatecatUtils.transformTagsToHtml(text, isRtl)
  return (
    <div
      data-testid="simple-editor-test"
      className={className}
      dangerouslySetInnerHTML={{__html: htmlText}}
    />
  )
}

export default SimpleEditor
