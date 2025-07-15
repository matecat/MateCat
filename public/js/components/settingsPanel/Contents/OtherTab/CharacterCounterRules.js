import React, {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import Switch from '../../../common/Switch'
import {Select} from '../../../common/Select'
import {CHARS_SIZE_COUNTER_TYPES} from '../../../../utils/charsSizeCounterUtil'

const OPTIONS = [
  {
    id: CHARS_SIZE_COUNTER_TYPES.GOOGLE_ADS,
    name: 'Ads',
    description: (
      <>
        Ideal for translating ads.
        <br />
        Counts characters like the most popular ads platforms: UTF-16 byte size
        for Armenian, Chinese, Georgian, Japanese, Korean, Sinhala, and emojis;
        all other characters count as one.
      </>
    ),
  },
  {
    id: CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK,
    name: 'Screen-fit',
    description: (
      <>
        Ideal for content where on-screen text size matters.
        <br />
        Counts characters using UTF-16 byte size for Chinese, Japanese and
        Korean; all other characters count as one
      </>
    ),
  },
  {
    id: CHARS_SIZE_COUNTER_TYPES.ALL_ONE,
    name: 'Content length',
    description: (
      <>
        Ideal for content where total text length matters.
        <br />
        Counts all characters as one.
      </>
    ),
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

  const counterRule =
    currentProjectTemplate.characterCounterMode ??
    CHARS_SIZE_COUNTER_TYPES.GOOGLE_ADS
  const setCounterRule = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      characterCounterMode: value,
    }))

  const isComponentsDisabled = config.is_cattool && config.ownerIsMe !== 1

  return (
    <div className="characters-counter-box">
      <div className="options-box">
        <div className="option-description">
          <h3>Character counter rule</h3>
          <p>Select how characters should be counted in your project.</p>
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
            isDisabled={isComponentsDisabled}
          />
        </div>
      </div>

      <div className="options-box">
        <div className="option-description">
          <h3>Count characters in tags</h3>
          <p>Choose whether to count characters within tags.</p>
        </div>
        <div className="options-box-value">
          <Switch
            active={isActive}
            onChange={onChange}
            testId="switch-chars-counter"
            disabled={isComponentsDisabled}
          />
        </div>
      </div>
    </div>
  )
}
