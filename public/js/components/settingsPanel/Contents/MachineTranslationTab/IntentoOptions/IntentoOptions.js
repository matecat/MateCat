import React from 'react'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {Select} from '../../../../common/Select'

const PROVIDERS = config.intento_providers
  ? Object.values(config.intento_providers)
  : []

const ROUTING = [
  {id: '1', name: 'routing 1'},
  {id: '2', name: 'routing 2'},
  {id: '3', name: 'routing 3'},
  {id: '4', name: 'routing 4'},
  {id: '5', name: 'routing 5'},
]

const ALL_OPTIONS = [...ROUTING, ...PROVIDERS]

export const IntentoOptions = () => {
  const {control, watch} = useOptions()

  const activeOption = watch('intento_routing')

  const getOptionsChildren = ({id}) => {
    const isFirstRouting =
      ROUTING.filter((item) => item.id !== activeOption).findIndex(
        (item) => item.id === id,
      ) === 0
    const isFirstProviders =
      PROVIDERS.filter((item) => item.id !== activeOption).findIndex(
        (item) => item.id === id,
      ) === 0

    return {
      ...(isFirstRouting && {
        beforeRow: <h4>Routing</h4>,
      }),
      ...(isFirstProviders && {
        beforeRow: <h4>Providers</h4>,
      }),
    }
  }
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
              options={ALL_OPTIONS}
              activeOption={ALL_OPTIONS.find(({id}) => id === value)}
              onSelect={(option) => onChange(option.id)}
              isPortalDropdown={true}
              dropdownClassName="select-intento-routing-providers"
              maxHeightDroplist={260}
            >
              {getOptionsChildren}
            </Select>
          )}
        />
      </div>
    </div>
  )
}
