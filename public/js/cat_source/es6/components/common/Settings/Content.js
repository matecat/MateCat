import React from 'react'
import {useContext} from 'react'
import {SettingsContext} from './SettingsContext'
import {Tab} from './Tab'

export const Content = () => {
  const {tabs} = useContext(SettingsContext)

  const activeContent = tabs.find(({isOpened}) => isOpened)?.component

  return (
    <div className="settings-panel-content">
      <ul>
        {tabs.map((tab, index) => (
          <Tab key={index} {...{...tab}} />
        ))}
      </ul>
      <div className="settings-panel-content-container">{activeContent}</div>
    </div>
  )
}
