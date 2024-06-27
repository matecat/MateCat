import React from 'react'

import * as RadixDropdownMenu from '@radix-ui/react-dropdown-menu'
import PropTypes from 'prop-types'

import {Button, BUTTON_MODE, BUTTON_SIZE, BUTTON_TYPE} from '../Button/Button'

import ChevronDown from '../../../../../../img/icons/ChevronDown'
import Check from '../../../../../../img/icons/Check'
import DotsHorizontal from '../../../../../../img/icons/DotsHorizontal'

export const DROPDOWN_MENU_ALIGN = {
  LEFT: 'start',
  RIGHT: 'end',
  CENTER: 'center',
}
export const DROPDOWN_SEPARATOR = 'separator'
export const DROPDOWN_MENU_ITEM_TYPE = {
  DEFAULT: 'default',
  CRITICAL: 'critical',
}

export const DropdownMenu = ({
  className = '',
  dropdownClassName = '',
  toggleButtonProps = {},
  align = DROPDOWN_MENU_ALIGN.LEFT,
  onOpenChange,
  items = [],
  portalTarget,
}) => {
  const defaultToggleButtonProps = {
    type: BUTTON_TYPE.DEFAULT,
    mode: BUTTON_MODE.GHOST,
    size: BUTTON_SIZE.ICON_SMALL,
    children: <DotsHorizontal />,
    ...toggleButtonProps,
    className: `${toggleButtonProps.className || ''} ${className}`,
  }

  // FUNCTIONS
  const preventBubbling = (e) => {
    e.stopPropagation()
  }

  // RENDER
  const renderItem = (item, index, level = '1') => {
    if (item === DROPDOWN_SEPARATOR) {
      return (
        <RadixDropdownMenu.Separator
          key={`${level}-${index}`}
          className="dropdownmenu-separator"
        />
      )
    } else if (item.onChange) {
      // Radio group
      return (
        <RadixDropdownMenu.RadioGroup
          key={`${level}-${index}`}
          value={item.value}
          onValueChange={item.onChange}
        >
          {item.items.map((item, i) => renderItem(item, i, `2-${index}`))}
        </RadixDropdownMenu.RadioGroup>
      )
    } else if (item.value) {
      // Radio item
      return (
        <RadixDropdownMenu.RadioItem
          key={`${level}-${index}`}
          className={`dropdownmenu-item selectable ${item.type ? item.type : ''}`}
          onMouseDown={preventBubbling}
          onClick={preventBubbling}
          value={item.value}
          disabled={item.disabled}
          data-testid={item.testId}
          aria-label={item.tooltip}
        >
          {item.label}
          <RadixDropdownMenu.ItemIndicator className="dropdownmenu-indicator">
            <Check size={16} />
          </RadixDropdownMenu.ItemIndicator>
        </RadixDropdownMenu.RadioItem>
      )
    } else if (item.items && item.items.length > 0) {
      // Item with submenu
      return (
        <RadixDropdownMenu.Sub key={`${level}-${index}`}>
          <RadixDropdownMenu.SubTrigger
            className={`dropdownmenu-item subTrigger`}
            onMouseDown={preventBubbling}
            onClick={preventBubbling}
            data-testid={item.testId}
            aria-label={item.tooltip}
          >
            {item.label}
            <span className="dropdownmenu-subIcon">
              <ChevronDown size={10} />
            </span>
          </RadixDropdownMenu.SubTrigger>
          <RadixDropdownMenu.Portal container={portalTarget ?? document.body}>
            <RadixDropdownMenu.SubContent className="dropdownmenu subDropdown">
              {item.items.map((item, i) => renderItem(item, i, `2-${index}`))}
            </RadixDropdownMenu.SubContent>
          </RadixDropdownMenu.Portal>
        </RadixDropdownMenu.Sub>
      )
    } else {
      // Default item
      return (
        <RadixDropdownMenu.Item
          key={`${level}-${index}`}
          className={`dropdownmenu-item ${item.type ? item.type : ''} ${
            item.selected ? 'selected' : ''
          }`}
          onMouseDown={preventBubbling}
          onClick={preventBubbling}
          onSelect={item.onClick}
          disabled={item.disabled}
          data-testid={item.testId}
          aria-label={item.tooltip}
        >
          {item.label}
        </RadixDropdownMenu.Item>
      )
    }
  }

  return (
    <RadixDropdownMenu.Root onOpenChange={onOpenChange}>
      <RadixDropdownMenu.Trigger asChild>
        <Button {...defaultToggleButtonProps} />
      </RadixDropdownMenu.Trigger>

      <RadixDropdownMenu.Portal container={portalTarget ?? document.body}>
        <RadixDropdownMenu.Content
          align={align}
          className={`dropdownmenu ${dropdownClassName}`}
        >
          {items.map(renderItem)}
        </RadixDropdownMenu.Content>
      </RadixDropdownMenu.Portal>
    </RadixDropdownMenu.Root>
  )
}

const itemShape = {
  type: PropTypes.oneOf([...Object.values(DROPDOWN_MENU_ITEM_TYPE)]),
  onClick: PropTypes.func,
  link: PropTypes.oneOfType([PropTypes.string, PropTypes.object]),
  label: PropTypes.node,
  disabled: PropTypes.bool,
  testId: PropTypes.string,
  tooltip: PropTypes.string,
}
const radioItemShape = {
  type: PropTypes.oneOf([...Object.values(DROPDOWN_MENU_ITEM_TYPE)]),
  label: PropTypes.node,
  disabled: PropTypes.bool,
  testId: PropTypes.string,
  tooltip: PropTypes.string,
  value: PropTypes.string.isRequired,
}
const radioGroupShape = {
  value: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
  items: PropTypes.arrayOf(PropTypes.shape(radioItemShape)),
}
itemShape.items = PropTypes.arrayOf(
  PropTypes.oneOfType([
    PropTypes.oneOf([DROPDOWN_SEPARATOR]),
    PropTypes.shape(radioGroupShape),
    PropTypes.shape(itemShape),
  ]),
)
const itemPropTypes = PropTypes.arrayOf(
  PropTypes.oneOfType([
    PropTypes.oneOf([DROPDOWN_SEPARATOR]),
    PropTypes.shape(radioGroupShape),
    PropTypes.shape(itemShape),
  ]),
)

DropdownMenu.propTypes = {
  className: PropTypes.string,
  dropdownClassName: PropTypes.string,
  toggleButtonProps: PropTypes.shape({...Button.propTypes}),
  align: PropTypes.oneOf([...Object.values(DROPDOWN_MENU_ALIGN)]),
  onOpenChange: PropTypes.func,
  items: itemPropTypes,
  portalTarget: PropTypes.any,
}
