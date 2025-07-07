import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import useExport, {EXPORT_TYPE} from './hooks/useExport'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'
import CatToolActions from '../../../../actions/CatToolActions'

export const ExportTMX = ({row, onClose}) => {
  const {email, status, onSubmit, onReset} = useExport({
    type: EXPORT_TYPE.tmx,
    row,
    onClose,
  })
  const [stripTags, setStripTags] = useState(false)

  const formRef = useRef()

  const isFormDisabled = false
  const isErrorExport = status && status.errors

  useEffect(() => {
    if (status && status.successfull) {
      const notification = {
        title: 'Request submitted',
        text: `You should receive the link at ${email}`,
        type: 'success',
        position: 'br',
        allowHtml: true,
        timer: 5000,
      }
      CatToolActions.addNotification(notification)
    }
  }, [status])

  return (
    <div className="translation-memory-glossary-tab-export">
      <form
        ref={formRef}
        className={`action-form${isErrorExport ? ' action-form-error' : ''}`}
        onSubmit={(e) => onSubmit(e, stripTags)}
        onReset={onReset}
      >
        <div className="translation-memory-glossary-tab-label">
          <span>
            We will send a link to download the exported TM to your email.
          </span>
          <div className="translation-memory-glossary-tab-checkbox">
            Export TM without tags
            <input
              name="tags"
              type="checkbox"
              onChange={(e) => setStripTags(e.currentTarget.checked)}
            ></input>
          </div>
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          <button
            type="submit"
            className="ui primary button settings-panel-button-icon confirm-button"
            disabled={isFormDisabled || isErrorExport}
          >
            <Checkmark size={12} />
            Confirm
          </button>

          <button
            type="reset"
            className="ui button orange close-button"
            disabled={isFormDisabled}
          >
            <Close size={18} />
          </button>
        </div>
      </form>
    </div>
  )
}

ExportTMX.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
