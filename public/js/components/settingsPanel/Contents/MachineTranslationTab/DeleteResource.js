import React from 'react'
import PropTypes from 'prop-types'

import Checkmark from '../../../../../img/icons/Checkmark'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../../../common/Button/Button'
import IconClose from '../../../../../img/icons/IconClose'

export const DeleteResource = ({row, onClose, onConfirm, type = 'mt'}) => {
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
            Do you really want to delete the
            {type === 'mt' ? ' MT' : ' Glossary'}: (<b>{row.name}</b>)
          </span>
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.SMALL}
            onClick={onClickConfirm}
          >
            <Checkmark size={16} />
            Confirm
          </Button>

          <Button
            type={BUTTON_TYPE.WARNING}
            size={BUTTON_SIZE.ICON_SMALL}
            onClick={onClickClose}
          >
            <IconClose size={16} />
          </Button>
        </div>
      </div>
    </div>
  )
}

DeleteResource.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
  type: PropTypes.oneOf(['mt', 'glossary']),
}
