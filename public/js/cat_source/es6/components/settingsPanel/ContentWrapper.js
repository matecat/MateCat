import React, {useContext} from 'react'
import {SettingsPanelContext} from './SettingsPanelContext'
import {Tab} from './Tab'

export const ContentWrapper = () => {
  const {tabs} = useContext(SettingsPanelContext)

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
