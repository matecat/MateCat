import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from './SettingsPanelContext'
import {TEMPLATE_PROPS_BY_TAB} from './SettingsPanelConstants'

export const useAreTemplatePropsModified = (props = []) => {
  const {
    checkSpecificTemplatePropsAreModified,
    isEnabledProjectTemplateComponent,
    subtemplatesNotSaved,
  } = useContext(SettingsPanelContext)

  return (
    isEnabledProjectTemplateComponent &&
    (checkSpecificTemplatePropsAreModified(props) ||
      subtemplatesNotSaved.some((value) => props.includes(value)))
  )
}

export const Tab = ({id, label}) => {
  const {tabs, setTabs} = useContext(SettingsPanelContext)
  const clickHandler = () =>
    setTabs((prevState) =>
      prevState.map((tab) => ({...tab, isOpened: tab.id === id})),
    )

  const isActive = tabs.find((tab) => tab.id === id)?.isOpened ?? false
  const isModifyng = useAreTemplatePropsModified(TEMPLATE_PROPS_BY_TAB[id])

  return (
    <li
      className={`settings-panel-tab${
        isActive ? ' settings-panel-tab-active' : ''
      }`}
      onClick={clickHandler}
    >
      {isModifyng && (
        <span className="settings-panel-tab-modifyng-icon">●</span>
      )}
      {label}
    </li>
  )
}

Tab.propTypes = {
  id: PropTypes.string.isRequired,
  label: PropTypes.string.isRequired,
}
