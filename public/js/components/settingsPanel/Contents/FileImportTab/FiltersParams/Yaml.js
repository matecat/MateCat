import React, {useContext, useEffect, useRef, useState} from 'react'
import {SegmentedControl} from '../../../../common/SegmentedControl'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from './FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'
import {Select} from '../../../../common/Select'
import Switch from '../../../../common/Switch'

const SEGMENTED_CONTROL_OPTIONS = [
  {id: 'translate_keys', name: 'Translatable'},
  {id: 'do_not_translate_keys', name: 'Non-translatable'},
]

const INNER_CONTENT_TYPE_OPTIONS = [
  {id: 'text/html', name: 'HTML'},
  {id: 'text/xml', name: 'XML'},
  {id: 'text/csv', name: 'CSV'},
  {id: 'application/json', name: 'JSON'},
  {id: 'text/markdown', name: 'Markdown'},
]

export const Yaml = () => {
  const {
    currentTemplate,
    currentProjectTemplateChanged,
    modifyingCurrentTemplate,
  } = useContext(FiltersParamsContext)

  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const yaml = useRef()
  yaml.current = currentTemplate.yaml

  const temporaryFormData = watch()
  const previousData = useRef()

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

  useEffect(() => {
    if (typeof formData === 'undefined') return

    const {segmentedControl, ...propsValue} = formData

    const restPropsValue = Object.entries(propsValue).reduce(
      (acc, [key, value]) => ({
        ...acc,
        ...(SEGMENTED_CONTROL_OPTIONS.every(
          ({id}) => id !== key || id === segmentedControl,
        ) && {[key]: value}),
      }),
      {},
    )

    if (
      !isEqual(yaml.current, restPropsValue) &&
      Object.keys(restPropsValue).length
    ) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        yaml: restPropsValue,
      }))
    }
  }, [formData, modifyingCurrentTemplate, setValue])

  // set default values for current template
  useEffect(() => {
    SEGMENTED_CONTROL_OPTIONS.forEach(({id}) => setValue(id, undefined))

    Object.entries(yaml.current).forEach(([key, value]) => setValue(key, value))

    setValue(
      'segmentedControl',
      SEGMENTED_CONTROL_OPTIONS.find(
        ({id}) => typeof yaml.current[id] !== 'undefined',
      )?.id,
    )
  }, [currentTemplate.id, currentProjectTemplateChanged, setValue])

  const {segmentedControl} = formData ?? {}

  useEffect(() => {
    if (formData?.segmentedControl) {
      setValue(
        SEGMENTED_CONTROL_OPTIONS.find(
          ({id}) => id !== formData.segmentedControl,
        ).id,
        formData[formData.segmentedControl],
      )
    }
  }, [formData, setValue])

  const renderActiveSegmentedController = (
    <>
      {SEGMENTED_CONTROL_OPTIONS.filter(({id}) => id === segmentedControl).map(
        ({id}) => (
          <Controller
            key={id}
            control={control}
            name={id}
            render={({field: {onChange, value, name}}) => (
              <WordsBadge
                name={name}
                value={value}
                onChange={onChange}
                placeholder={''}
              />
            )}
          />
        ),
      )}
    </>
  )

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Translatable keys </h3>
          <p>
            Choose which keys should be translated. If left empty, all keys in
            the file will be extracted as translatable.
            <br />
            Key names are case sensitive.
            <br />
            If the toggle is set to "Translatable", only the keys entered will
            be extracted as translatable.
            <br />
            If the toggle is set to "Non-translatable", all the keys in the file{' '}
            <b>except</b> those entered will be extracted as translatable.
          </p>
        </div>
        <div className="container-segmented-control">
          <Controller
            control={control}
            name="segmentedControl"
            render={({field: {onChange, value, name}}) => (
              <SegmentedControl
                name={name}
                className="custom-segmented-control"
                options={SEGMENTED_CONTROL_OPTIONS}
                selectedId={value}
                onChange={onChange}
              />
            )}
          />
          {renderActiveSegmentedController}
        </div>
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Context keys</h3>
          <p>
            Choose which keys should be extracted as context for translatable
            keys.
            <br />
            Key names are case sensitive.
            <br />
            The extracted notes will be applied to translatable keys in the same
            object scope.
          </p>
        </div>
        <Controller
          control={control}
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
            Choose which keys should be extracted as the character limit for
            translatable keys.
            <br />
            Key names are case sensitive.
            <br />
            Character limits will be applied to translatable keys in the same
            object.
            <br />
            Keys with a character limit won't be segmented.
          </p>
        </div>
        <Controller
          control={control}
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

      <div className="filters-params-option">
        <div>
          <h3>Translatable text content type</h3>
          <p>
            Select the content type of the translatable text to optimize
            segmentation and tags.
          </p>
        </div>
        <Controller
          control={control}
          name="inner_content_type"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              placeholder="Select inner content type"
              options={INNER_CONTENT_TYPE_OPTIONS}
              activeOption={INNER_CONTENT_TYPE_OPTIONS?.find(
                ({id}) => id === value,
              )}
              onSelect={(option) => onChange(option.id)}
              maxHeightDroplist={260}
            />
          )}
        />
      </div>
    </div>
  )
}
