import React from 'react'
import {SegmentContext} from './SegmentContext'

import SegmentSource from './SegmentSource'
import SegmentTarget from './SegmentTarget'
import SimpleEditor from './SimpleEditor'

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

    return (
      <SimpleEditor
        text={isTarget ? segment.translation : segment.segment}
        isTarget={isTarget}
      />
    )
  }
}

export default SegmentWrapper
