import React from 'react'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {Select} from '../../../../common/Select'

const PROVIDERS = Object.values(config.intento_providers)

export const IntentoOptions = () => {
  const {control} = useOptions()

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
          <h3>Provider/routing to use</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="intento_routing"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              placeholder="Select provider"
              options={PROVIDERS}
              activeOption={PROVIDERS.find(({id}) => id === value)}
              onSelect={(option) => onChange(option.id)}
              isPortalDropdown={true}
              maxHeightDroplist={260}
            />
          )}
        />
      </div>
    </div>
  )
}
