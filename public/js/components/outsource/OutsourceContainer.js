import React, {useCallback, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import Cookies from 'js-cookie'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import AssignToTranslator from './AssignToTranslator'
import OutsourceVendor from './OutsourceVendor'

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

const OutsourceContainer = ({
  openOutsource,
  showTranslatorBox = true,
  idJobLabel,
  job,
  standardWC,
  project,
  extendedView = true,
  onClickOutside,
  outsourceEnabled = config.enable_outsource,
  serviceLoginUrl,
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
    if (openOutsource || showTranslatorBox) {
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
  }, [openOutsource, showTranslatorBox, handleDocumentClick, handleEscKey])

  const outsourceContainerClass =
    !outsourceEnabled || (showTranslatorBox && !openOutsource)
      ? 'no-outsource'
      : showTranslatorBox && openOutsource
        ? 'showTranslator'
        : openOutsource
          ? 'showOutsource'
          : ''

  return (
    <TransitionGroup style={{width: '100%'}}>
      {openOutsource || showTranslatorBox ? (
        <CSSTransition
          key={idJobLabel}
          classNames="transitionOutsource"
          timeout={{enter: 500, exit: 300}}
        >
          <div
            className={'outsource-container ' + outsourceContainerClass}
            ref={containerRef}
          >
            {showTranslatorBox ? (
              <AssignToTranslator
                job={job}
                project={project}
                closeOutsource={onClickOutside}
              />
            ) : null}
            {outsourceEnabled && openOutsource ? (
              <OutsourceVendor
                project={project}
                job={job}
                extendedView={extendedView}
                standardWC={standardWC}
                outsourceEnabled={outsourceEnabled}
                serviceLoginUrl={serviceLoginUrl}
              />
            ) : null}
          </div>
        </CSSTransition>
      ) : null}
    </TransitionGroup>
  )
}

OutsourceContainer.propTypes = {
  openOutsource: PropTypes.any,
  showTranslatorBox: PropTypes.bool,
  idJobLabel: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  job: PropTypes.object,
  standardWC: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  project: PropTypes.object,
  extendedView: PropTypes.bool,
  onClickOutside: PropTypes.func,
  outsourceEnabled: PropTypes.oneOfType([
    PropTypes.bool,
    PropTypes.number,
    PropTypes.string,
  ]),
  serviceLoginUrl: PropTypes.string,
}

export default OutsourceContainer
