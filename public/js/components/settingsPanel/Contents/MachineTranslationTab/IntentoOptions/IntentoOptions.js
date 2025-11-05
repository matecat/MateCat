import React, {useContext, useEffect, useMemo, useState} from 'react'
import PropTypes from 'prop-types'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {Select} from '../../../../common/Select'
import {getIntentoRouting} from '../../../../../api/getIntentoRouting'
import {SettingsPanelContext} from '../../../SettingsPanelContext'

const PROVIDERS = config.intento_providers
  ? Object.values(config.intento_providers)
  : []

export const IntentoOptions = ({id, isCattoolPage}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const {control} = useOptions()

  const [routings, setRoutings] = useState([])

  const allOptions = useMemo(() => {
    return [...routings, ...PROVIDERS]
  }, [routings])

  useEffect(() => {
    let cleanup = false

    getIntentoRouting(id).then((data) => {
      if (!cleanup) {
        const items = Object.values(data)
        setRoutings(
          items
            .map((item) => ({...item, id: item.id.toString()}))
            .sort((a) => (a.id === 'smart_routing' ? -1 : 1)),
        )
      }
    })

    return () => (cleanup = true)
  }, [id])

  const routingOrProviderKey = currentProjectTemplate.mt?.extra
    ?.intento_provider
    ? 'intento_provider'
    : 'intento_routing'

  const getOptionsChildren = ({id}) => {
    const isFirstRouting = routings.findIndex((item) => item.id === id) === 0
    const isFirstProvider = PROVIDERS.findIndex((item) => item.id === id) === 0

    return {
      ...(isFirstRouting && {
        beforeRow: <h4>Routings</h4>,
      }),
      ...(isFirstProvider && {
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
          <h3>Provider/routing to use</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name={routingOrProviderKey}
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Select
              name={name}
              placeholder="Select provider"
              options={allOptions}
              activeOption={allOptions.find(({id}) => id === value)}
              onSelect={(option) => onChange(option.id)}
              isPortalDropdown={true}
              isActiveOptionOnTop={false}
              dropdownClassName="select-intento-routing-providers"
              maxHeightDroplist={260}
              isDisabled={disabled}
            >
              {getOptionsChildren}
            </Select>
          )}
        />
      </div>
    </div>
  )
}

IntentoOptions.propTypes = {
  id: PropTypes.number.isRequired,
  isCattoolPage: PropTypes.bool,
}
