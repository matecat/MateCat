import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../img/icons/Trash'
import IconDown from '../../../icons/IconDown'
import LabelWithTooltip from '../../../common/LabelWithTooltip'
import ChevronDown from '../../../../../img/icons/ChevronDown'
import {ModifySeverity} from './ModifySeverity'

export const orderSeverityBySort = (severities) =>
  severities.sort((a, b) => (a.sort > b.sort ? 1 : -1))

export const SeverityColumn = ({
  label,
  code,
  index,
  sort,
  numbersOfColumns,
  shouldScrollIntoView,
}) => {
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

  const switchSort = ({severities, sort, newSort}) =>
    orderSeverityBySort(
      severities.map((severity) => {
        const modifiedSort =
          severity.sort === sort
            ? newSort
            : newSort < sort && severity.sort < sort && newSort <= severity.sort
              ? severity.sort + 1
              : newSort > sort &&
                  severity.sort > sort &&
                  newSort >= severity.sort
                ? severity.sort - 1
                : severity.sort

        return {...severity, sort: modifiedSort}
      }),
    )

  const moveLeft = () => {
    const newSort = sort - 1
    if (newSort >= 1) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        categories: prevTemplate.categories.map((category) => ({
          ...category,
          severities: switchSort({
            severities: category.severities,
            sort,
            newSort,
          }),
        })),
      }))
    }
  }

  const moveRight = () => {
    const newSort = sort + 1
    if (newSort <= numbersOfColumns) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        categories: prevTemplate.categories.map((category) => ({
          ...category,
          severities: switchSort({
            severities: category.severities,
            sort,
            newSort,
          }),
        })),
      }))
    }
  }

  const deleteSeverity = () => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      categories: prevTemplate.categories.map((category) => ({
        ...category,
        severities: category.severities
          .filter(
            (severity) => !(severity.label === label && severity.code === code),
          )
          .map((severity) => ({
            ...severity,
            sort: severity.sort > sort ? severity.sort - 1 : severity.sort,
          })),
      })),
    }))
  }

  const isMoveLeftDisabled = sort === 1
  const isMoveRightDisabled = sort === numbersOfColumns
  const isDeleteDisabled = currentTemplate.categories.every(
    ({severities}) => severities.length === 1,
  )

  const menu = (
    <MenuButton
      className="button-menu-button quality-framework-columns-menu-button"
      icon={<IconDown size={20} />}
      onClick={() => false}
      isVisibleRectArrow={false}
      itemsTarget={portalTarget}
    >
      <MenuButtonItem
        className="quality-framework-columns-menu-item"
        onMouseUp={() => setIsEditingName(true)}
        data-testid="menu-button-rename"
      >
        <IconEdit size={18} />
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
        <Trash size={18} />
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
            code,
            index,
            sort,
            setIsEditingName,
          }}
        />
      )}
    </div>
  )
}

SeverityColumn.propTypes = {
  label: PropTypes.string.isRequired,
  code: PropTypes.string.isRequired,
  index: PropTypes.number.isRequired,
  sort: PropTypes.number.isRequired,
  numbersOfColumns: PropTypes.number.isRequired,
  shouldScrollIntoView: PropTypes.bool,
}
