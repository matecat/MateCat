import React, {useContext, useEffect} from 'react'
import Switch from '../../../../common/Switch'
import {SegmentedControl} from '../../../../common/SegmentedControl'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from '../FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

export const Xml = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch, unregister} = useForm()

  const {xml} = currentTemplate

  const data = watch()
  const {elementsType, ...propsValue} = data

  useEffect(() => {
    if (!isEqual(xml, propsValue) && Object.keys(propsValue).length) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        xml: propsValue,
      }))
    }
  }, [propsValue, xml, modifyingCurrentTemplate])

  useEffect(() => {
    if (elementsType === '1') unregister('translate_elements')
    else unregister('do_not_translate_elements')
  }, [elementsType, unregister])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Preserve whitespaces</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={xml.preserve_whitespace}
          name="preserve_whitespace"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Elements</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <div className="container-keys-controller">
          <Controller
            control={control}
            name="elementsType"
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
              elementsType === '1'
                ? xml.do_not_translate_elements
                : xml.translate_elements
            }
            name={
              elementsType === '1'
                ? 'do_not_translate_elements'
                : 'translate_elements'
            }
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
          <h3>Translatable attributes</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={xml.translate_attributes}
          name="translate_attributes"
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
