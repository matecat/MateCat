import React from 'react'
import $ from 'jquery'

export const ModalContainer = ({title, styleContainer, children, onClose}) => {
  const handleClose = (event) => {
    event.stopPropagation()

    if (
      $(event.target).closest('.matecat-modal-content').length == 0 ||
      $(event.target).hasClass('close-matecat-modal')
    ) {
      onClose()
    }
  }

  React.useEffect(() => {
    $('body').addClass('side-popup')
    document.activeElement.blur()

    return () => {
      $('body').removeClass('side-popup')
    }
  }, [])

  return (
    <div id="matecat-modal" className="matecat-modal" onClick={handleClose}>
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
