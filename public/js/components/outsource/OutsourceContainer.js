import React, {useCallback, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import Cookies from 'js-cookie'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import AssignToTranslator from './AssignToTranslator'

const checkTimezone = () => {
  let timezoneToShow = Cookies.get('matecat_timezone')
  if (!timezoneToShow) {
    timezoneToShow = -1 * (new Date().getTimezoneOffset() / 60)
    Cookies.set('matecat_timezone', timezoneToShow, {secure: true})
  }
}

const IGNORED_CLICK_CLASSES = [
  'open-view-more',
  'outsource-goBack',
  'faster',
  'need-it-faster-close',
  'need-it-faster-close-icon',
  'get-price',
  'react-datepicker__day',
]

/**
 * The "assign to a translator" box under a Manage job. Buying a translation
 * opens the outsource drawer instead (JobContainer).
 */
const OutsourceContainer = ({
  showTranslatorBox = true,
  idJobLabel,
  job,
  project,
  onClickOutside,
}) => {
  const containerRef = useRef(null)

  useEffect(() => {
    checkTimezone()
  }, [])

  const handleDocumentClick = useCallback(
    (evt) => {
      evt.stopPropagation()
      if (
        containerRef.current &&
        !containerRef.current.contains(evt.target) &&
        !IGNORED_CLICK_CLASSES.some((cls) =>
          evt.target.classList.contains(cls),
        ) &&
        !evt.target.closest('.dropdown__list') &&
        !evt.target.closest('.outsource-container')
      ) {
        onClickOutside(evt)
      }
    },
    [onClickOutside],
  )

  const handleEscKey = useCallback(
    (event) => {
      if (event.keyCode === 27) {
        event.preventDefault()
        event.stopPropagation()
        onClickOutside()
      }
    },
    [onClickOutside],
  )

  useEffect(() => {
    if (showTranslatorBox) {
      const timer = setTimeout(() => {
        window.addEventListener('mousedown', handleDocumentClick)
        window.addEventListener('keydown', handleEscKey)
        containerRef.current?.scrollIntoView({block: 'center'})
      }, 500)
      return () => {
        clearTimeout(timer)
        window.removeEventListener('mousedown', handleDocumentClick)
        window.removeEventListener('keydown', handleEscKey)
      }
    } else {
      window.removeEventListener('mousedown', handleDocumentClick)
      window.removeEventListener('keydown', handleEscKey)
    }
  }, [showTranslatorBox, handleDocumentClick, handleEscKey])

  return (
    <TransitionGroup style={{width: '100%'}}>
      {showTranslatorBox ? (
        <CSSTransition
          key={idJobLabel}
          classNames="transitionOutsource"
          timeout={{enter: 500, exit: 300}}
        >
          <div
            className="outsource-container showTranslator"
            ref={containerRef}
          >
            <AssignToTranslator
              job={job}
              project={project}
              closeOutsource={onClickOutside}
            />
          </div>
        </CSSTransition>
      ) : null}
    </TransitionGroup>
  )
}

OutsourceContainer.propTypes = {
  showTranslatorBox: PropTypes.bool,
  idJobLabel: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  job: PropTypes.object,
  project: PropTypes.object,
  onClickOutside: PropTypes.func,
}

export default OutsourceContainer
