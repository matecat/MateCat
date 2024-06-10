import React, {useEffect, useRef} from 'react'

export const ModalContainer = ({
  title,
  styleContainer,
  children,
  onClose,
  closeOnOutsideClick,
  isCloseButtonDisabled,
}) => {
  const ref = useRef(null)

  const handleClose = () => {
    onClose()
  }

  React.useEffect(() => {
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
        <div className="matecat-modal-header">
          <div className="modal-logo" />

          <div>
            <h2>{title}</h2>
          </div>
          {!isCloseButtonDisabled && (
            <div>
              <span
                className="close-matecat-modal x-popup"
                data-testid="close-button"
                onClick={handleClose}
              />
            </div>
          )}
        </div>

        <div className="matecat-modal-body">{children}</div>
      </div>
    </div>
  )
}
