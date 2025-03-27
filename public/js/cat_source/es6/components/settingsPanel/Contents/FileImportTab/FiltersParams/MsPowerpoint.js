import React, {useContext, useEffect, useRef, useState} from 'react'
import Switch from '../../../../common/Switch'
import {FiltersParamsContext} from './FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'
import {NumbersDashBadge} from './NumbersDashBadge'

const convertTranslateSlidesToServer = (value) =>
  value.reduce((acc, cur) => {
    const [firstNum, secondNum] = cur.split('-')
    const arrayLength =
      typeof secondNum === 'string'
        ? parseInt(secondNum) - parseInt(firstNum)
        : 1
    const result = new Array(arrayLength)
      .fill('')
      .map((item, index) => parseInt(firstNum) + index)
      .filter((value) => acc.every((valueCompare) => valueCompare !== value))

    return [...acc, ...result].sort((a, b) => a - b)
  }, [])

export const MsPowerpoint = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const msPowerpoint = useRef()
  msPowerpoint.current = currentTemplate.msPowerpoint

  const temporaryFormData = watch()
  const previousData = useRef()

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

  useEffect(() => {
    if (typeof formData === 'undefined') return

    const {translate_slides, extract_hidden_slides, ...propsValue} = formData

    const restPropsValue = {
      ...propsValue,
      ...(!extract_hidden_slides
        ? {translate_slides}
        : {extract_hidden_slides}),
    }

    if (
      !isEqual(msPowerpoint.current, restPropsValue) &&
      Object.keys(restPropsValue).length
    ) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        msPowerpoint: restPropsValue,
      }))
    }
  }, [formData, modifyingCurrentTemplate, setValue])

  // set default values for current template
  useEffect(() => {
    Object.entries(msPowerpoint.current).forEach(([key, value]) =>
      setValue(key, value),
    )
    if (Array.isArray(msPowerpoint.current.translate_slides))
      setValue('extract_hidden_slides', false)

    if (
      typeof msPowerpoint.current.extract_hidden_slides === 'boolean' &&
      (msPowerpoint.current.extract_hidden_slides ||
        typeof msPowerpoint.current.translate_slides === 'undefined')
    )
      setValue('translate_slides', [])
  }, [currentTemplate.id, setValue])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Translate hidden slides</h3>
          <p>
            Choose whether to translate text in hidden slides.
            <br />
            Mutually exclusive with "Translatable slides"
          </p>
        </div>
        <Controller
          control={control}
          name="extract_hidden_slides"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate speaker notes</h3>
          <p>
            Choose whether to translate speaker notes.
            <br />
            If activated, speaker notes will be extracted for all slides,
            including hidden slides not being extracted for translation.
            <br />
            However, if activated in combination with the "Translatable slides"
            option, only the notes for the slides listed will be extracted.
          </p>
        </div>
        <Controller
          control={control}
          name="extract_notes"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate document properties</h3>
          <p>
            Choose whether to translate document properties (e.g. the author's
            name).
          </p>
        </div>
        <Controller
          control={control}
          name="extract_doc_properties"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translatable slides</h3>
          <p>
            Choose which slides should be translated.
            <br />
            If left empty, all the slides in the file will be extracted as
            translatable except for hidden slides unless otherwise specified
            through the dedicated option.
          </p>
        </div>
        <Controller
          control={control}
          name="translate_slides"
          disabled={formData?.extract_hidden_slides}
          render={({field: {onChange, value, name}}) => (
            <NumbersDashBadge
              name={name}
              value={value}
              onChange={onChange}
              placeholder={''}
              disabled={formData?.extract_hidden_slides}
            />
          )}
        />
      </div>
    </div>
  )
}
