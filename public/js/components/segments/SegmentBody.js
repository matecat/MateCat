import React, {useContext} from 'react'

import {Shortcuts} from '../../utils/shortcuts'
import SegmentWrapper from './SegmentWrapper'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'
import {isMacOS} from '../../utils/Utils'

export const SegmentBody = ({onClick}) => {
  const {segment} = useContext(SegmentContext)

  const copySource = (e) => {
    e.preventDefault()
    SegmentActions.copySourceToTarget(segment.sid)
  }

  let copySourceShortcuts = isMacOS()
    ? Shortcuts.cattol.events.copySource.keystrokes.mac
    : Shortcuts.cattol.events.copySource.keystrokes.standard
  return (
    <div onClick={onClick} className="text segment-body-content">
      <div className="wrap">
        <div className="outersource">
          <SegmentWrapper />

          <div
            className="copy"
            title="Copy source to target"
            onClick={(e) => copySource(e)}
          >
            <a href="#" />
            <p>{copySourceShortcuts.toUpperCase()}</p>
          </div>

          <SegmentWrapper isTarget={true} />
        </div>
      </div>
      <div className="status-container">
        <a href="#" className="status no-hover" />
      </div>
    </div>
  )
}

export default SegmentBody
