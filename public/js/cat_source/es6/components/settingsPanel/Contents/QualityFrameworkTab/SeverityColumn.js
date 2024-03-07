import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../../../img/icons/Trash'
import IconDown from '../../../icons/IconDown'

export const SeverityColumn = ({label}) => {
  const {portalTarget} = useContext(SettingsPanelContext)
  const {templates, currentTemplate} = useContext(QualityFrameworkTabContext)

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

  const menu = (
    <MenuButton
      className="button-menu-button"
      icon={<IconDown width={14} height={14} />}
      onClick={() => false}
      isVisibleRectArrow={false}
      itemsTarget={portalTarget}
    >
      <MenuButtonItem onMouseUp={() => {}}>
        <IconEdit />
        Rename
      </MenuButtonItem>
      <MenuButtonItem>
        <Trash size={16} />
        Delete severity
      </MenuButtonItem>
    </MenuButton>
  )

  return (
    <div className={`column${isNotSaved ? ' column-not-saved' : ''}`}>
      {label}
      {menu}
    </div>
  )
}

SeverityColumn.propTypes = {
  label: PropTypes.string,
}
