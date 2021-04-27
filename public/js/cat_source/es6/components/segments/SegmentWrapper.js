import React from 'react'
import SegmentSource from './SegmentSource'
import SegmentTarget from './SegmentTarget'
import SimpleEditor from './SimpleEditor'

class SegmentWrapper extends React.Component {
  constructor(props) {
    super(props)
  }

  render() {
    const {segment, isTarget} = this.props

    if (segment.opened) {
      return isTarget ? (
        <SegmentTarget {...this.props} />
      ) : (
        <SegmentSource {...this.props} />
      )
    }

    return (
      <SimpleEditor
        sid={segment.sid}
        segment={segment}
        text={isTarget ? segment.translation : segment.segment}
        isTarget={isTarget}
      />
    )
  }
}

export default SegmentWrapper
