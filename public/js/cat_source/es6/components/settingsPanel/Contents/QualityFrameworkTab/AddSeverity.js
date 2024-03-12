import React, {useContext, useState} from 'react'
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

export const AddSeverity = () => {
  const {modifyingCurrentTemplate, currentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const [name, setName] = useState()

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

  const onClose = () => setName()

  return (
    <div className="quality-framework-add-severity">
      <Popover
        title="Add Severity"
        toggleButtonProps={{
          type: BUTTON_TYPE.PRIMARY,
          mode: BUTTON_MODE.BASIC,
          size: BUTTON_SIZE.ICON_SMALL,
          children: (
            <>
              <IconAdd size={22} />
            </>
          ),
        }}
        confirmButtonProps={{
          type: BUTTON_TYPE.PRIMARY,
          size: BUTTON_SIZE.MEDIUM,
          disabled: !name,
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
        <div className="add-popover-content">
          <input
            className="quality-framework-input input"
            placeholder="Name"
            value={name}
            onChange={(e) => setName(e.currentTarget.value)}
          />
        </div>
      </Popover>
    </div>
  )
}
