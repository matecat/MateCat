import React, {useCallback, useContext} from 'react'
import Switch from '../../../common/Switch'
import {SettingsPanelContext} from '../../SettingsPanelContext'

export const TmPrioritization = () => {
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const isActive = currentProjectTemplate.tmPrioritization
  const setIsActive = useCallback(
    (value) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        tmPrioritization: value,
      })),
    [modifyingCurrentTemplate],
  )

  const onChange = (isActive) => {
    setIsActive(isActive)
  }

  return (
    <div className="tm-prioritization-container">
      <div className="tm-prioritization-text-content">
        <h4>TM prioritization</h4>
        <span>
          Activate to prioritize translation memories based on their order.{' '}
          <a href="https://guides.matecat.com/activ" target="_blank">
            More details
          </a>
        </span>
      </div>
      <Switch
        onChange={onChange}
        active={isActive}
        disabled={config.ownerIsMe === 0}
      />
    </div>
  )
}
