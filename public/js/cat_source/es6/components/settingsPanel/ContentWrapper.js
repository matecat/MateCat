import React, {useContext} from 'react'
import {SettingsPanelContext} from './SettingsPanelContext'
import {Tab} from './Tab'

export const ContentWrapper = () => {
  const {tabs} = useContext(SettingsPanelContext)

  const activeTab = tabs.find(({isOpened}) => isOpened)
  const activeContent = activeTab?.component

  return (
    <div className="settings-panel-contentwrapper">
      <ul>
        {tabs.map((tab, index) => (
          <Tab key={index} {...{...tab}} />
        ))}
      </ul>
      <div className="settings-panel-contentwrapper-active-tab">
        <h3>{activeTab.label}</h3>
        <span>{activeTab.description}</span>
      </div>
      <div className="settings-panel-contentwrapper-container">
        {activeContent}
      </div>
    </div>
  )
}
