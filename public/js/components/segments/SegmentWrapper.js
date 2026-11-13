import React, {useContext} from 'react'
import {SegmentContext} from './SegmentContext'

import SegmentSource from './SegmentSource'
import SegmentTarget from './SegmentTarget'
import SegmentView from '../segmentView/SegmentView'
import SegmentUtils from '../../utils/segmentUtils'
import SearchUtils from '../header/cattol/search/searchUtils'
import SegmentStringUtils from './utils/DraftMatecatUtils/segmentStringUtils'
import {markableText} from '../segmentView/segmentModel'

const SegmentWrapper = ({isTarget}) => {
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
    ? SegmentStringUtils.removeTagsFromText(textToDisplay)
    : textToDisplay

  const isRtl = isTarget ? config.isTargetRTL : config.isSourceRTL
  const searchMarks = segment.inSearch
    ? SearchUtils.getSearchMarks(
        markableText(textToDisplay, {isRtl: !!isRtl}),
        !isTarget,
        segment.sid,
      )
    : undefined

  return (
    <div
      className={`${isTarget ? `target` : `source`} item`}
      id={`segment-${segment.sid}-${isTarget ? 'target' : 'source'}`}
    >
      <SegmentView
        as="div"
        className={isTarget ? `targetarea editarea` : ``}
        segment={textToDisplay}
        isRtl={!!isRtl}
        marks={searchMarks}
        lang={isTarget ? config.target_code : config.source_code}
      />
    </div>
  )
}

export default SegmentWrapper
