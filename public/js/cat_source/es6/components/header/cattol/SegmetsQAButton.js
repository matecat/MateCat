import React, {useEffect, useState} from 'react'
import CatToolActions from '../../../actions/CatToolActions'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'

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
          ? ''
          : warnings.matecat.WARNING.total && warnings.matecat.WARNING.total > 0
          ? 'numberwarning'
          : warnings.matecat.INFO.total && warnings.matecat.INFO.total > 0
          ? 'numberinfo'
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
    <div
      className={`action-submenu ui floating  ${
        !totalIssues ? 'notific disabled' : ''
      }`}
      id="notifbox"
      title={
        totalIssues > 0
          ? 'Click to see the segments with potential issues'
          : 'Well done, no errors found!'
      }
    >
      <a id="point2seg" onClick={openQA}>
        {warnings && totalIssues > 0 && (
          <span className={`numbererror ${numberClass}`}>{totalIssues}</span>
        )}
      </a>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        x="0"
        y="0"
        enableBackground="new 0 0 42 42"
        version="1.1"
        viewBox="0 0 42 42"
        xmlSpace="preserve"
      >
        <g className="st0">
          <path
            fill="#fff"
            className="st1"
            d="M18.5 26.8l1.8 2.1-1.8 1.5-1.9-2.3c-1 .5-2.2.7-3.5.7-4.9 0-7.9-3.6-7.9-8.3 0-4.7 3-8.3 7.9-8.3s7.9 3.6 7.9 8.3c0 2.6-.9 4.8-2.5 6.3zm-5.4-11.9c-3.2 0-5 2.4-5 5.7 0 3.3 1.8 5.7 5 5.7.6 0 1.2-.1 1.7-.4L13.2 24l1.8-1.4 1.8 2.1c.9-1 1.4-2.4 1.4-4.1-.1-3.3-2-5.7-5.1-5.7z"
          />
          <path
            d="M34.7 28.5l-1.5-4.1h-6.6L25 28.5h-3l6.3-16h3.3l6.3 16h-3.2zM29.9 15l-2.6 7.1h5.1L29.9 15z"
            className="st1"
            fill="#fff"
          />
        </g>
      </svg>
    </div>
  )
}
