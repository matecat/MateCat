import React from 'react'
import {transformTagsToHtml} from '../../utils/newTagUtils'

const SimpleEditor = ({className = '', text}) => {
  let htmlText = transformTagsToHtml(text)
  return (
    <div
      data-testid="simple-editor-test"
      className={className}
      dangerouslySetInnerHTML={{__html: htmlText}}
    />
  )
}

export default SimpleEditor
