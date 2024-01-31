import React from 'react'

import Shortcuts from '../../utils/shortcuts'
import SegmentWrapper from './SegmentWrapper'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'

class SegmentBody extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    this.state = {
      clickedTagId: null,
      clickedTagText: null,
      tagClickedInSource: false,
    }
  }

  copySource(e) {
    e.preventDefault()
    SegmentActions.copySourceToTarget(this.context.segment.sid)
  }

  render() {
    let copySourceShortcuts = UI.isMac
      ? Shortcuts.cattol.events.copySource.keystrokes.mac
      : Shortcuts.cattol.events.copySource.keystrokes.standard
    return (
      <div
        onClick={this.props.onClick}
        className="text segment-body-content"
        ref={(body) => (this.segmentBody = body)}
      >
        <div className="wrap">
          <div className="outersource">
            <SegmentWrapper />

            <div
              className="copy"
              title="Copy source to target"
              onClick={(e) => this.copySource(e)}
            >
              <a href="#" />
              <p>{copySourceShortcuts.toUpperCase()}</p>
            </div>

            <SegmentWrapper isTarget />
          </div>
        </div>
        <div className="status-container">
          <a href="#" className="status no-hover" />
        </div>
      </div>
    )
  }
}

export default SegmentBody
