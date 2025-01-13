import React, {useContext, useEffect, useRef, useState} from 'react'
import {FiltersParamsContext} from './FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'

export const Dita = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const dita = useRef()
  dita.current = currentTemplate.dita

  const temporaryFormData = watch()
  const previousData = useRef()

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

  useEffect(() => {
    if (typeof formData === 'undefined') return

    const restPropsValue = formData

    if (
      !isEqual(dita.current, restPropsValue) &&
      Object.keys(restPropsValue).length
    ) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        dita: restPropsValue,
      }))
    }
  }, [formData, modifyingCurrentTemplate, setValue])

  // set default values for current template
  useEffect(() => {
    Object.entries(dita.current).forEach(([key, value]) => setValue(key, value))

    if (typeof dita.current.do_not_translate_elements === 'undefined')
      setValue('do_not_translate_elements', [])
  }, [currentTemplate.id, setValue])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Non-translatable elements</h3>
          <p>
            Choose which elements should not be translated. If left empty, only
            elements marked as translatable by the DITA specification will be
            extracted.
            <br />
            Element names are case sensitive.
          </p>
        </div>
        <Controller
          control={control}
          name="do_not_translate_elements"
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
