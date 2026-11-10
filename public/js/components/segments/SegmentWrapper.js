import React, {useContext} from 'react'
import {SegmentContext} from './SegmentContext'

import SegmentSource from './SegmentSource'
import SegmentTarget from './SegmentTarget'
import SegmentView from '../segmentView/SegmentView'
import SegmentUtils from '../../utils/segmentUtils'
import SearchUtils from '../header/cattol/search/searchUtils'
import SegmentStringUtils from './utils/DraftMatecatUtils/segmentStringUtils'
import {markableText} from '../segmentView/segmentModel'
import SourceEditor from '../editor/matecat/SourceEditor'
import {isNewEditorActive} from '../editor/matecat/editorEngine'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {sourceEditorBridge} from './sourceEditorBridge'

const SegmentWrapper = ({isTarget}) => {
  const {segment} = useContext(SegmentContext)
  const {userInfo} = useContext(ApplicationWrapperContext)

  if (segment.opened) {
    if (isTarget) return <SegmentTarget segment={segment} />
    if (!isNewEditorActive(userInfo?.metadata))
      return <SegmentSource segment={segment} />
    return (
      <SourceEditor
        segment={segment}
        value={
          SegmentUtils.checkCurrentSegmentTPEnabled(segment)
            ? SegmentStringUtils.removeTagsFromText(segment.segment)
            : segment.segment
        }
        bridge={sourceEditorBridge}
      />
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
