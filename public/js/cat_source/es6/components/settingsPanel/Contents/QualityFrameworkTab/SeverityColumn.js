import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../../../img/icons/Trash'
import IconDown from '../../../icons/IconDown'
import {switchArrayIndex} from '../../../../utils/commonUtils'
import LabelWithTooltip from '../../../common/LabelWithTooltip'
import ChevronDown from '../../../../../../../img/icons/ChevronDown'
import {ModifySeverity} from './ModifySeverity'

export const SeverityColumn = ({label, index, shouldScrollIntoView}) => {
  const {portalTarget} = useContext(SettingsPanelContext)
  const {templates, currentTemplate, modifyingCurrentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const [isEditingName, setIsEditingName] = useState(false)

  const ref = useRef()

  useEffect(() => {
    if (shouldScrollIntoView)
      ref.current.scrollIntoView?.({behavior: 'smooth', block: 'nearest'})
  }, [shouldScrollIntoView])

  const checkIsNotSaved = () => {
    if (!templates?.some(({isTemporary}) => isTemporary)) return false

    const originalCurrentTemplate = templates?.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    )

    const isMatched = originalCurrentTemplate.categories.some(({severities}) =>
      severities.some((severity) => severity.label === label),
    )

    if (!isMatched) return true

    const originalColumnSeverity =
      originalCurrentTemplate.categories[0].severities[index]

    const columnsSeverity = currentTemplate.categories[0].severities.find(
      ({id}) => id === originalColumnSeverity?.id,
    )

    const isModified =
      columnsSeverity &&
      originalColumnSeverity?.label !== columnsSeverity?.label

    return isModified
  }

  const isNotSaved = checkIsNotSaved()

  const moveLeft = () => {
    const newIndex = index - 1
    if (newIndex >= 0) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        categories: prevTemplate.categories.map((category) => ({
          ...category,
          severities: switchArrayIndex(category.severities, index, newIndex),
        })),
      }))
    }
  }

  const moveRight = () => {
    const newIndex = index + 1
    if (newIndex <= currentTemplate.categories[0].severities.length - 1) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        categories: prevTemplate.categories.map((category) => ({
          ...category,
          severities: switchArrayIndex(category.severities, index, newIndex),
        })),
      }))
    }
  }

  const deleteSeverity = () => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      categories: prevTemplate.categories.map((category) => ({
        ...category,
        severities: category.severities.filter(
          (severity, indexSeverity) => indexSeverity !== index,
        ),
      })),
    }))
  }

  const isMoveLeftDisabled = index === 0
  const isMoveRightDisabled =
    index === currentTemplate.categories[0].severities.length - 1
  const isDeleteDisabled = currentTemplate.categories[0].severities.length === 1

  const menu = (
    <MenuButton
      className="button-menu-button quality-framework-columns-menu-button"
      icon={<IconDown width={14} height={14} />}
      onClick={() => false}
      isVisibleRectArrow={false}
      itemsTarget={portalTarget}
    >
      <MenuButtonItem
        className="quality-framework-columns-menu-item"
        onMouseUp={() => setIsEditingName(true)}
        data-testid="menu-button-rename"
      >
        <IconEdit />
        Rename
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item quality-framework-columns-menu-item-moveleft"
        onMouseUp={moveLeft}
        disabled={isMoveLeftDisabled}
        data-testid="menu-button-moveleft"
      >
        <ChevronDown />
        Move left
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item quality-framework-columns-menu-item-moveright"
        onMouseUp={moveRight}
        disabled={isMoveRightDisabled}
        data-testid="menu-button-moveright"
      >
        <ChevronDown />
        Move right
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item"
        onMouseUp={deleteSeverity}
        data-testid="menu-button-delete"
        disabled={isDeleteDisabled}
      >
        <Trash size={16} />
        Delete
      </MenuButtonItem>
    </MenuButton>
  )

  return (
    <div
      ref={ref}
      className={`column${isNotSaved ? ' quality-framework-not-saved' : ''}`}
      data-testid={`qf-severity-column-${index}`}
    >
      <LabelWithTooltip className="label" tooltipTarget={portalTarget}>
        <span>{label}</span>
      </LabelWithTooltip>
      {menu}
      {isEditingName && (
        <ModifySeverity
          {...{
            target: ref.current,
            label,
            index,
            setIsEditingName,
          }}
        />
      )}
    </div>
  )
}

SeverityColumn.propTypes = {
  label: PropTypes.string.isRequired,
  index: PropTypes.number.isRequired,
  shouldScrollIntoView: PropTypes.bool,
}
