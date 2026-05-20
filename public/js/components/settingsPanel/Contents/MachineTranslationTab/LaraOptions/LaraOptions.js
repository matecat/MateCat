import React, {useCallback, useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {LaraGlossary} from '../LaraGlossary/LaraGlossary'
import {Select} from '../../../../common/Select'
import {laraStyleguides} from '../../../../../api/laraStyleguides/laraStyleguides'
import {laraAuth} from '../../../../../api/laraAuth'
import CreateProjectStore from '../../../../../stores/CreateProjectStore'
import CatToolStore from '../../../../../stores/CatToolStore'

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

  const [styleGuidesOptions, setStyleGuidesOptions] = useState([])

  const {watch, control, setValue} = useOptions(['lara_style_guide'])

  const laraStyleGuide = watch('lara_style_guide')

  const setGlossaries = useCallback(
    (value) => setValue('lara_glossaries', value),
    [setValue],
  )

  useEffect(() => {
    if (
      typeof currentProjectTemplate?.mt?.extra === 'object' &&
      !currentProjectTemplate?.mt?.extra.lara_style
    )
      setValue('lara_style', LARA_STYLES.FAITHFUL)
  }, [currentProjectTemplate?.mt?.extra, setValue])

  useEffect(() => {
    laraAuth().then((response) => {
      laraStyleguides(response)
        .then((data) => setStyleGuidesOptions(data))
        .catch((error) => console.log(error))
    })
  }, [])

  useEffect(() => {
    CreateProjectStore.updateProject({
      laraStyleGuide,
    })
  }, [laraStyleGuide])

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
          <p>
            Select the style for Lara’s suggestions.
            <br />
            This setting will be applied to all segments in the project by
            default. You can still view and select alternative styles for
            individual segments directly in the editor.
          </p>
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
      {config.isAnInternalUser && (
        <div className="mt-params-option">
          <div>
            <h3>Style guide</h3>
            <p>
              Select a style guide to be applied to Lara's translations
              (activates Lara Prose for the project).
            </p>
          </div>
          <Controller
            control={control}
            name="lara_style_guide"
            disabled={isCattoolPage}
            render={({field: {onChange, value, name, disabled}}) => (
              <Select
                name={name}
                placeholder="Select a style guide"
                isPortalDropdown={true}
                dropdownClassName="select-dropdown__wrapper-portal option-dropdown-with-descrition"
                options={styleGuidesOptions.map((option) => ({
                  ...option,
                  name: (
                    <div className="option-dropdown-with-descrition-select-content">
                      {option.name}
                      <p>{option.description}</p>
                    </div>
                  ),
                }))}
                activeOption={styleGuidesOptions.find(
                  ({id}) =>
                    id ===
                    (isCattoolPage
                      ? CatToolStore.getJobMetadata().project.mt_extra
                          .lara_style_guide_id
                      : value),
                )}
                checkSpaceToReverse={true}
                onSelect={(option) => onChange(option.id)}
                isDisabled={disabled}
                maxHeightDroplist={300}
              />
            )}
          />
        </div>
      )}
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
