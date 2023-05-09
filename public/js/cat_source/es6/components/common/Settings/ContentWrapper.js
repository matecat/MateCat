import React from 'react'
import {useContext} from 'react'
import {SettingsContext} from './SettingsContext'
import {Tab} from './Tab'

export const ContentWrapper = () => {
  const {tabs} = useContext(SettingsContext)

  const activeContent = tabs.find(({isOpened}) => isOpened)?.component

  return (
    <div className="settings-panel-contentwrapper">
      <ul>
        {tabs.map((tab, index) => (
          <Tab key={index} {...{...tab}} />
        ))}
      </ul>
      <div className="settings-panel-contentwrapper-container">
        {activeContent}
      </div>
    </div>
  )
}
