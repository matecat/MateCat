import React, {useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsContext} from './SettingsContext'
import {Content} from './Content'

const DEFAULT_CONTENTS = [
  {
    id: 0,
    label: 'Translation Memory and Glossary',
    component: <div>Content: Translation Memory and Glossary</div>,
    isOpened: true,
  },
  {
    id: 1,
    label: 'Machine Translation',
    component: <div>Content: Machine Translation</div>,
  },
  {
    id: 2,
    label: 'Advanced Options',
    component: <div>Content: Advanced Options</div>,
  },
]

export const Settings = ({onClose}) => {
  const [tabs, setTabs] = useState(DEFAULT_CONTENTS)

  const close = () => onClose()

  return (
    <SettingsContext.Provider value={{tabs, setTabs}}>
      <div className="settings-panel">
        <div className="settings-panel-overlay"></div>
        <div className="settings-panel-wrapper">
          <div className="settings-panel-header">
            <img src="../../img/logo_matecat_small_white.svg" />
            <span>Settings</span>
            <div onClick={close} className="close-button">
              x
            </div>
          </div>
          <Content />
        </div>
      </div>
    </SettingsContext.Provider>
  )
}

Settings.propTypes = {
  onClose: PropTypes.func.isRequired,
}
