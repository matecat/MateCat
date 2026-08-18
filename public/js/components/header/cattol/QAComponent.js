import React, {useCallback, useEffect, useRef, useState} from 'react'

import SegmentActions from '../../../actions/SegmentActions'
import LXQ from '../../../utils/lxq.main'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import SegmentQA from '../../../../img/icons/SegmentQA'
import AlertIcon from '../../../../img/icons/AlertIcon'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import InfoIcon from '../../../../img/icons/InfoIcon'
import ChevronLeft from '../../../../img/icons/ChevronLeft'
import ChevronRight from '../../../../img/icons/ChevronRight'

const QAComponent = (props) => {
  const [navigationList, setNavigationList] = useState([])
  const [navigationIndex, setNavigationIndex] = useState(0)
  const [currentPriority, setCurrentPriority] = useState('')
  const [currentCategory, setCurrentCategory] = useState('')
  const [labels] = useState({
    TAG: 'Tag',
    TAGS: 'Tag',
    lexiqa: 'Lexiqa',
    GLOSSARY: 'Glossary',
    MISMATCH: 'T. Conflicts',
    FUZZY: 'Unedited fuzzy matches',
  })
  const [totalWarnings, setTotalWarnings] = useState(0)
  const [warnings, setWarnings] = useState({
    ERROR: {
      Categories: {},
      total: 0,
    },
    WARNING: {
      Categories: {},
      total: 0,
    },
    INFO: {
      Categories: {},
      total: 0,
    },
  })

  const latestRef = useRef({currentPriority: '', currentCategory: ''})
  latestRef.current = {currentPriority, currentCategory}

  const scrollToSegment = (increment) => {
    let newIndex = navigationIndex + increment
    newIndex =
      newIndex === -1
        ? navigationList.length - 1
        : newIndex % navigationList.length

    let segmentId = navigationList[newIndex]

    if (segmentId) {
      SegmentActions.openSegment(segmentId)
    }
    setNavigationIndex(newIndex)
  }

  const setCurrentNavigationElements = (list, priority, category) => {
    let segmentId = list[0]

    if (segmentId) {
      setTimeout(function () {
        SegmentActions.scrollToSegment(segmentId)
      })
      SegmentActions.openSegment(segmentId)
    }

    setNavigationList(list)
    setNavigationIndex(0)
    setCurrentPriority(priority)
    setCurrentCategory(category)
  }

  const allowHTML = (string) => {
    return {__html: string}
  }

  const receiveGlobalWarnings = useCallback((warnings) => {
    const {currentPriority, currentCategory} = latestRef.current
    const category = warnings.matecat[currentPriority]
      ? warnings.matecat[currentPriority].Categories[currentCategory]
      : null
    if (warnings && category) {
      setWarnings(warnings.matecat)
      setTotalWarnings(warnings.matecat.total)
      setNavigationList(category)
    } else {
      setWarnings(warnings.matecat)
      setTotalWarnings(warnings.matecat.total)
      setNavigationList([])
    }
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

  let mismatch = '',
    error = [],
    warning = [],
    info = []
  if (warnings) {
    if (warnings.ERROR?.total > 0) {
      Object.keys(warnings.ERROR.Categories).map((key, index) => {
        if (warnings.ERROR.Categories[key].length > 0) {
          if (key === 'TAGS') {
            let activeClass =
              currentPriority === 'ERROR' && currentCategory === key
                ? ' mc-bg-gray'
                : ''
            error.push(
              <Button
                key={index}
                type={BUTTON_TYPE.CRITICAL}
                mode={BUTTON_MODE.OUTLINE}
                className={activeClass}
                onClick={() =>
                  setCurrentNavigationElements(
                    warnings.ERROR.Categories[key],
                    'ERROR',
                    key,
                  )
                }
              >
                <SegmentQA size={20} />
                {labels[key] ? labels[key] : key} errors
                <b> ({warnings.ERROR.Categories[key].length})</b>
              </Button>,
            )
          } else {
            let activeClass =
              currentPriority === 'ERROR' && currentCategory === key
                ? ' mc-bg-gray'
                : ''
            error.push(
              <Button
                key={index}
                type={BUTTON_TYPE.CRITICAL}
                mode={BUTTON_MODE.OUTLINE}
                className={activeClass}
                onClick={() =>
                  setCurrentNavigationElements(
                    warnings.ERROR.Categories[key],
                    'ERROR',
                    key,
                  )
                }
              >
                <SegmentQA size={20} />
                {labels[key] ? labels[key] : key}
                <b> ({warnings.ERROR.Categories[key].length})</b>
              </Button>,
            )
          }
        }
      })
    }
    if (warnings.WARNING.total > 0) {
      Object.keys(warnings.WARNING.Categories).map((key, index) => {
        if (warnings.WARNING.Categories[key].length > 0) {
          let activeClass =
            currentPriority === 'WARNING' && currentCategory === key
              ? ' mc-bg-gray'
              : ''
          if (key === 'TAGS') {
            warning.push(
              <Button
                key={index}
                type={BUTTON_TYPE.WARNING}
                mode={BUTTON_MODE.OUTLINE}
                className={activeClass}
                onClick={() =>
                  setCurrentNavigationElements(
                    warnings.WARNING.Categories[key],
                    'WARNING',
                    key,
                  )
                }
              >
                <AlertIcon size={20} />
                {labels[key] ? labels[key] : key} warnings
                <b> ({warnings.WARNING.Categories[key].length}) </b>
              </Button>,
            )
          } else if (key !== 'MISMATCH') {
            warning.push(
              <Button
                key={index}
                type={BUTTON_TYPE.WARNING}
                mode={BUTTON_MODE.OUTLINE}
                className={activeClass}
                onClick={() =>
                  setCurrentNavigationElements(
                    warnings.WARNING.Categories[key],
                    'WARNING',
                    key,
                  )
                }
              >
                <AlertIcon size={20} />
                {labels[key] ? labels[key] : key}
                <b> ({warnings.WARNING.Categories[key].length}) </b>
              </Button>,
            )
          } else {
            mismatch = (
              <Button
                key={index}
                type={BUTTON_TYPE.WARNING}
                mode={BUTTON_MODE.OUTLINE}
                className={activeClass}
                onClick={() =>
                  setCurrentNavigationElements(
                    warnings.WARNING.Categories[key],
                    'WARNING',
                    key,
                  )
                }
              >
                <AlertIcon size={20} />
                {labels[key] ? labels[key] : key}
                <b> ({warnings.WARNING.Categories[key].length}) </b>
              </Button>
            )
          }
        }
      })
    }
    if (warnings.INFO.total > 0) {
      Object.keys(warnings.INFO.Categories).map((key, index) => {
        if (warnings.INFO.Categories[key].length > 0) {
          let activeClass =
            currentPriority === 'INFO' && currentCategory === key
              ? ' mc-bg-gray'
              : ''
          info.push(
            <Button
              key={index}
              className={activeClass}
              type={BUTTON_TYPE.INFO}
              mode={BUTTON_MODE.OUTLINE}
              onClick={() =>
                setCurrentNavigationElements(
                  warnings.INFO.Categories[key],
                  'INFO',
                  key,
                )
              }
            >
              <InfoIcon size={20} />
              {labels[key] ? labels[key] : key}{' '}
              <b> ({warnings.INFO.Categories[key].length})</b>
            </Button>,
          )
        }
      })
    }
  }
  let segmentsWithActive =
    error.length > 0 || warning.length > 0 || info.length > 0
  return props.active && totalWarnings > 0 ? (
    <div className="qa-wrapper">
      <div className="qa-container">
        <div className="qa-container-inside">
          <div className="qa-issues-list">
            {segmentsWithActive ? (
              <div className="label-issues label-issues-segment">
                Segments with:
              </div>
            ) : null}
            {segmentsWithActive ? (
              <div>
                {error}
                {warning}
                {info}
              </div>
            ) : null}
            {currentPriority === 'INFO' && currentCategory === 'lexiqa' ? (
              <div className="qa-lexiqa-info">
                <span>QA:</span>
                <a
                  href={config.lexiqaServer + '/documentation.html'}
                  target="_blank"
                  rel="noreferrer"
                >
                  Guide
                </a>
                <a
                  target="_blank"
                  rel="noreferrer"
                  alt="Read the full QA report"
                  href={
                    config.lexiqaServer +
                    '/errorreport?id=' +
                    LXQ.partnerid +
                    '-' +
                    config.id_job +
                    '-' +
                    config.password +
                    '&type=' +
                    (config.isReview ? 'revise' : 'translate')
                  }
                >
                  Report
                </a>
              </div>
            ) : null}
            {mismatch ? (
              <div className="label-issues labl">Repetitions with:</div>
            ) : null}
            {mismatch ? (
              <div className="qa-mismatch">
                <div>{mismatch}</div>
              </div>
            ) : null}
          </div>
          {navigationList.length > 0 ? (
            <div className="qa-issues-navigator">
              <div className="qa-actions">
                <div className={'qa-arrows qa-arrows-enabled'}>
                  <Button
                    size={BUTTON_SIZE.ICON_STANDARD}
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={() => scrollToSegment(-1)}
                  >
                    <ChevronLeft />
                  </Button>
                  <div className="info-navigation-issues">
                    <b>{navigationIndex + 1} </b> / {navigationList.length}{' '}
                    {/*Segments*/}
                  </div>
                  <Button
                    onClick={() => scrollToSegment(1)}
                    mode={BUTTON_MODE.OUTLINE}
                    size={BUTTON_SIZE.ICON_STANDARD}
                  >
                    <ChevronRight />
                  </Button>
                </div>
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  ) : null
}

export default QAComponent
