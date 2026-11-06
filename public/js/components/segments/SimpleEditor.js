import React from 'react'
import SegmentStringUtils from './utils/DraftMatecatUtils/segmentStringUtils'

const SimpleEditor = ({className = '', text, isRtl}) => {
  let htmlText = SegmentStringUtils.transformTagsToHtml(text, isRtl)
  return (
    <div
      data-testid="simple-editor-test"
      className={className}
      dangerouslySetInnerHTML={{__html: htmlText}}
      lang={config.target_code}
    />
  )
}

export default SimpleEditor
