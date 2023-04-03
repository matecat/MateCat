import React from 'react'
import {SegmentContext} from './SegmentContext'

import SegmentSource from './SegmentSource'
import SegmentTarget from './SegmentTarget'
import SimpleEditor from './SimpleEditor'
import TagUtils from '../../utils/tagUtils'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

class SegmentWrapper extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
  }

  render() {
    const {isTarget} = this.props
    const {segment} = this.context

    if (segment.opened) {
      return isTarget ? (
        <SegmentTarget segment={segment} />
      ) : (
        <SegmentSource segment={segment} />
      )
    }
    let textToDisplay = isTarget ? segment.translation : segment.segment
    textToDisplay = TagUtils.transformTextForEditor(
      DraftMatecatUtils.unescapeHTML(textToDisplay),
    )
    return <SimpleEditor text={textToDisplay} isTarget={isTarget} />
  }
}

export default SegmentWrapper
