import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import IconDown from '../../../icons/IconDown'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../img/icons/Trash'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {switchArrayIndex} from '../../../../utils/commonUtils'
import LabelWithTooltip from '../../../common/LabelWithTooltip'
import {ModifyCategory} from './ModifyCategory'
import {getCategoryLabelAndDescription} from './CategoriesSeveritiesTable'
import ChevronDown from '../../../../../img/icons/ChevronDown'
import {DropdownMenu} from '../../../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE} from '../../../common/Button/Button'

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
    <DropdownMenu
      dropdownClassName="settings-panel-dropdownMenu"
      toggleButtonProps={{
        className: 'quality-framework-columns-menu-button',
        mode: BUTTON_MODE.GHOST,
        testId: 'qf-category-menu',
        children: (
          <>
            <IconDown size={20} />
          </>
        ),
      }}
      items={[
        {
          label: (
            <>
              <IconEdit size={18} />
              Edit
            </>
          ),
          onClick: () => setIsEditingName(true),
          testId: 'menu-button-rename',
        },
        {
          label: (
            <>
              <div className="quality-framework-columns-menu-item-moveup">
                <ChevronDown />
              </div>
              Move up
            </>
          ),
          disabled: isMoveUpDisabled,
          onClick: moveUp,
          testId: 'menu-button-moveup',
        },
        {
          label: (
            <>
              <ChevronDown />
              Move down
            </>
          ),
          disabled: isMoveDownDisabled,
          onClick: moveDown,
          testId: 'menu-button-movedown',
        },
        {
          label: (
            <>
              <Trash size={18} />
              Delete
            </>
          ),
          disabled: isDeleteDisabled,
          onClick: deleteCategory,
          testId: 'menu-button-delete',
        },
      ]}
    />
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
