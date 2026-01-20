import React, {useContext} from 'react'
import Switch from '../../../common/Switch'
import {SettingsPanelContext} from '../../SettingsPanelContext'

export const IcuSyntax = () => {
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const isActive = currentProjectTemplate.icuEnabled
  const setIsActive = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      icuEnabled: value,
    }))
  const onChange = (isActive) => {
    setIsActive(isActive)
  }

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>ICU detection</h3>
        <p>
          Enable or disable ICU syntax detection and ICU-specific QA checks.
        </p>
      </div>
      <div className="options-box-value">
        <Switch active={isActive} onChange={onChange} />
      </div>
    </div>
  )
}
