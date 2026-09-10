import React, {useContext} from 'react'
import {SegmentContext} from './SegmentContext'

import SegmentSource from './SegmentSource'
import SegmentTarget from './SegmentTarget'
import SimpleEditor from './SimpleEditor'
import SegmentUtils from '../../utils/segmentUtils'
import SearchUtils from '../header/cattol/search/searchUtils'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

const SegmentWrapper = (props) => {
  const {isTarget} = props
  const {segment} = useContext(SegmentContext)

  if (segment.opened) {
    return isTarget ? (
      <SegmentTarget segment={segment} />
    ) : (
      <SegmentSource segment={segment} />
    )
  }

  let textToDisplay = isTarget ? segment.translation : segment.segment
  textToDisplay = SegmentUtils.checkCurrentSegmentTPEnabled(segment)
    ? DraftMatecatUtils.removeTagsFromText(textToDisplay)
    : textToDisplay

  if (segment.inSearch) {
    textToDisplay = SearchUtils.markText(textToDisplay, !isTarget, segment.sid)
  }
  return (
    <div
      className={`${isTarget ? `target` : `source`} item`}
      id={`segment-${segment.sid}-${isTarget ? 'target' : 'source'}`}
    >
      <SimpleEditor
        className={isTarget ? `targetarea editarea` : ``}
        text={textToDisplay}
        isTarget={isTarget}
        isRtl={isTarget ? config.isTargetRTL : config.isSourceRTL}
      />
    </div>
  )
}

export default SegmentWrapper
