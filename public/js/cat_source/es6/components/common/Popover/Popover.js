import React, {useState, useRef} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_MODE, BUTTON_SIZE, BUTTON_TYPE} from '../Button/Button'

export const POPOVER_ALIGN = {
  LEFT: 'left',
  CENTER: 'center',
  RIGHT: 'right',
}
export const POPOVER_VERTICAL_ALIGN = {
  TOP: 'top',
  BOTTOM: 'bottom',
}
export const POPOVER_TOGGLE = {
  DEFAULT: 'default',
  UNSTYLED: 'unstyled',
}

export const Popover = ({
  className = '',
  contentClassName = '',
  title = '',
  toggleButtonVariant = POPOVER_TOGGLE.DEFAULT,
  toggleButtonProps,
  confirmButtonProps,
  cancelButtonProps,
  align = POPOVER_ALIGN.LEFT,
  verticalAlign = POPOVER_VERTICAL_ALIGN.BOTTOM,
  onClose = () => {},
  children,
}) => {
  const [isOpen, setIsOpen] = useState(false)

  const containerRef = useRef()

  const defaultToggleButtonProps = {
    mode: BUTTON_MODE.OUTLINE,
    size: BUTTON_SIZE.ICON_STANDARD,
    disabled: false,
    ...toggleButtonProps,
  }

  // FUNCTIONS
  const closePopover = (e) => {
    e.stopPropagation()
    if (containerRef.current && !containerRef.current.contains(e.target)) {
      window.removeEventListener('mousedown.popover', closePopover)
      onClose()
      setIsOpen(false)
    }
  }
  const togglePopover = (e) => {
    e.stopPropagation()
    e.preventDefault()
    if (isOpen) {
      window.removeEventListener('mousedown.popover', closePopover)
      onClose()
    } else {
      document.dispatchEvent(new Event('mousedown.popover')) // close other popovers
      window.addEventListener('mousedown.popover', closePopover)
    }
    setIsOpen(!isOpen)
  }
  const handleConfirm = (e) => {
    // eslint-disable-next-line react/prop-types
    if (confirmButtonProps?.onClick) confirmButtonProps.onClick()
    togglePopover(e)
  }
  const handleCancel = (e) => {
    // eslint-disable-next-line react/prop-types
    if (cancelButtonProps?.onClick) cancelButtonProps.onClick()
    togglePopover(e)
  }

  // RENDER
  const defaultConfirmButtonProps = confirmButtonProps
    ? {
        type: BUTTON_TYPE.PRIMARY,
        children: 'Confirm',
        ...confirmButtonProps,
        onClick: handleConfirm,
      }
    : undefined
  const defaultCancelButtonProps = cancelButtonProps
    ? {
        mode: BUTTON_MODE.GHOST,
        children: 'Cancel',
        ...cancelButtonProps,
        onClick: handleCancel,
      }
    : undefined

  return (
    <div
      ref={containerRef}
      className="popover-component-container"
      data-testid="popover-container"
    >
      {toggleButtonVariant === POPOVER_TOGGLE.DEFAULT ? (
        <Button
          active={isOpen}
          onClick={togglePopover}
          {...defaultToggleButtonProps}
        />
      ) : (
        <button
          type="button"
          tabIndex="-1"
          className={`popover-component-toggle ${isOpen ? 'popover__toggle--active' : ''}`}
          onClick={togglePopover}
          aria-label={defaultToggleButtonProps.tooltip}
          // eslint-disable-next-line react/no-unknown-property
          tooltip-position={defaultToggleButtonProps.tooltipPosition}
        >
          {defaultToggleButtonProps.children}
        </button>
      )}
      {isOpen && (
        <div
          className={`popover-component-popover popover-component-${align} popover-component-${verticalAlign} ${className}`}
          data-testid="popover"
          onKeyDown={(event) => event.key === 'Escape' && handleCancel(event)}
        >
          {title && (
            <div className="popover-component-header">
              <span className="popover-component-title">{title}</span>
            </div>
          )}
          <div className={`popover-component-body ${contentClassName}`}>
            {children}
          </div>
          {(cancelButtonProps || confirmButtonProps) && (
            <div className="popover-component-actions">
              {cancelButtonProps && <Button {...defaultCancelButtonProps} />}
              {confirmButtonProps && <Button {...defaultConfirmButtonProps} />}
            </div>
          )}
        </div>
      )}
    </div>
  )
}

Popover.propTypes = {
  className: PropTypes.string,
  contentClassName: PropTypes.string,
  title: PropTypes.any,
  toggleButtonVariant: PropTypes.oneOf([...Object.values(POPOVER_TOGGLE)]),
  toggleButtonProps: PropTypes.shape({...Button.propTypes}),
  confirmButtonProps: PropTypes.shape({...Button.propTypes}),
  cancelButtonProps: PropTypes.shape({...Button.propTypes}),
  align: PropTypes.oneOf([...Object.values(POPOVER_ALIGN)]),
  verticalAlign: PropTypes.oneOf([...Object.values(POPOVER_VERTICAL_ALIGN)]),
  onClose: PropTypes.func,
  children: PropTypes.node,
}
