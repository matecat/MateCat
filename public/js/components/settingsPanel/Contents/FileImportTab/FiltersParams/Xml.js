import React, {useContext, useEffect, useRef, useState} from 'react'
import Switch from '../../../../common/Switch'
import {SegmentedControl} from '../../../../common/SegmentedControl'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from './FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

const SEGMENTED_CONTROL_OPTIONS = [
  {id: 'translate_elements', name: 'Translatable'},
  {id: 'do_not_translate_elements', name: 'Non-translatable'},
]

export const Xml = () => {
  const {
    currentTemplate,
    currentProjectTemplateChanged,
    modifyingCurrentTemplate,
  } = useContext(FiltersParamsContext)

  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const xml = useRef()
  xml.current = currentTemplate.xml

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
      !isEqual(xml.current, restPropsValue) &&
      Object.keys(restPropsValue).length
    ) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        xml: restPropsValue,
      }))
    }
  }, [formData, modifyingCurrentTemplate, setValue])

  // set default values for current template
  useEffect(() => {
    SEGMENTED_CONTROL_OPTIONS.forEach(({id}) => setValue(id, undefined))

    Object.entries(xml.current).forEach(([key, value]) => setValue(key, value))

    setValue(
      'segmentedControl',
      SEGMENTED_CONTROL_OPTIONS.find(
        ({id}) => typeof xml.current[id] !== 'undefined',
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
          <h3>Preserve whitespaces</h3>
          <p>Choose whether to preserve whitespace in all elements.</p>
        </div>
        <Controller
          control={control}
          name="preserve_whitespace"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translatable elements</h3>
          <p>
            Choose which elements should be translated. If left empty, all keys
            in the file will be extracted as translatable.
            <br />
            Element names are case sensitive.
            <br />
            If the toggle is set to "Translatable", only the elements entered
            will be extracted as translatable.
            <br />
            If the toggle is set to "Non-translatable", all the elements in the
            file <b>except</b> those entered will be extracted as translatable.
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
          <h3>Translatable attributes</h3>
          <p>
            Enter the attributes whose content should be extracted as
            translatable.
            <br />
            If left empty, no attributes will be extracted.
            <br />
            The structure of each element inside the array should be as follows:
            elementname@attributename.
          </p>
        </div>
        <Controller
          control={control}
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
