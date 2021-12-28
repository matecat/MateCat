import React, {useEffect, useRef} from 'react'
import $ from 'jquery'

export const ModalContainer = ({title, styleContainer, children, onClose}) => {
  const ref = useRef(null)

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

  // prevent propagation keydown events
  useEffect(() => {
    if (!ref.current) return
    const refTag = ref.current
    const stopPropagation = (event) => event.stopPropagation()
    const preventDefault = (event) =>
      event.key === 'Tab' && event.preventDefault()
    refTag.addEventListener('keydown', stopPropagation)
    refTag.addEventListener('keydown', preventDefault)
    refTag.focus()

    return () => {
      refTag.removeEventListener('keydown', stopPropagation)
      refTag.removeEventListener('keydown', preventDefault)
    }
  }, [ref])

  return (
    <div
      ref={ref}
      tabIndex="0"
      id="matecat-modal"
      className="matecat-modal"
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
