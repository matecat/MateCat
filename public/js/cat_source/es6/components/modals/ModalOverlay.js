import React from 'react'
import $ from 'jquery'

export const ModalOverlay = ({title, styleContainer, children, onClose}) => {
  const handleClose = (e) => {
    e.stopPropagation()

    if (
      $(e.target).closest('.matecat-modal-content').length == 0 ||
      $(e.target).hasClass('close-matecat-modal')
    ) {
      onClose()
    }
  }

  React.useEffect(() => {
    document.activeElement.blur()
  }, [])

  return (
    <div
      id="matecat-modal-overlay"
      className="matecat-modal-overlay"
      onClick={handleClose}
    >
      <div className="matecat-modal-content" style={styleContainer}>
        <div className="matecat-modal-header">
          <div className="modal-logo" />

          <div>
            <h2>{title}</h2>
          </div>

          <div>
            <span
              className="close-matecat-modal x-popup"
              data-testid="close-button"
              onClick={handleClose}
            />
          </div>
        </div>

        <div className="matecat-modal-body">{children}</div>
      </div>
    </div>
  )
}
