import React from 'react'
import PropTypes from 'prop-types'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'

export const DeleteResource = ({row, onClose, onConfirm}) => {
  const onClickConfirm = () => {
    onConfirm()
  }

  const onClickClose = () => {
    onClose()
  }

  return (
    <div className="translation-memory-glossary-tab-delete">
      <div className="action-form">
        <div>
          <span>
            Do you really want to delete this resource (<b>{row.name}</b>)
          </span>
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          <button
            className="ui primary button settings-panel-button-icon confirm-button"
            onClick={onClickConfirm}
          >
            <Checkmark size={12} />
            Confirm
          </button>

          <button
            className="ui button orange close-button"
            onClick={onClickClose}
          >
            <Close size={18} />
          </button>
        </div>
      </div>
    </div>
  )
}

DeleteResource.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
  onConfirm: PropTypes.func,
}
