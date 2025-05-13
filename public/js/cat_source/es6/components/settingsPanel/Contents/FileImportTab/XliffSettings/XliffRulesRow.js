import React, {useEffect, useRef, useState} from 'react'
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

const getMatchCategoryShortId = (id) => id.replace(/_match_category/, '')
const getMatchCategoryExtendedId = (id) => `${id}_match_category`

export const XliffRulesRow = ({
  value,
  onChange,
  onDelete,
  currentXliffData,
  xliffOptions,
}) => {
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

    const {editor, match_category, ...restProps} = formData

    const propsValue = {
      ...restProps,
      ...(formData.analysis !== 'new' && {editor, match_category}),
    }

    if (
      !isEqual(valueRef.current, propsValue) &&
      Object.keys(propsValue).length
    ) {
      onChange(propsValue)
    }
  }, [formData, setValue, onChange])

  // set default values for current template
  useEffect(() => {
    Object.entries(value).forEach(([key, value]) => setValue(key, value))

    if (value.analysis !== 'new' && typeof value.editor === 'undefined')
      setValue('editor', xliffOptions.editor[0])
  }, [value, setValue, xliffOptions.editor])

  const MAX_HEIGHT_DROPLIST = 260

  const deleteRow = () => onDelete(value.id)

  const statesOptions = xliffOptions.states
    .filter(
      (state) =>
        !currentXliffData
          .reduce((acc, {states}) => [...acc, ...(states ?? [])], [])
          .some(
            (stateCompare) =>
              state === stateCompare &&
              value.states.every((v) => v !== stateCompare),
          ),
    )
    .map((value) => ({id: value, name: value}))

  const optionsAnalysis = xliffOptions.analysis.reduce(
    (acc, value) =>
      value === 'pre-translated'
        ? [
            ...acc,
            ...xliffOptions.match_category.map((value) => ({
              ...value,
              id: getMatchCategoryExtendedId(value.id),
            })),
          ]
        : [...acc, {id: value, name: value === 'new' ? 'N.A.' : value}],
    [],
  )

  const getAnalysisActiveOption = (id) =>
    optionsAnalysis.find(
      (option) =>
        option.id ===
        (id !== 'new'
          ? getMatchCategoryExtendedId(formData.match_category)
          : id),
    )

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
              placeholder="Select state"
              options={statesOptions}
              multipleSelect="dropdown"
              activeOptions={value && value?.map((v) => ({id: v, name: v}))}
              onToggleOption={(option) => {
                const updatedOptions = value.some((id) => id === option.id)
                  ? value.filter((id) => id !== option.id)
                  : value.concat([option.id])

                if (updatedOptions.length) onChange(updatedOptions)
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
              options={optionsAnalysis}
              activeOption={value && getAnalysisActiveOption(value)}
              onSelect={(option) => {
                onChange(option.id !== 'new' ? 'pre-translated' : option.id)
                if (option.id !== 'new')
                  setValue('match_category', getMatchCategoryShortId(option.id))
              }}
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
              activeOption={
                formData?.analysis === 'new'
                  ? {id: 'na', name: 'N.A.'}
                  : value && {id: value, name: value}
              }
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
  currentXliffData: PropTypes.array.isRequired,
  xliffOptions: PropTypes.object.isRequired,
}
