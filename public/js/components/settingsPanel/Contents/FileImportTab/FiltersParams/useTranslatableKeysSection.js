import {useContext, useEffect, useRef, useState} from 'react'
import {useForm} from 'react-hook-form'
import {isEqual} from 'lodash'
import {FiltersParamsContext} from './FiltersParamsContext'

/**
 * Form state for an extraction parameters section that has a
 * Translatable / Non-translatable keys toggle (JSON, YAML, Markdown).
 *
 * The two key lists are mutually exclusive: both fields always hold the same
 * keys, so flipping the toggle keeps what was typed, and only the active one
 * is written to the template.
 *
 * @param {string} sectionKey template property of the section, e.g. 'yaml'
 * @param {{id: string, name: string}[]} segmentedControlOptions the two key
 *   lists, translatable first; each id is the template property it maps to
 * @returns {{control: object, setValue: Function, formData: object|undefined}}
 */
export const useTranslatableKeysSection = (
  sectionKey,
  segmentedControlOptions,
) => {
  const {
    currentTemplate,
    currentProjectTemplateChanged,
    modifyingCurrentTemplate,
  } = useContext(FiltersParamsContext)

  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const section = useRef()
  section.current = currentTemplate[sectionKey] ?? {}

  const options = useRef()
  options.current = segmentedControlOptions

  const temporaryFormData = watch()
  const previousData = useRef()

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

  // write the form to the template, dropping the inactive key list and the
  // fields the stored template does not set (a registered but unset field is
  // undefined, and must not count as a change)
  useEffect(() => {
    if (typeof formData === 'undefined') return

    const {segmentedControl, ...propsValue} = formData

    const restPropsValue = Object.entries(propsValue).reduce(
      (acc, [key, value]) => ({
        ...acc,
        ...(typeof value !== 'undefined' &&
          options.current.every(
            ({id}) => id !== key || id === segmentedControl,
          ) && {[key]: value}),
      }),
      {},
    )

    if (
      !isEqual(section.current, restPropsValue) &&
      Object.keys(restPropsValue).length
    ) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        [sectionKey]: restPropsValue,
      }))
    }
  }, [formData, modifyingCurrentTemplate, sectionKey])

  // set default values for current template
  useEffect(() => {
    options.current.forEach(({id}) => setValue(id, undefined))

    Object.entries(section.current).forEach(([key, value]) =>
      setValue(key, value),
    )

    setValue(
      'segmentedControl',
      options.current.find(({id}) => typeof section.current[id] !== 'undefined')
        ?.id,
    )
  }, [currentTemplate.id, currentProjectTemplateChanged, setValue])

  // keep the inactive key list in step with the active one
  useEffect(() => {
    if (formData?.segmentedControl) {
      setValue(
        options.current.find(({id}) => id !== formData.segmentedControl).id,
        formData[formData.segmentedControl],
      )
    }
  }, [formData, setValue])

  return {control, setValue, formData}
}
