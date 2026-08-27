import React, {useEffect, useRef} from 'react'
import Close from '../../../img/icons/Close'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'

export const ModalContainer = ({
  title,
  styleContainer,
  children,
  onClose,
  closeOnOutsideClick,
  showHeader,
  styleBody,
  isCloseButtonDisabled,
}) => {
  const ref = useRef(null)

  const handleClose = () => {
    onClose()
  }

  useEffect(() => {
    document.activeElement.blur()
  }, [])

  const onKeyDownHandler = (event) => {
    event.stopPropagation()
    if (event.key === 'Tab') {
      let focusable = document
        .querySelector('#matecat-modal')
        .querySelectorAll('input,button,select,textarea')
      if (focusable.length) {
        let first = focusable[0]
        let last = focusable[focusable.length - 1]
        let shift = event.shiftKey
        if (shift) {
          if (event.target === first) {
            // shift-tab pressed on first input in dialog
            last.focus()
            event.preventDefault()
          }
        } else {
          if (event.target === last) {
            // tab pressed on last input in dialog
            first.focus()
            event.preventDefault()
          }
        }
      }
    }
  }

  return (
    <div
      ref={ref}
      tabIndex="0"
      id="matecat-modal"
      className="matecat-modal"
      onKeyDown={onKeyDownHandler}
    >
      <div
        className="matecat-modal-background"
        onClick={() => {
          if (closeOnOutsideClick && !isCloseButtonDisabled) {
            handleClose()
          }
        }}
      />
      <div className="matecat-modal-content" style={styleContainer}>
        {showHeader && (
          <div className="matecat-modal-header">
            <div className="modal-logo" />

            <div>
              <h2>{title}</h2>
            </div>
            {!isCloseButtonDisabled && (
              <Button
                type={BUTTON_TYPE.ICON}
                size={BUTTON_SIZE.ICON_STANDARD}
                mode={BUTTON_MODE.GHOST}
                onClick={handleClose}
                data-testid="close-button"
              >
                <Close size={20} />
              </Button>
            )}
          </div>
        )}

        <div className="matecat-modal-body" style={styleBody}>
          {children}
        </div>
      </div>
    </div>
  )
}
