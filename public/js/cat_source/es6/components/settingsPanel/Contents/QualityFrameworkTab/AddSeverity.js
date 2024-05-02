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
} from '../../../common/Button/Button'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import IconAdd from '../../../icons/IconAdd'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import {getCodeFromLabel} from './CategoriesSeveritiesTable'
import {TOOLTIP_POSITION} from '../../../common/Tooltip'

const MAX_ENTRY = 50

export const AddSeverity = () => {
  const {modifyingCurrentTemplate, currentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const [name, setName] = useState('')
  const [error, setError] = useState()

  const confirmRef = useRef()

  const validateLabel = (value) => {
    const labels = currentTemplate.categories[0].severities.map(
      ({label}) => label,
    )

    if (labels.some((label) => label.toLowerCase() === value.toLowerCase()))
      setError('Name already in use for another severity.')
    else setError()
  }

  const addSeverity = () => {
    const {categories = []} = currentTemplate ?? {}

    let lastId = categories.slice(-1)[0].severities.slice(-1)[0].id
    const newColum = categories.reduce(
      (acc, cur) => [
        ...acc,
        {
          ...cur.severities.slice(-1)[0],
          id: ++lastId,
          label: name,
          code: getCodeFromLabel(name),
          penalty: 0,
        },
      ],
      [],
    )

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      categories: prevTemplate.categories.map((category) => ({
        ...category,
        severities: [
          ...category.severities,
          newColum.find((column) => column.id_category === category.id),
        ],
      })),
    }))
  }

  const confirmWithKeyboard = ({key}) =>
    key === 'Enter' && confirmRef?.current.click()

  const onClose = () => setName()

  const isDisabled =
    currentTemplate.categories[0].severities.length >= MAX_ENTRY

  return (
    <div
      className="quality-framework-add-severity"
      data-testid="qf-add-severity"
    >
      <Popover
        title="Add Severity"
        toggleButtonProps={{
          type: BUTTON_TYPE.PRIMARY,
          mode: BUTTON_MODE.BASIC,
          size: BUTTON_SIZE.ICON_SMALL,
          testId: 'add-severity-button',
          disabled: isDisabled,
          ...(isDisabled && {
            tooltip: `You have reached the limit of ${MAX_ENTRY} severities\nallowed in a quality framework`,
          }),
          tooltipPosition: TOOLTIP_POSITION.LEFT,
          children: (
            <>
              <IconAdd size={22} />
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
          onClick: addSeverity,
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
            onChange={(e) => {
              setName(e.currentTarget.value)
              validateLabel(e.currentTarget.value)
            }}
            autoFocus
          />
          {error && (
            <span className="quality-framework-error-message">{error}</span>
          )}
        </div>
      </Popover>
    </div>
  )
}
