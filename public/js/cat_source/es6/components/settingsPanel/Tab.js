import React from 'react'
import PropTypes from 'prop-types'
import {useContext} from 'react'
import {SettingsPanelContext} from './SettingsPanelContext'

export const Tab = ({id, label}) => {
  const {tabs, setTabs} = useContext(SettingsPanelContext)

  const clickHandler = () =>
    setTabs((prevState) =>
      prevState.map((tab) => ({...tab, isOpened: tab.id === id})),
    )

  const isActive = tabs.find((tab) => tab.id === id)?.isOpened ?? false

  return (
    <li
      className={`settings-panel-tab${
        isActive ? ' settings-panel-tab-active' : ''
      }`}
      onClick={clickHandler}
    >
      {label}
    </li>
  )
}

Tab.propTypes = {
  id: PropTypes.number.isRequired,
  label: PropTypes.string.isRequired,
}
