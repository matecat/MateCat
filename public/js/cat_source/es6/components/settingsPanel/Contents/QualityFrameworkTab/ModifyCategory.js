import React, {useContext, useEffect, useRef, useState} from 'react'
import {
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../../../common/Button/Button'
import PropTypes from 'prop-types'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import usePortal from '../../../../hooks/usePortal'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {
  getCodeFromLabel,
  formatCategoryDescription,
  getCategoryLabelAndDescription,
} from './CategoriesSeveritiesTable'

export const ModifyCategory = ({target, category, setIsEditingName}) => {
  const {portalTarget} = useContext(SettingsPanelContext)
  const {modifyingCurrentTemplate} = useContext(QualityFrameworkTabContext)

  const Portal = usePortal(portalTarget ? portalTarget : document.body)

  const initLabelState = getCategoryLabelAndDescription(category)

  const [, setResizingUpdate] = useState()
  const [label, setLabel] = useState(initLabelState.label)
  const [description, setDescription] = useState(initLabelState.description)

  const ref = useRef()

  useEffect(() => {
    const handler = (e) => {
      const target = ref.current

      if (target && !target.contains(e.target)) setIsEditingName(false)
    }
    const handlerResize = () => setResizingUpdate(Symbol())

    document.addEventListener('mousedown', handler)
    window.addEventListener('resize', handlerResize)

    return () => {
      document.removeEventListener('mousedown', handler)
      window.removeEventListener('resize', handlerResize)
    }
  }, [setIsEditingName])

  const updateLabel = () => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      categories: prevTemplate.categories.map((categoryItem) => ({
        ...categoryItem,
        ...(categoryItem.id === category.id && {
          label: `${label}${description ? ' ' + formatCategoryDescription(description) : ''}`,
        }),
      })),
    }))

    setIsEditingName(false)
  }

  const onChangeLabel = ({currentTarget: {value}}) => {
    setLabel(value)
  }
  const onChangeDescription = ({currentTarget: {value}}) => {
    setDescription(value)
  }

  const cancel = () => setIsEditingName(false)

  const content = (
    <div className="add-popover-content">
      <input
        className="quality-framework-input input"
        placeholder="Name"
        value={label}
        onChange={onChangeLabel}
      />
      <input
        className="quality-framework-input input"
        placeholder="Description"
        value={description}
        onChange={onChangeDescription}
      />
    </div>
  )

  const rect = target.getBoundingClientRect()

  return (
    <Portal>
      <div
        ref={ref}
        className="popover-component-popover quality-framework-modify-category"
        style={{top: `${rect.top}px`, left: `${rect.left}px`}}
      >
        <div className="popover-component-header">
          <span className="popover-component-title">Modify category</span>
        </div>
        <div className="popover-component-body">{content}</div>
        <div className="popover-component-actions">
          <Button
            mode={BUTTON_MODE.OUTLINE}
            size={BUTTON_SIZE.MEDIUM}
            onClick={cancel}
          >
            Cancel
          </Button>
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.MEDIUM}
            onClick={updateLabel}
          >
            <Checkmark size={14} />
            Confirm
          </Button>
        </div>
      </div>
    </Portal>
  )
}

ModifyCategory.propTypes = {
  target: PropTypes.any.isRequired,
  category: PropTypes.object.isRequired,
  setIsEditingName: PropTypes.func.isRequired,
}
