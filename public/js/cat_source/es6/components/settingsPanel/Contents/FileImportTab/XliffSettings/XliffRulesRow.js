import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
} from '../../../../common/Button/Button'
import Trash from '../../../../../../../../img/icons/Trash'
import {Select} from '../../../../common/Select'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

export const XliffRulesRow = ({value, onChange, onDelete, xliffOptions}) => {
  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const temporaryFormData = watch()
  const previousData = useRef()

  const valueRef = useRef()
  valueRef.current = value

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)
    previousData.current = temporaryFormData
  }, [temporaryFormData])

  useEffect(() => {
    if (typeof formData === 'undefined') return
    console.log('@formData', formData)
    const propsValue = Object.entries(formData).reduce(
      (acc, [key, value]) => ({
        ...acc,
        ...(typeof value !== 'undefined' && {[key]: value}),
      }),
      {},
    )

    if (
      !isEqual(valueRef.current, propsValue) &&
      Object.keys(propsValue).length
    ) {
      onChange(propsValue)
    }
  }, [formData, setValue])

  // set default values for current template
  useEffect(() => {
    Object.entries(value).forEach(([key, value]) => setValue(key, value))
  }, [value, setValue])

  const MAX_HEIGHT_DROPLIST = 320

  const deleteRow = () => onDelete(value.id)

  return (
    <>
      <span className="xliff-settings-column-content">{value.id + 1}.</span>
      <div className="xliff-settings-column-content">
        <Controller
          control={control}
          name="states"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              options={xliffOptions.states.map((value) => ({
                id: value,
                name: value,
              }))}
              multipleSelect="dropdown"
              activeOptions={value?.map((v) => ({id: v, name: v}))}
              onToggleOption={(option) => {
                const updatedOptions = value.some((id) => id === option.id)
                  ? value.filter((id) => id !== option.id)
                  : value.concat([option.id])

                onChange(updatedOptions)
              }}
              maxHeightDroplist={MAX_HEIGHT_DROPLIST}
            />
          )}
        />
      </div>
      <div className="xliff-settings-column-content">
        <Controller
          control={control}
          name="analysis"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              placeholder="Select analysis"
              options={xliffOptions.analysis.map((value) => ({
                id: value,
                name: value,
              }))}
              activeOption={{id: value, name: value}}
              onSelect={(option) => onChange(option.id)}
              maxHeightDroplist={MAX_HEIGHT_DROPLIST}
            />
          )}
        />
      </div>
      <div className="xliff-settings-column-content">
        <Controller
          control={control}
          name="editor"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              placeholder="Select editor"
              options={xliffOptions.editor.map((value) => ({
                id: value,
                name: value,
              }))}
              activeOption={value && {id: value, name: value}}
              onSelect={(option) => onChange(option.id)}
              isDisabled={
                typeof value === 'undefined' || formData.analysis === 'new'
              }
              maxHeightDroplist={MAX_HEIGHT_DROPLIST}
            />
          )}
        />
      </div>
      <Button
        className="xliff-settings-column-content"
        mode={BUTTON_MODE.GHOST}
        size={BUTTON_SIZE.SMALL}
        onClick={deleteRow}
      >
        <Trash size={16} />
      </Button>
    </>
  )
}

XliffRulesRow.propTypes = {
  value: PropTypes.object.isRequired,
  onChange: PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
  xliffOptions: PropTypes.object.isRequired,
}
