import React, {useCallback, useEffect, useRef, useState} from 'react'
import classnames from 'classnames'
import SegmentActions from '../../../../actions/SegmentActions'
import SegmentConstants from '../../../../constants/SegmentConstants'
import SegmentStore from '../../../../stores/SegmentStore'
import CatToolActions from '../../../../actions/CatToolActions'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../common/Button/Button'
import IconChevronLeft from '../../../../../img/icons/IconChevronLeft'
import IconTick from '../../../../../img/icons/IconTick'
import Checkmark from '../../../../../img/icons/Checkmark'

const BulkSelectionBar = ({isReview}) => {
  const [selection, setSelection] = useState({count: 0, segmentsArray: []})
  const [changingStatus, setChangingStatus] = useState(false)

  const segmentsArrayRef = useRef(selection.segmentsArray)
  segmentsArrayRef.current = selection.segmentsArray

  const countInBulkElements = (segments) => {
    let segmentsArray = selection.segmentsArray
    if (segments && segments.size > 0) {
      segments.map(function (segment) {
        let index = segmentsArray.indexOf(segment.get('sid'))
        if (segment.get('inBulk') && index === -1) {
          segmentsArray.push(segment.get('sid'))
        } else if (!segment.get('inBulk') && index > -1) {
          segmentsArray.splice(index, 1)
        }
      })
    }
    setSelection({
      count: segmentsArray.length,
      segmentsArray: segmentsArray,
    })
  }

  const setSegmentsinBulk = useCallback((segments) => {
    setSelection({count: segments.length, segmentsArray: segments})
  }, [])

  const removeAll = useCallback(() => {
    setSelection({count: 0, segmentsArray: []})
  }, [])

  const toggleSegment = useCallback((sid) => {
    setSelection((prev) => {
      const index = prev.segmentsArray.indexOf(sid)
      const array =
        index > -1
          ? prev.segmentsArray.filter((_, i) => i !== index)
          : [...prev.segmentsArray, sid]
      return {count: array.length, segmentsArray: array}
    })
  }, [])

  const onClickBack = () => {
    SegmentActions.removeSegmentsOnBulk()
    setChangingStatus(false)
  }

  const onClickBulk = () => {
    setChangingStatus(true)
    if (isReview) {
      SegmentActions.approveFilteredSegments(selection.segmentsArray).then(
        () => {
          onClickBack()
          CatToolActions.onRender({segmentToOpen: segmentsArrayRef.current[0]})
          CatToolActions.reloadQualityReport()
        },
      )
    } else {
      SegmentActions.translateFilteredSegments(selection.segmentsArray).then(
        () => {
          CatToolActions.onRender({segmentToOpen: segmentsArrayRef.current[0]})
          onClickBack()
        },
      )
    }
    // SegmentActions.closeSegment(SegmentStore.getCurrentSegmentId());
  }

  useEffect(() => {
    // SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, countInBulkElements);
    SegmentStore.addListener(
      SegmentConstants.TOGGLE_SEGMENT_ON_BULK,
      toggleSegment,
    )
    SegmentStore.addListener(
      SegmentConstants.REMOVE_SEGMENTS_ON_BULK,
      removeAll,
    )
    SegmentStore.addListener(
      SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
      setSegmentsinBulk,
    )

    return () => {
      // SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, countInBulkElements);
      SegmentStore.removeListener(
        SegmentConstants.TOGGLE_SEGMENT_ON_BULK,
        toggleSegment,
      )
      SegmentStore.removeListener(
        SegmentConstants.REMOVE_SEGMENTS_ON_BULK,
        removeAll,
      )
      SegmentStore.removeListener(
        SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
        setSegmentsinBulk,
      )
    }
  }, [])

  let buttonClass = classnames({
    'approve-all-segments': true,
    'translated-all-bulked': !isReview,
    'approved-all-bulked': isReview,
    'approved-2nd-pass':
      config.secondRevisionsCount &&
      config.revisionNumber &&
      config.revisionNumber === 2,
  })
  return selection.count > 0 ? (
    <div className="bulk-approve-bar">
      <div className="bulk-back-info">
        <div className="bulk-back">
          <Button
            mode={BUTTON_MODE.GHOST}
            size={BUTTON_SIZE.SMALL}
            onClick={onClickBack}
          >
            <IconChevronLeft size={16} /> back
          </Button>
        </div>
        {selection.count === 1 ? (
          <div className="bulk-info">
            <b>{selection.count} Segment selected</b>
          </div>
        ) : (
          <div className="bulk-info">
            <b>{selection.count} Segments selected</b>
          </div>
        )}
      </div>

      {changingStatus ? (
        <div className="bulk-activity-icons">
          <div className="label-filters labl">
            Applying changes
            <div className="loader" />
          </div>
        </div>
      ) : (
        <div className="bulk-activity-icons">
          <Button
            className={`mark-button ${buttonClass}`}
            type={BUTTON_TYPE.PRIMARY}
            mode={BUTTON_MODE.OUTLINE}
            onClick={onClickBulk}
          >
            <div>
              <Checkmark size={16} />
            </div>
            {isReview ? 'MARK AS APPROVED' : 'MARK AS TRANSLATED'}
          </Button>
        </div>
      )}
    </div>
  ) : null
}

export default BulkSelectionBar
