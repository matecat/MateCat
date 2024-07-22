import React from 'react'
import PropTypes from 'prop-types'
import {TOOLTIP_POSITION} from '../Tooltip'

const mergeClassNames = (...args) => {
  return (
    Array.prototype.slice
      // eslint-disable-next-line no-undef
      .call(args)
      .reduce(
        (classList, arg) =>
          typeof arg === 'string' || Array.isArray(arg)
            ? classList.concat(arg)
            : classList,
        [],
      )
      .filter(Boolean)
      .join(' ')
  )
}

export const BUTTON_TYPE = {
  DEFAULT: 'default',
  PRIMARY: 'primary',
  INFO: 'info',
  SUCCESS: 'success',
  WARNING: 'warning',
  CRITICAL: 'critical',
}
export const BUTTON_MODE = {
  BASIC: 'basic',
  OUTLINE: 'outline',
  GHOST: 'ghost',
}
export const BUTTON_SIZE = {
  SMALL: 'small',
  STANDARD: 'standard',
  MEDIUM: 'medium',
  BIG: 'big',
  ICON_SMALL: 'iconSmall',
  ICON_STANDARD: 'iconStandard',
  ICON_BIG: 'iconBig',
}
export const BUTTON_HTML_TYPE = {
  BUTTON: 'button',
  SUBMIT: 'submit',
  RESET: 'reset',
}

export const Button = React.forwardRef(
  (
    {
      type = BUTTON_TYPE.DEFAULT,
      mode = BUTTON_MODE.BASIC,
      size = BUTTON_SIZE.STANDARD,
      fullWidth = false,
      disabled = false,
      active = false,
      waiting = false,
      children,
      tabIndex = '-1',
      htmlType = BUTTON_HTML_TYPE.BUTTON,
      form,
      tooltip,
      tooltipPosition,
      onClick = () => {},
      testId,
      className = '',
      ...props
    },
    ref,
  ) => {
    const defaultClassName = `button-component-container ${type} ${mode} ${size}`
    const fullWidthClassName = fullWidth ? 'fullWidth' : ''
    const activeClassName = active ? 'button--active' : ''
    const waitingClassName = waiting ? 'waiting' : ''
    const buttonClassName = mergeClassNames(
      defaultClassName,
      fullWidthClassName,
      activeClassName,
      waitingClassName,
      className,
    )
    const style = {}
    if (typeof size === 'number') {
      style.width = size
      style.height = size
      style.lineHeight = size
    }

    return (
      <button
        ref={ref}
        form={form}
        type={htmlType}
        className={buttonClassName}
        style={style}
        disabled={disabled || waiting}
        tabIndex={tabIndex}
        aria-label={tooltip}
        tooltip-position={tooltipPosition}
        // eslint-disable-next-line react/no-unknown-property
        onClick={onClick}
        data-testid={testId}
        {...props}
      >
        {waiting ? (
          <>
            <span className={styles.hiddenContent}>{children}</span>
            {/* <Spinner
                        className={styles.spinner}
                        size={
                            size === BUTTON_SIZE.ICON_SMALL
                                ? 16
                                : size === BUTTON_SIZE.BIG || size === BUTTON_SIZE.ICON_BIG
                                  ? 24
                                  : 20
                        }
                    /> */}
          </>
        ) : (
          children
        )}
      </button>
    )
  },
)

Button.displayName = 'Button'

Button.propTypes = {
  type: PropTypes.oneOf([...Object.values(BUTTON_TYPE)]),
  mode: PropTypes.oneOf([...Object.values(BUTTON_MODE)]),
  size: PropTypes.oneOfType([
    PropTypes.number,
    PropTypes.oneOf([...Object.values(BUTTON_SIZE)]),
  ]),
  fullWidth: PropTypes.bool,
  disabled: PropTypes.bool,
  active: PropTypes.bool,
  waiting: PropTypes.bool,
  children: PropTypes.node,
  tabIndex: PropTypes.string,
  htmlType: PropTypes.oneOf([...Object.values(BUTTON_HTML_TYPE)]),
  form: PropTypes.string,
  tooltip: PropTypes.string,
  tooltipPosition: PropTypes.oneOf([...Object.values(TOOLTIP_POSITION)]),
  onClick: PropTypes.func,
  testId: PropTypes.string,
  className: PropTypes.string,
}
