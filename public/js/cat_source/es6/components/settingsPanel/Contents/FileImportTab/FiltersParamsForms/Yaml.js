import React, {useContext, useEffect} from 'react'
import Switch from '../../../../common/Switch'
import {SegmentedControl} from '../../../../common/SegmentedControl'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from '../FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

export const Yaml = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch, unregister} = useForm()

  const {yaml} = currentTemplate

  const data = watch()
  const {keysType, ...propsValue} = data

  useEffect(() => {
    if (!isEqual(yaml, propsValue) && Object.keys(propsValue).length) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        yaml: propsValue,
      }))
    }
  }, [propsValue, yaml, modifyingCurrentTemplate])

  useEffect(() => {
    if (keysType === '1') unregister('translate_keys')
    else unregister('do_not_translate_keys')
  }, [keysType, unregister])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Extract arrays</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={yaml.extract_arrays}
          name="extract_arrays"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Keys</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <div className="container-keys-controller">
          <Controller
            control={control}
            name="keysType"
            defaultValue={'0'}
            render={({field: {onChange, value, name}}) => (
              <SegmentedControl
                name={name}
                className="keys-segmented-control"
                options={[
                  {id: '0', name: 'Translatable'},
                  {id: '1', name: 'Non-translatable'},
                ]}
                selectedId={value}
                onChange={onChange}
              />
            )}
          />
          <Controller
            control={control}
            defaultValue={
              keysType === '1'
                ? yaml.do_not_translate_keys
                : yaml.translate_keys
            }
            name={keysType === '1' ? 'do_not_translate_keys' : 'translate_keys'}
            render={({field: {onChange, value, name}}) => (
              <WordsBadge
                name={name}
                value={value}
                onChange={onChange}
                placeholder={''}
              />
            )}
          />
        </div>
      </div>
    </div>
  )
}
