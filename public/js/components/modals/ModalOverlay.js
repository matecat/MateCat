import React from 'react'
import $ from 'jquery'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import Close from '../../../img/icons/Close'

export const ModalOverlay = ({title, styleContainer, children, onClose}) => {
  const handleClose = (e) => {
    e.stopPropagation()

    if (onClose) {
      onClose()
    }
  }

  React.useEffect(() => {
    document.activeElement.blur()
  }, [])

  return (
    <div id="matecat-modal-overlay" className="matecat-modal-overlay">
      <div className="matecat-modal-content" style={styleContainer}>
        <div className="matecat-modal-header">
          <div className="modal-logo" />

          <div>
            <h2>{title}</h2>
          </div>
          <Button
            type={BUTTON_TYPE.ICON}
            size={BUTTON_SIZE.ICON_STANDARD}
            mode={BUTTON_MODE.GHOST}
            onClick={handleClose}
            data-testid="close-button"
          >
            <Close size={20} />
          </Button>
        </div>

        <div className="matecat-modal-body">{children}</div>
      </div>
    </div>
  )
}
