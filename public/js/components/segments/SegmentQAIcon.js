import React, {useEffect} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentActions from '../../actions/SegmentActions'
import SegmentQA from '../../../img/icons/SegmentQA'

export const SegmentQAIcon = ({sid}) => {
  const [hasGlobalWarnings, setHasGlobalWarnings] = React.useState(false)

  const receiveGlobalWarnings = () => {
    if (sid) {
      setHasGlobalWarnings(SegmentStore.hasGlobalErrors(sid))
    }
  }

  useEffect(() => {
    receiveGlobalWarnings()
  }, [])

  useEffect(() => {
    SegmentStore.addListener(
      SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      receiveGlobalWarnings,
    )
    return () => {
      SegmentStore.removeListener(
        SegmentConstants.UPDATE_GLOBAL_WARNINGS,
        receiveGlobalWarnings,
      )
    }
  }, [])
  return (
    <>
      {hasGlobalWarnings && (
        <div
          className={`icon-warning-sign qa-icon ${config.isReview ? 'review' : ''}`}
          title="Segment with blocking issues"
          onClick={() => {
            SegmentActions.openSegment(sid)
          }}
        >
          <SegmentQA />
        </div>
      )}
    </>
  )
}
