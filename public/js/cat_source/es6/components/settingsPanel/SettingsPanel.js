import React, {useState, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from './SettingsPanelContext'
import {ContentWrapper} from './ContentWrapper'
import {MachineTranslationTab} from './Contents/MachineTranslationTab'
import {AdvancedOptionsTab} from './Contents/AdvancedOptionsTab'
import {TranslationMemoryGlossaryTab} from './Contents/TranslationMemoryGlossaryTab'

const DEFAULT_CONTENTS = [
  {
    label: 'Translation Memory and Glossary',
    component: <TranslationMemoryGlossaryTab />,
    isOpened: true,
  },
  {
    label: 'Machine Translation',
    component: <MachineTranslationTab />,
  },
  {
    label: 'Advanced Options',
    component: <AdvancedOptionsTab />,
  },
]

export const DEFAULT_ENGINE_MEMORY = {
  id: '1',
  name: 'MyMemory',
  description:
    'Machine translation by the MT engine best suited to your project.',
  default: true,
}
export const MMT_NAME = 'ModernMT'

export const SettingsPanel = ({
  onClose,
  tmKeys,
  setTmKeys,
  mtEngines,
  setMtEngines,
  activeMTEngine,
  setActiveMTEngine,
}) => {
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

  const openLoginModal = () => {
    setIsVisible(false)
    APP.openLoginModal()
  }

  return (
    <SettingsPanelContext.Provider
      value={{
        tabs,
        setTabs,
        tmKeys,
        setTmKeys,
        mtEngines,
        setMtEngines,
        activeMTEngine,
        setActiveMTEngine,
        openLoginModal,
        wrapperRef,
      }}
    >
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
    </SettingsPanelContext.Provider>
  )
}

SettingsPanel.propTypes = {
  onClose: PropTypes.func.isRequired,
  tmKeys: PropTypes.array,
  setTmKeys: PropTypes.func,
  mtEngines: PropTypes.array,
  setMtEngines: PropTypes.func,
  activeMTEngine: PropTypes.object,
  setActiveMTEngine: PropTypes.func,
}
