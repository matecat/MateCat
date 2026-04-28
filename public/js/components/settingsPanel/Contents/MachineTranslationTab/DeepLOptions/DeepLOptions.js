import React, {useCallback, useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {Select} from '../../../../common/Select'
import {DeepLGlossary} from '../DeepLGlossary/DeepLGlossary'

const FORMALITIES = [
  {name: 'Default', id: 'default'},
  {name: 'Informal', id: 'prefer_less'},
  {name: 'Formal', id: 'prefer_more'},
]

const TYPE_ENGINES = [
  {name: 'Next generation', id: 'prefer_quality_optimized'},
  {name: 'Old generation', id: 'latency_optimized'},
]

export const DeepLOptions = ({isCattoolPage}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const {control, setValue} = useOptions()

  const setGlossaries = useCallback(
    (value) => setValue('deepl_id_glossary', value),
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
          <h3>Formality</h3>
          <p>Select the level of formality for the MT output.</p>
        </div>
        <Controller
          control={control}
          name="deepl_formality"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Select
              name={name}
              placeholder="Select formality"
              options={FORMALITIES}
              activeOption={FORMALITIES.find(({id}) => id === value)}
              onSelect={(option) => onChange(option.id)}
              isPortalDropdown={true}
              maxHeightDroplist={260}
              isDisabled={disabled}
            />
          )}
        />
      </div>
      <div className="mt-params-option">
        <div>
          <h3>Language model</h3>
          <p>
            Select the DeepL language model to use for translation. The
            next‑generation model provides higher translation quality,
            especially for longer texts, while the classic model offers reliable
            results and faster processing.
          </p>
        </div>
        <Controller
          control={control}
          name="deepl_engine_type"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Select
              name={name}
              placeholder="Select type engine to use"
              options={TYPE_ENGINES}
              activeOption={TYPE_ENGINES.find(({id}) => id === value)}
              onSelect={(option) => onChange(option.id)}
              isPortalDropdown={true}
              maxHeightDroplist={260}
              isDisabled={disabled}
            />
          )}
        />
      </div>
      <DeepLGlossary
        id={currentProjectTemplate.mt.id}
        {...{setGlossaries, isCattoolPage}}
      />
    </div>
  )
}

DeepLOptions.propTypes = {
  isCattoolPage: PropTypes.bool,
}
