import React, {useState, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from './SettingsPanelContext'
import {ContentWrapper} from './ContentWrapper'
import {MachineTranslationTab} from './Contents/MachineTranslationTab'
import {AdvancedOptionsTab} from './Contents/AdvancedOptionsTab'
import {TranslationMemoryGlossaryTab} from './Contents/TranslationMemoryGlossaryTab'

let tabOpenFromQueryString = new URLSearchParams(window.location.search).get(
  'openTab',
)

export const SETTINGS_PANEL_TABS = {
  translationMemoryGlossary: 'tm',
  machineTranslation: 'mt',
  advancedOptions: 'options',
}

const DEFAULT_CONTENTS = [
  {
    id: SETTINGS_PANEL_TABS.translationMemoryGlossary,
    label: 'Translation Memory and Glossary',
    component: <TranslationMemoryGlossaryTab />,
  },
  {
    id: SETTINGS_PANEL_TABS.machineTranslation,
    label: 'Machine Translation',
    component: <MachineTranslationTab />,
  },
  {
    id: SETTINGS_PANEL_TABS.advancedOptions,
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
  isOpened,
  tabOpen = SETTINGS_PANEL_TABS.translationMemoryGlossary,
  tmKeys,
  setTmKeys,
  mtEngines,
  setMtEngines,
  activeMTEngine,
  setActiveMTEngine,
  speechToTextActive,
  setSpeechToTextActive,
  guessTagActive,
  setGuessTagActive,
  sourceLang,
  targetLangs,
  lexiqaActive,
  setLexiqaActive,
  multiMatchLangs,
  setMultiMatchLangs,
  segmentationRule,
  setSegmentationRule,
  getPublicMatches,
  setGetPublicMatches,
  setKeysOrdered,
}) => {
  const [isVisible, setIsVisible] = useState(false)
  const [tabs, setTabs] = useState(() => {
    const initialState = DEFAULT_CONTENTS.map((tab) => ({
      ...tab,
      isOpened: Object.values(SETTINGS_PANEL_TABS).some(
        (value) => value === tabOpenFromQueryString,
      )
        ? tabOpenFromQueryString === tab.id
        : tabOpen === tab.id,
    }))

    tabOpenFromQueryString = false
    return initialState
  })

  const wrapperRef = useRef()

  useEffect(() => {
    setIsVisible(typeof isOpened !== 'undefined' ? isOpened : true)

    const keyboardHandler = (e) => e.key === 'Escape' && setIsVisible(false)
    document.addEventListener('keydown', keyboardHandler)

    return () => document.removeEventListener('keydown', keyboardHandler)
  }, [isOpened])

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
        speechToTextActive,
        setSpeechToTextActive,
        guessTagActive,
        setGuessTagActive,
        sourceLang,
        targetLangs,
        lexiqaActive,
        setLexiqaActive,
        multiMatchLangs,
        setMultiMatchLangs,
        segmentationRule,
        setSegmentationRule,
        getPublicMatches,
        setGetPublicMatches,
        setKeysOrdered,
      }}
    >
      <div
        className={`settings-panel${
          isOpened || typeof isOpened === 'undefined' ? ' visible' : ''
        }`}
      >
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
  isOpened: PropTypes.bool,
  tabOpen: PropTypes.oneOf(Object.values(SETTINGS_PANEL_TABS)),
  tmKeys: PropTypes.array,
  setTmKeys: PropTypes.func,
  mtEngines: PropTypes.array,
  setMtEngines: PropTypes.func,
  activeMTEngine: PropTypes.object,
  setActiveMTEngine: PropTypes.func,
  guessTagActive: PropTypes.bool,
  setGuessTagActive: PropTypes.func,
  sourceLang: PropTypes.object,
  targetLangs: PropTypes.array,
  getPublicMatches: PropTypes.bool,
  setGetPublicMatches: PropTypes.func,
  setKeysOrdered: PropTypes.func,
}
