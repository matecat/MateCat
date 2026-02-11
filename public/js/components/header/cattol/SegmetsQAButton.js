import React, {useEffect, useState} from 'react'
import CatToolActions from '../../../actions/CatToolActions'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import AlertIcon from '../../../../img/icons/AlertIcon'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'
import SearchUtils from './search/searchUtils'
import AlertIconFull from '../../../../img/icons/AlertIconFull'

export const SegmentsQAButton = () => {
  const [warnings, setWarnings] = useState()
  const [totalIssues, setTotalIssues] = useState(0)
  const [numberClass, setNumberClass] = useState('')
  const [qaOpen, setQaOpen] = useState(SearchUtils.searchOpen)

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
    const closeQA = (container) => {
      if (container && container === 'qaComponent') {
        setQaOpen((prevState) => !prevState)
      } else {
        setQaOpen(false)
      }
    }
    CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, closeQA)
    CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, closeQA)
    CatToolStore.addListener(CatToolConstants.SHOW_CONTAINER, closeQA)
    return () => {
      SegmentStore.removeListener(
        SegmentConstants.UPDATE_GLOBAL_WARNINGS,
        updateWarnings,
      )
      CatToolStore.removeListener(CatToolConstants.TOGGLE_CONTAINER, closeQA)
      CatToolStore.removeListener(CatToolConstants.CLOSE_SUBHEADER, closeQA)
      CatToolStore.removeListener(CatToolConstants.SHOW_CONTAINER, closeQA)
    }
  }, [])

  return (
    <Button
      size={BUTTON_SIZE.ICON_STANDARD}
      mode={BUTTON_MODE.GHOST}
      type={BUTTON_TYPE.ICON}
      tooltip={
        totalIssues > 0
          ? 'Click to see the segments with potential issues'
          : 'Well done, no errors found!'
      }
      tooltipPosition="bottom"
      disabled={!totalIssues}
      onClick={openQA}
      className={'qaButton'}
    >
      {qaOpen ? <AlertIconFull size={24} /> : <AlertIcon size={24} />}
      {warnings && totalIssues > 0 && (
        <div className={`button-badge button-badge-${numberClass}`}>
          {totalIssues}
        </div>
      )}
    </Button>
  )
}
