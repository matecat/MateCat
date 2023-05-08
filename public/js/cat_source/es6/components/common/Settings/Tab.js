import React from 'react'
import PropTypes from 'prop-types'
import {useContext} from 'react'
import {SettingsContext} from './SettingsContext'

export const Tab = ({id, label}) => {
  const {setTabs} = useContext(SettingsContext)

  const clickHandler = () =>
    setTabs((prevState) =>
      prevState.map((tab) => ({...tab, isOpened: tab.id === id})),
    )

  return (
    <li className="settings-panel-tab" onClick={clickHandler}>
      {label}
    </li>
  )
}

Tab.propTypes = {
  id: PropTypes.number.isRequired,
  label: PropTypes.string.isRequired,
}
