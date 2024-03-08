import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../../../img/icons/Trash'
import IconDown from '../../../icons/IconDown'
import {switchArrayIndex} from '../../../../utils/commonUtils'

export const SeverityColumn = ({label, index}) => {
  const {portalTarget} = useContext(SettingsPanelContext)
  const {templates, currentTemplate, modifyingCurrentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const checkIsNotSaved = () => {
    if (!templates?.some(({isTemporary}) => isTemporary)) return false

    const originalCurrentTemplate = templates?.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    )

    return !originalCurrentTemplate.categories.some(({severities}) =>
      severities.some((severity) => severity.label === label),
    )
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

  const isMoveLeftDisabled = index === 0
  const isMoveRightDisabled =
    index === currentTemplate.categories[0].severities.length - 1

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
        onMouseUp={() => {}}
      >
        <IconEdit />
        Rename
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item"
        onMouseUp={moveLeft}
        disabled={isMoveLeftDisabled}
      >
        Move left
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item"
        onMouseUp={moveRight}
        disabled={isMoveRightDisabled}
      >
        Move right
      </MenuButtonItem>
      <MenuButtonItem className="quality-framework-columns-menu-item">
        <Trash size={16} />
        Delete severity
      </MenuButtonItem>
    </MenuButton>
  )

  return (
    <div className={`column${isNotSaved ? ' column-not-saved' : ''}`}>
      <span className="label">{label}</span>
      {menu}
    </div>
  )
}

SeverityColumn.propTypes = {
  label: PropTypes.string.isRequired,
  index: PropTypes.number.isRequired,
}
