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

const KEY_ROUTING = 'intento_routing'
const KEY_PROVIDER = 'intento_provider'

export const IntentoOptions = ({id, isCattoolPage}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const {control, setValue} = useOptions()

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
            .map((item) => ({...item, id: item.name }))
            .sort((a) => (a.id === 'smart_routing' ? -1 : 1)),
        )
      }
    })

    return () => (cleanup = true)
  }, [id])

  const routingOrProviderKey = currentProjectTemplate.mt?.extra
    ?.intento_provider
    ? KEY_PROVIDER
    : KEY_ROUTING

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
          <h3>Provider or routing</h3>
          <p>Select the provider or routing for the project.</p>
        </div>
        <Controller
          control={control}
          name={routingOrProviderKey}
          disabled={isCattoolPage}
          render={({field: {value, name, disabled}}) => (
            <Select
              name={name}
              placeholder="Select provider"
              showSearchBar={true}
              options={allOptions}
              activeOption={allOptions.find(({id}) => id === value)}
              onSelect={(option) => {
                const actualKey = routings.some((item) => item.id === option.id)
                  ? KEY_ROUTING
                  : KEY_PROVIDER

                setValue(actualKey, option.id)
                setValue(actualKey === KEY_ROUTING ? KEY_PROVIDER : KEY_ROUTING)
              }}
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
