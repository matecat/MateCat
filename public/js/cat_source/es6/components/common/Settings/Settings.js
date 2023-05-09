import React, {useState, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {SettingsContext} from './SettingsContext'
import {ContentWrapper} from './ContentWrapper'

const DEFAULT_CONTENTS = [
  {
    label: 'Translation Memory and Glossary',
    component: <h2>ContentWrapper: Translation Memory and Glossary</h2>,
    isOpened: true,
  },
  {
    label: 'Machine Translation',
    component: <h2>ContentWrapper: Machine Translation</h2>,
  },
  {
    label: 'Advanced Options',
    component: <h2>ContentWrapper: Advanced Options</h2>,
  },
]

export const Settings = ({onClose}) => {
  const [isVisible, setIsVisible] = useState(false)
  const [tabs, setTabs] = useState(
    DEFAULT_CONTENTS.map((tab, index) => ({...tab, id: index})),
  )

  const wrapperRef = useRef()

  useEffect(() => {
    setIsVisible(true)
  }, [])

  useEffect(() => {
    const onTransitionEnd = () => !isVisible && onClose()

    const {current} = wrapperRef
    current.addEventListener('transitionend', onTransitionEnd)

    return () => current.removeEventListener('transitionend', onTransitionEnd)
  }, [isVisible, onClose])

  const close = () => setIsVisible(false)

  return (
    <SettingsContext.Provider value={{tabs, setTabs}}>
      <div className="settings-panel">
        <div
          className={`settings-panel-overlay${
            isVisible
              ? ' settings-panel-overlay-visible'
              : ' settings-panel-overlay-hide'
          }`}
          onClick={close}
        ></div>
        <div
          ref={wrapperRef}
          className={`settings-panel-wrapper${
            isVisible
              ? ' settings-panel-wrapper-visible'
              : ' settings-panel-wrapper-hide'
          }`}
        >
          <div className="settings-panel-header">
            <div className="settings-panel-header-logo" />
            <span>Settings</span>
            <div onClick={close} className="close-matecat-modal x-popup" />
          </div>
          <ContentWrapper />
        </div>
      </div>
    </SettingsContext.Provider>
  )
}

Settings.propTypes = {
  onClose: PropTypes.func.isRequired,
}
