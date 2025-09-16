import React, {useCallback, useContext} from 'react'
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

export const DeepLOptions = () => {
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
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="pre_translate_files"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>
      <div className="mt-params-option">
        <div>
          <h3>Formality</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="deepl_formality"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              placeholder="Select formality"
              options={FORMALITIES}
              activeOption={FORMALITIES.find(({id}) => id === value)}
              onSelect={(option) => onChange(option.id)}
              isPortalDropdown={true}
              maxHeightDroplist={260}
            />
          )}
        />
      </div>
      <div className="mt-params-option">
        <div>
          <h3>Type of engine to use</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="deepl_engine_type"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              placeholder="Select type engine to use"
              options={TYPE_ENGINES}
              activeOption={TYPE_ENGINES.find(({id}) => id === value)}
              onSelect={(option) => onChange(option.id)}
              isPortalDropdown={true}
              maxHeightDroplist={260}
            />
          )}
        />
      </div>
      <DeepLGlossary
        id={currentProjectTemplate.mt.id}
        setGlossaries={setGlossaries}
        isCattoolPage={config.is_cattool}
      />
    </div>
  )
}
