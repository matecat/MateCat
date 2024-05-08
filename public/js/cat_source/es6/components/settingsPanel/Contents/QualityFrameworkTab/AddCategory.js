import React, {useContext, useRef, useState} from 'react'
import {
  POPOVER_ALIGN,
  POPOVER_VERTICAL_ALIGN,
  Popover,
} from '../../../common/Popover/Popover'
import {
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../../../common/Button/Button'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import IconAdd from '../../../icons/IconAdd'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import {
  getCodeFromLabel,
  formatCategoryDescription,
  getCategoryLabelAndDescription,
} from './CategoriesSeveritiesTable'
import {TOOLTIP_POSITION} from '../../../common/Tooltip'

const MAX_ENTRY = 50

export const AddCategory = () => {
  const {modifyingCurrentTemplate, currentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const [isVisibleDescriptionInput, setIsVisibleDescriptionInput] =
    useState(false)
  const [fields, setFields] = useState({name: '', description: ''})
  const [error, setError] = useState()

  const confirmRef = useRef()

  const validateLabel = (value) => {
    const labels = currentTemplate.categories.map((categoryItem) =>
      getCategoryLabelAndDescription(categoryItem),
    )

    if (labels.some(({label}) => label.toLowerCase() === value.toLowerCase()))
      setError('Name already in use for another category.')
    else setError()
  }

  const {name, description} = fields
  const setName = ({currentTarget: {value}}) => {
    setFields((prevState) => ({...prevState, name: value}))
    validateLabel(value)
  }
  const setDescription = ({currentTarget: {value}}) =>
    setFields((prevState) => ({...prevState, description: value}))

  const addCategory = () => {
    const {categories = []} = currentTemplate ?? {}

    const lastCategory = categories.slice(-1)[0]
    const {id: lastCategoryId, severities: lastCategorySeverities} =
      lastCategory
    let lastSeverityId = lastCategory.severities.slice(-1)[0].id
    const newCategoryId = lastCategoryId + 1

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      categories: [
        ...prevTemplate.categories,
        {
          ...lastCategory,
          id: newCategoryId,
          label: `${name}${description ? formatCategoryDescription(description) : ''}`,
          code: getCodeFromLabel(name),
          severities: lastCategorySeverities.map((severity) => ({
            ...severity,
            id: ++lastSeverityId,
            id_category: newCategoryId,
            penalty: 0,
          })),
        },
      ],
    }))
  }

  const confirmWithKeyboard = ({key}) =>
    key === 'Enter' && confirmRef?.current.click()

  const onClose = () => {
    setIsVisibleDescriptionInput(false)
    setFields({name: '', description: ''})
  }

  const isDisabled = currentTemplate.categories.length >= MAX_ENTRY

  return (
    <div
      className="quality-framework-add-category"
      data-testid="qf-add-category"
    >
      <Popover
        title="Add category"
        toggleButtonProps={{
          type: BUTTON_TYPE.PRIMARY,
          mode: BUTTON_MODE.BASIC,
          size: BUTTON_SIZE.MEDIUM,
          disabled: isDisabled,
          ...(isDisabled && {
            tooltip: `You have reached the limit of ${MAX_ENTRY} categories\nallowed in a quality framework`,
          }),
          tooltipPosition: TOOLTIP_POSITION.RIGHT,
          children: (
            <>
              <IconAdd size={22} /> Add category
            </>
          ),
        }}
        confirmButtonProps={{
          ref: confirmRef,
          type: BUTTON_TYPE.PRIMARY,
          size: BUTTON_SIZE.MEDIUM,
          disabled: !name || typeof error === 'string',
          children: (
            <>
              <Checkmark size={14} />
              Confirm
            </>
          ),
          onClick: addCategory,
        }}
        cancelButtonProps={{
          mode: BUTTON_MODE.OUTLINE,
          size: BUTTON_SIZE.MEDIUM,
          children: 'Cancel',
        }}
        align={POPOVER_ALIGN.RIGHT}
        verticalAlign={POPOVER_VERTICAL_ALIGN.TOP}
        onClose={onClose}
      >
        <div
          className="add-popover-content"
          tabIndex={0}
          onKeyUp={confirmWithKeyboard}
        >
          <input
            className={`quality-framework-input input${error ? ' quality-framework-input-error' : ''}`}
            placeholder="Name"
            value={name}
            onChange={setName}
            autoFocus
          />
          {error && (
            <span className="quality-framework-error-message">{error}</span>
          )}
          {!isVisibleDescriptionInput ? (
            <Button
              className="add-description"
              mode={BUTTON_MODE.GHOST}
              size={BUTTON_SIZE.SMALL}
              onClick={() => setIsVisibleDescriptionInput(true)}
            >
              <IconAdd size={20} /> Add description
            </Button>
          ) : (
            <input
              className="quality-framework-input input"
              placeholder="Description"
              value={description}
              onChange={setDescription}
              autoFocus
            />
          )}
        </div>
      </Popover>
    </div>
  )
}
