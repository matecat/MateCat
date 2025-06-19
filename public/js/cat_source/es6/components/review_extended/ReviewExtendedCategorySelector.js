import classNames from 'classnames'
import React from 'react'
import {
  DROPDOWN_MENU_ALIGN,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'

const ReviewExtendedCategorySelector = ({
  sendIssue,
  category,
  active,
  severityActiveIndex,
}) => {
  const onClickSeverity = (severity) => {
    if (severity) sendIssue(category, severity)
  }

  const containerClasses = classNames({
    're-item': true,
    're-category-item': true,
    'severity-buttons': category.severities.length > 0,
    active,
    classCatName: true,
  })

  const getSeverities = () => {
    if (category.severities.length > 7) {
      const items = category.severities.map((severity) => ({
        label: severity.label,
        onClick: () => onClickSeverity(severity.label),
      }))

      return (
        <DropdownMenu
          align={DROPDOWN_MENU_ALIGN.RIGHT}
          toggleButtonProps={{
            mode: BUTTON_MODE.OUTLINE,
            size: BUTTON_SIZE.ICON_SMALL,
            className: 'severities-dropdown-trigger',
            children: <i className="icon-sort-down icon" />,
          }}
          items={items}
        />
      )
    } else {
      return (
        <div
          className="re-severities-buttons ui tiny buttons"
          title="Select severities"
        >
          {category.severities.map((severity, i) => {
            const buttonClass = classNames({
              ui: true,
              attached: true,
              button: true,
              left: i === 0 && category.severities.length > 1,
              right:
                i === category.severities.length - 1 ||
                category.severities.length === 1,
              active: active && i === severityActiveIndex,
            })
            let label =
              category.severities.length === 1
                ? severity.label
                : severity.label.substring(0, 3)
            const sevName = severity.code ? severity.code : label
            return (
              <button
                key={'value-' + severity.label}
                onClick={() => onClickSeverity(severity.label)}
                className={'ui ' + buttonClass + ' attached button'}
                title={severity.label}
              >
                {sevName}
              </button>
            )
          })}
        </div>
      )
    }
  }

  return (
    <div className={containerClasses}>
      <div className="re-item-box re-error">
        <div className="error-name">{category.label}</div>
        <div className="error-level">{getSeverities()}</div>
      </div>
    </div>
  )
}

export default ReviewExtendedCategorySelector
