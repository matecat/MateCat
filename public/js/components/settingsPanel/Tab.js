import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from './SettingsPanelContext'
import {TEMPLATE_PROPS_BY_TAB} from './SettingsPanel'

export const Tab = ({id, label}) => {
  const {
    tabs,
    setTabs,
    checkSpecificTemplatePropsAreModified,
    isEnabledProjectTemplateComponent,
    subtemplatesNotSaved,
  } = useContext(SettingsPanelContext)
  const clickHandler = () =>
    setTabs((prevState) =>
      prevState.map((tab) => ({...tab, isOpened: tab.id === id})),
    )

  const isActive = tabs.find((tab) => tab.id === id)?.isOpened ?? false
  const isModifyng =
    isEnabledProjectTemplateComponent &&
    (checkSpecificTemplatePropsAreModified(TEMPLATE_PROPS_BY_TAB[id] ?? []) ||
      subtemplatesNotSaved.some((value) =>
        TEMPLATE_PROPS_BY_TAB[id].includes(value),
      ))

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
