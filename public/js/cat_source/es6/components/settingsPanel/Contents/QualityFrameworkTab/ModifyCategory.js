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
  formatCategoryDescription,
  getCategoryLabelAndDescription,
  getCodeFromLabel,
} from './CategoriesSeveritiesTable'

export const ModifyCategory = ({target, category, setIsEditingName}) => {
  const {portalTarget} = useContext(SettingsPanelContext)
  const {modifyingCurrentTemplate, currentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const Portal = usePortal(portalTarget ? portalTarget : document.body)

  const initLabelState = getCategoryLabelAndDescription(category)

  const [, setResizingUpdate] = useState()
  const [label, setLabel] = useState(initLabelState.label)
  const [description, setDescription] = useState(initLabelState.description)
  const [error, setError] = useState()

  const ref = useRef()
  const confirmRef = useRef()

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
      categories: prevTemplate.categories.map((categoryItem) => {
        const name = `${label}${description ? ' ' + formatCategoryDescription(description) : ''}`
        return {
          ...categoryItem,
          ...(categoryItem.id === category.id && {
            label: name,
            code: getCodeFromLabel(name),
          }),
        }
      }),
    }))

    setIsEditingName(false)
  }

  const validateLabel = (value) => {
    const labels = currentTemplate.categories
      .filter(({id}) => id !== category.id)
      .map((categoryItem) => getCategoryLabelAndDescription(categoryItem))

    if (labels.some(({label}) => label.toLowerCase() === value.toLowerCase()))
      setError('Name already in use for another category.')
    else setError()
  }

  const onChangeLabel = ({currentTarget: {value}}) => {
    setLabel(value)
    validateLabel(value)
  }
  const onChangeDescription = ({currentTarget: {value}}) => {
    setDescription(value)
  }

  const confirmWithKeyboard = ({key}) =>
    key === 'Enter' && confirmRef?.current.click()

  const cancel = () => setIsEditingName(false)

  const content = (
    <div
      className="add-popover-content"
      tabIndex={0}
      onKeyUp={confirmWithKeyboard}
    >
      <input
        className={`quality-framework-input input${error ? ' quality-framework-input-error' : ''}`}
        placeholder="Name"
        value={label}
        onChange={onChangeLabel}
        autoFocus
      />
      {error && (
        <span className="quality-framework-error-message">{error}</span>
      )}
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
        data-testid="qf-modify-category"
      >
        <div className="popover-component-header">
          <span className="popover-component-title">Edit category</span>
        </div>
        <div
          className="popover-component-body"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              cancel()
              event.stopPropagation()
            }
          }}
        >
          {content}
        </div>
        <div className="popover-component-actions">
          <Button
            mode={BUTTON_MODE.OUTLINE}
            size={BUTTON_SIZE.MEDIUM}
            onClick={cancel}
          >
            Cancel
          </Button>
          <Button
            ref={confirmRef}
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.MEDIUM}
            onClick={updateLabel}
            disabled={!label || typeof error === 'string'}
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
