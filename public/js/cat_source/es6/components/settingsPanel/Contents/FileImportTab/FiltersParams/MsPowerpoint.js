import React, {useContext, useEffect, useRef, useState} from 'react'
import Switch from '../../../../common/Switch'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from './FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

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
      ...(extract_hidden_slides ? {translate_slides} : {extract_hidden_slides}),
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
      setValue('extract_hidden_slides', true)

    if (
      typeof msPowerpoint.current.extract_hidden_slides === 'boolean' &&
      !msPowerpoint.current.extract_hidden_slides
    )
      setValue('translate_slides', [])
  }, [currentTemplate.id, setValue])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Extract hidden slides</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
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
          <h3>Extract speaker notes</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
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
          <h3>Extract document properties</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
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
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="translate_slides"
          disabled={!formData?.extract_hidden_slides}
          render={({field: {onChange, value, name}}) => (
            <WordsBadge
              name={name}
              value={value}
              onChange={onChange}
              placeholder={''}
              disabled={!formData?.extract_hidden_slides}
            />
          )}
        />
      </div>
    </div>
  )
}
