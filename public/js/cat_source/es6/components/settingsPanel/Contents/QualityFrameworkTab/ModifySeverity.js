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
import {getCodeFromLabel} from './CategoriesSeveritiesTable'

export const ModifySeverity = ({target, label, index, setIsEditingName}) => {
  const {portalTarget} = useContext(SettingsPanelContext)
  const {modifyingCurrentTemplate, currentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const Portal = usePortal(portalTarget ? portalTarget : document.body)

  const [, setResizingUpdate] = useState()
  const [labelState, setLabelState] = useState(label)
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
      categories: prevTemplate.categories.map((category) => ({
        ...category,
        severities: category.severities.map((severity, indexSeverity) => ({
          ...severity,
          ...(indexSeverity === index && {
            label: labelState,
            code: getCodeFromLabel(labelState),
          }),
        })),
      })),
    }))

    setIsEditingName(false)
  }

  const validateLabel = (value) => {
    const labels = currentTemplate.categories[0].severities
      .filter((severity, indexItem) => indexItem !== index)
      .map(({label}) => label)

    if (labels.some((label) => label.toLowerCase() === value.toLowerCase()))
      setError('Name already in use for another severity.')
    else setError()
  }

  const onChangeLabel = ({currentTarget: {value}}) => {
    setLabelState(value)
    validateLabel(value)
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
        value={labelState}
        onChange={onChangeLabel}
        autoFocus
      />
      {error && (
        <span className="quality-framework-error-message">{error}</span>
      )}
    </div>
  )

  const rect = target.getBoundingClientRect()

  return (
    <Portal>
      <div
        ref={ref}
        className="popover-component-popover quality-framework-modify-severity"
        style={{top: `${rect.top}px`, left: `${rect.left}px`}}
        data-testid="qf-modify-severity"
      >
        <div className="popover-component-header">
          <span className="popover-component-title">Rename severity</span>
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
            disabled={!labelState || typeof error === 'string'}
          >
            <Checkmark size={14} />
            Confirm
          </Button>
        </div>
      </div>
    </Portal>
  )
}

ModifySeverity.propTypes = {
  target: PropTypes.any.isRequired,
  label: PropTypes.string.isRequired,
  index: PropTypes.number.isRequired,
  setIsEditingName: PropTypes.func.isRequired,
}
