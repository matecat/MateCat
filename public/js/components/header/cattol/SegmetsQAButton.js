import React, {useEffect, useState} from 'react'
import CatToolActions from '../../../actions/CatToolActions'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../common/Button/Button'
import QAICon from '../../../../img/icons/QAICon'

export const SegmentsQAButton = () => {
  const [warnings, setWarnings] = useState()
  const [totalIssues, setTotalIssues] = useState(0)
  const [numberClass, setNumberClass] = useState('')
  const openQA = (event) => {
    event.preventDefault()
    CatToolActions.toggleQaIssues()
  }

  useEffect(() => {
    const updateWarnings = (warnings) => {
      setTotalIssues(warnings.matecat.total)
      setWarnings(warnings)
      const iconClass =
        warnings.matecat.ERROR.total && warnings.matecat.ERROR.total > 0
          ? 'error'
          : warnings.matecat.WARNING.total && warnings.matecat.WARNING.total > 0
            ? 'warning'
            : warnings.matecat.INFO.total && warnings.matecat.INFO.total > 0
              ? 'info'
              : ''
      setNumberClass(iconClass)
    }
    SegmentStore.addListener(
      SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      updateWarnings,
    )
    return () => {
      SegmentStore.removeListener(
        SegmentConstants.UPDATE_GLOBAL_WARNINGS,
        updateWarnings,
      )
    }
  }, [])

  return (
    <Button
      size={BUTTON_SIZE.ICON_STANDARD}
      mode={BUTTON_MODE.GHOST}
      tooltip={
        totalIssues > 0
          ? 'Click to see the segments with potential issues'
          : 'Well done, no errors found!'
      }
      tooltipPosition="bottom"
      disabled={!totalIssues}
      onClick={openQA}
    >
      <QAICon size={20} />
      {warnings && totalIssues > 0 && (
        <div className={`button-badge button-badge-${numberClass}`}>
          {totalIssues}
        </div>
      )}
    </Button>
  )
}
