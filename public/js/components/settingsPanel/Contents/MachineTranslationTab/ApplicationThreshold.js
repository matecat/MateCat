import React, {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {NumericStepper} from '../../../common/NumericStepper/NumericStepper'

export const ApplicationThreshold = () => {
  const {modifyingCurrentTemplate, currentProjectTemplate} =
    useContext(SettingsPanelContext)

  const mtQualityValue = currentProjectTemplate.mtQualityValueInEditor
  const setMtQualityValue = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      mtQualityValueInEditor: value,
    }))

  return (
    typeof mtQualityValue === 'number' && (
      <div className="mt-quality-value">
        <div className="mt-quality-value-label">
          <h4>Application threshold</h4>
          <p>
            Defines the value and position of MT compared to TM matches. At 85%,
            MT pre-populates segments unless TM matches
            <br />
            exceed this value.{' '}
            <a
              href="https://guides.matecat.com/mt-settings#MT-application-threshold"
              target="_blank"
              rel="noreferrer"
            >
              Learn more
            </a>
          </p>
        </div>
        <NumericStepper
          value={mtQualityValue}
          valuePlaceholder={`${mtQualityValue}%`}
          onChange={setMtQualityValue}
          minimumValue={76}
          maximumValue={101}
          stepValue={1}
          disabled={config.is_cattool}
        />
      </div>
    )
  )
}
