import React, {useContext, useEffect} from 'react'
import Switch from '../../../../common/Switch'
import {SegmentedControl} from '../../../../common/SegmentedControl'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from '../FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

export const Json = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch, unregister} = useForm()

  const {json} = currentTemplate

  const data = watch()
  const {keysType, ...propsValue} = data

  useEffect(() => {
    if (
      !isEqual(currentTemplate.json, propsValue) &&
      Object.keys(propsValue).length
    ) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        json: propsValue,
      }))
    }
  }, [propsValue, currentTemplate.json, modifyingCurrentTemplate])

  useEffect(() => {
    if (keysType === '1') unregister('translate_keys')
    else unregister('do_not_translate_keys')
  }, [keysType, unregister])

  return (
    <div>
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
          defaultValue={json.extract_arrays}
          name="extract_arrays"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Escape forward slashes</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={json.escape_forward_slashes}
          name="escape_forward_slashes"
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
                ? json.do_not_translate_keys
                : json.translate_keys
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

      <div className="filters-params-option">
        <div>
          <h3>Context keys</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={json.context_keys}
          name="context_keys"
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

      <div className="filters-params-option">
        <div>
          <h3>Character limit keys</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={json.character_limit}
          name="character_limit"
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
  )
}
