import React, {useContext, useEffect, useRef, useState} from 'react'
import Switch from '../../../../common/Switch'
import {FiltersParamsContext} from './FiltersParamsContext'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {Select} from '../../../../common/Select'

const INNER_CONTENT_TYPE_OPTIONS = [
  {id: 'text/plain', name: 'Plain text'},
  {id: 'text/html', name: 'HTML'},
]

export const MsExcel = () => {
  const {
    currentTemplate,
    currentProjectTemplateChanged,
    modifyingCurrentTemplate,
  } = useContext(FiltersParamsContext)

  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const msExcel = useRef()
  msExcel.current = currentTemplate.msExcel

  const temporaryFormData = watch()
  const previousData = useRef()

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

  useEffect(() => {
    if (typeof formData === 'undefined') return

    if (!isEqual(msExcel.current, formData) && Object.keys(formData).length) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        msExcel: formData,
      }))
    }
  }, [formData, modifyingCurrentTemplate, setValue])

  // set default values for current template
  useEffect(() => {
    Object.entries(msExcel.current).forEach(([key, value]) =>
      setValue(key, value),
    )
  }, [currentTemplate.id, currentProjectTemplateChanged, setValue])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Translate hidden cells</h3>
          <p>Choose whether to translate text in hidden cells.</p>
        </div>
        <Controller
          control={control}
          name="extract_hidden_cells"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate chart texts</h3>
          <p>Choose whether to translate chart titles and axis names.</p>
        </div>
        <Controller
          control={control}
          name="extract_diagrams"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate text boxes</h3>
          <p>Choose whether to translate text in text boxes.</p>
        </div>
        <Controller
          control={control}
          name="extract_drawings"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate sheet names</h3>
          <p>Choose whether to translate sheet names.</p>
        </div>
        <Controller
          control={control}
          name="extract_sheet_names"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate document properties</h3>
          <p>
            Choose whether to translate document properties (e.g. the author&apos;s
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
          <h3>Exclude columns</h3>
          <p>
            Choose which columns should not be translated.
            <br />
            The format for the entered items should be sheet number + column
            letter.
            <br />
            E.g.: enter 1C to exclude from translation column C of the first
            sheet in the file.
          </p>
        </div>
        <Controller
          control={control}
          name="exclude_columns"
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
              isPortalDropdown={true}
              options={INNER_CONTENT_TYPE_OPTIONS}
              activeOption={INNER_CONTENT_TYPE_OPTIONS?.find(
                ({id}) => id === value,
              )}
              onSelect={(option) => onChange(option.id)}
            />
          )}
        />
      </div>
    </div>
  )
}
