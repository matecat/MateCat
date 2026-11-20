import React, {useContext, useLayoutEffect, useRef, useState} from 'react'
import {SettingsPanelContext} from './SettingsPanelContext'
import {Tab} from './Tab'

export const ContentWrapper = () => {
  const {tabs, isEnabledProjectTemplateComponent} =
    useContext(SettingsPanelContext)

  const [maxHeightContainer, setMaxHeightContainer] = useState()

  const refContainer = useRef()

  useLayoutEffect(() => {
    if (!refContainer?.current) return

    const {y} = refContainer.current.getBoundingClientRect()
    setMaxHeightContainer(`calc(100vh - ${y}px)`)
  }, [])

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
        <span dangerouslySetInnerHTML={{__html: activeTab.description}} />
      </div>
      <div
        ref={refContainer}
        className={`settings-panel-contentwrapper-container${!isEnabledProjectTemplateComponent ? ' settings-panel-contentwrapper-container-without-project-teamplate-control' : ''}`}
        style={{maxHeight: maxHeightContainer}}
      >
        {activeContent}
      </div>
    </div>
  )
}
