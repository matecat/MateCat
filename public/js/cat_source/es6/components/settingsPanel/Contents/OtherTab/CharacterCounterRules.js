import React, {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import Switch from '../../../common/Switch'
import {Select} from '../../../common/Select'
import {CHARS_SIZE_COUNTER_TYPES} from '../../../../utils/charsSizeCounterUtil'

const OPTIONS = [
  {
    id: CHARS_SIZE_COUNTER_TYPES.GOOGLE_ADS,
    name: 'Ads',
    description:
      'Lorem ipsum dolor sit amet consectetur. Arcu aliquet vel donec at fermentum nulla neque.',
  },
  {
    id: CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK,
    name: 'Airbnb',
    description:
      'Lorem ipsum dolor sit amet consectetur. Arcu aliquet vel donec at fermentum nulla neque.',
  },
  {
    id: CHARS_SIZE_COUNTER_TYPES.ALL_ONE,
    name: 'Always one',
    description:
      'Lorem ipsum dolor sit amet consectetur. Arcu aliquet vel donec at fermentum nulla neque.',
  },
]

export const CharacterCounterRules = () => {
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const isActive = currentProjectTemplate.characterCounterCountTags
  const setIsActive = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      characterCounterCountTags: value,
    }))
  const onChange = (isActive) => {
    setIsActive(isActive)
  }

  const counterRule = currentProjectTemplate.characterCounterMode
  const setCounterRule = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      characterCounterMode: value,
    }))

  return (
    <div className="characters-counter-box">
      <div className="options-box">
        <div className="option-description">
          <h3>Character counter rule</h3>
          <p>Select how the character counter should count tags:</p>
        </div>
        <div className="options-box-value">
          <Select
            id="chars-counter-rule"
            name="chars-counter-rule"
            isPortalDropdown={true}
            dropdownClassName="select-dropdown__wrapper-portal option-characters-counter-rule-dropdown"
            options={OPTIONS.map((option) => ({
              ...option,
              name: (
                <div className="option-characters-counter-rule-select-option-content">
                  {option.name}
                  <p>{option.description}</p>
                </div>
              ),
            }))}
            activeOption={OPTIONS.find(({id}) => id === counterRule)}
            checkSpaceToReverse={true}
            onSelect={(option) => setCounterRule(option.id)}
          />
        </div>
      </div>

      <div className="options-box">
        <div className="option-description">
          <h3>Count tags as characters</h3>
          <p>Lorem ipsum.</p>
        </div>
        <div className="options-box-value">
          <Switch
            active={isActive}
            onChange={onChange}
            testId="switch-chars-counter"
          />
        </div>
      </div>
    </div>
  )
}
