import React, {useCallback, useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {LaraGlossary} from '../LaraGlossary/LaraGlossary'
import {Select} from '../../../../common/Select'

export const LARA_STYLES = {
  FAITHFUL: 'faithful',
  FLUID: 'fluid',
  CREATIVE: 'creative',
}

export const LARA_STYLES_OPTIONS = [
  {
    id: LARA_STYLES.FAITHFUL,
    name: 'Faithful',
    description: (
      <>
        Precise translation, maintaining the text’s original structure and
        meaning accurately. For manuals, legal, etc.
      </>
    ),
  },
  {
    id: LARA_STYLES.FLUID,
    name: 'Fluid',
    description: (
      <>
        Smooth translation, emphasizing readability and natural language flow.
        For general content.
      </>
    ),
  },
  {
    id: LARA_STYLES.CREATIVE,
    name: 'Creative',
    description: (
      <>
        Imaginative translation, capturing the text’s essence with vivid and
        engaging language. For marketing, literature, etc.
      </>
    ),
  },
]

export const LaraOptions = ({isCattoolPage}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)
  console.log('currentProjectTemplate', currentProjectTemplate)
  const {control, setValue} = useOptions()

  const setGlossaries = useCallback(
    (value) => setValue('lara_glossaries', value),
    [setValue],
  )

  return (
    <div className="options-container-content">
      <div className="mt-params-option">
        <div>
          <h3>Pre-translate files</h3>
          <p>
            Choose whether to automatically translate project files during the
            analysis phase. Pre-translation may generate additional charges from
            your MT provider.
          </p>
        </div>
        <Controller
          control={control}
          name="enable_mt_analysis"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Switch
              name={name}
              active={value}
              onChange={onChange}
              disabled={disabled}
            />
          )}
        />
      </div>
      <div className="mt-params-option">
        <div>
          <h3>Style</h3>
          <p>Content...</p>
        </div>
        <Controller
          control={control}
          name="lara_style"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Select
              name={name}
              isPortalDropdown={true}
              dropdownClassName="select-dropdown__wrapper-portal option-dropdown-with-descrition"
              options={LARA_STYLES_OPTIONS.map((option) => ({
                ...option,
                name: (
                  <div className="option-dropdown-with-descrition-select-content">
                    {option.name}
                    <p>{option.description}</p>
                  </div>
                ),
              }))}
              activeOption={LARA_STYLES_OPTIONS.find(
                ({id}) => id === (value ?? LARA_STYLES.FAITHFUL),
              )}
              checkSpaceToReverse={true}
              onSelect={(option) => onChange(option.id)}
              isDisabled={disabled}
              maxHeightDroplist={300}
            />
          )}
        />
      </div>
      <h2>Glossaries</h2>
      <LaraGlossary
        id={currentProjectTemplate.mt.id}
        {...{setGlossaries, isCattoolPage}}
      />
    </div>
  )
}

LaraOptions.propTypes = {
  isCattoolPage: PropTypes.bool,
}
