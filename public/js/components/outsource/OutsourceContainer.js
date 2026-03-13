import React, {useCallback, useEffect, useRef} from 'react'
import Cookies from 'js-cookie'
import $ from 'jquery'
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

const OutsourceContainer = ({
  openOutsource,
  showTranslatorBox,
  idJobLabel,
  job,
  standardWC,
  url,
  project,
  extendedView,
  onClickOutside,
}) => {
  const containerRef = useRef(null)

  checkTimezone()

  const handleDocumentClick = useCallback(
    (evt) => {
      evt.stopPropagation()
      const parentClass = '.outsource-container'
      if (
        containerRef.current &&
        !containerRef.current.contains(evt.target) &&
        !$(evt.target).hasClass('open-view-more') &&
        !$(evt.target).hasClass('outsource-goBack') &&
        !$(evt.target).hasClass('faster') &&
        !$(evt.target).hasClass('need-it-faster-close') &&
        !$(evt.target).hasClass('need-it-faster-close-icon') &&
        !$(evt.target).hasClass('get-price') &&
        !$(evt.target).hasClass('react-datepicker__day') &&
        !evt.target.closest('.dropdown__list') &&
        !evt.target.closest(parentClass)
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
        containerRef.current &&
          containerRef.current.scrollIntoView({block: 'center'})
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
    !config.enable_outsource || (showTranslatorBox && !openOutsource)
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
          style={{width: '100%'}}
        >
          <div
            className={'outsource-container ' + outsourceContainerClass}
            ref={containerRef}
          >
            {showTranslatorBox ? (
              <AssignToTranslator
                job={job}
                url={url}
                project={project}
                closeOutsource={onClickOutside}
              />
            ) : null}
            {config.enable_outsource && openOutsource ? (
              <OutsourceVendor
                project={project}
                job={job}
                extendedView={extendedView}
                standardWC={standardWC}
              />
            ) : null}
          </div>
        </CSSTransition>
      ) : null}
    </TransitionGroup>
  )
}

OutsourceContainer.defaultProps = {
  showTranslatorBox: true,
  extendedView: true,
}

export default OutsourceContainer
