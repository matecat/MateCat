import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import IconDown from '../../../icons/IconDown'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../../../img/icons/Trash'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {switchArrayIndex} from '../../../../utils/commonUtils'
import LabelWithTooltip from '../../../common/LabelWithTooltip'
import {ModifyCategory} from './ModifyCategory'
import {getCategoryLabelAndDescription} from './CategoriesSeveritiesTable'
import ChevronDown from '../../../../../../../img/icons/ChevronDown'

export const CategoryRow = ({category, index, shouldScrollIntoView}) => {
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

  const {label, description} = getCategoryLabelAndDescription(category)

  const checkIsNotSaved = () => {
    if (!templates?.some(({isTemporary}) => isTemporary)) return false

    const originalCurrentTemplate = templates?.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    )

    const isMatched = originalCurrentTemplate.categories.some(
      ({id}) => id === category.id,
    )

    if (!isMatched) return true

    const originalCategory = originalCurrentTemplate.categories.find(
      ({id}) => id === category.id,
    )
    const currentCategory = currentTemplate.categories.find(
      ({id}) => id === category.id,
    )

    const isModified =
      originalCategory?.id !== currentCategory.id ||
      originalCategory?.label !== currentCategory.label

    return isModified
  }

  const isNotSaved = checkIsNotSaved()

  const moveUp = () => {
    const newIndex = index - 1
    if (newIndex >= 0) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        categories: switchArrayIndex(prevTemplate.categories, index, newIndex),
      }))
    }
  }

  const moveDown = () => {
    const newIndex = index + 1
    if (newIndex <= currentTemplate.categories.length - 1) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        categories: switchArrayIndex(prevTemplate.categories, index, newIndex),
      }))
    }
  }

  const deleteCategory = () => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      categories: prevTemplate.categories.filter(
        (category, indexCategory) => indexCategory !== index,
      ),
    }))
  }

  const isMoveUpDisabled = index === 0
  const isMoveDownDisabled = index === currentTemplate.categories.length - 1
  const isDeleteDisabled = currentTemplate.categories.length === 1

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
        Edit
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item quality-framework-columns-menu-item-moveup"
        onMouseUp={moveUp}
        disabled={isMoveUpDisabled}
        data-testid="menu-button-moveup"
      >
        <ChevronDown />
        Move up
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item"
        onMouseUp={moveDown}
        disabled={isMoveDownDisabled}
        data-testid="menu-button-movedown"
      >
        <ChevronDown />
        Move down
      </MenuButtonItem>
      <MenuButtonItem
        className="quality-framework-columns-menu-item"
        onMouseUp={deleteCategory}
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
      className={`row${isNotSaved ? ' quality-framework-not-saved' : ''}`}
      data-testid={`qf-category-row-${category.id}`}
    >
      <div className="label">
        <LabelWithTooltip tooltipTarget={portalTarget}>
          <span>{label}</span>
        </LabelWithTooltip>
        <LabelWithTooltip tooltipTarget={portalTarget}>
          <span className="details">{description && `(${description})`}</span>
        </LabelWithTooltip>
      </div>
      <div className="menu">{menu}</div>
      {isEditingName && (
        <ModifyCategory
          {...{
            target: ref.current,
            category,
            setIsEditingName,
          }}
        />
      )}
    </div>
  )
}

CategoryRow.propTypes = {
  category: PropTypes.object.isRequired,
  index: PropTypes.number.isRequired,
  shouldScrollIntoView: PropTypes.bool,
}
