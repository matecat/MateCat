import React, {useRef} from 'react'
import PropTypes from 'prop-types'
import useExport, {EXPORT_TYPE} from './hooks/useExport'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'

export const ExportGlossary = ({row, onClose}) => {
  const {email, status, onSubmit, onReset, onChange} = useExport({
    type: EXPORT_TYPE.glossary,
    row,
    onClose,
  })

  const formRef = useRef()

  const isFormDisabled = false
  const isErrorExport = status && status.errors
  const isSuccessfullExport = status && status.successfull

  return (
    <div className="translation-memory-glossary-tab-export">
      {!isSuccessfullExport ? (
        <form
          ref={formRef}
          className={`action-form${isErrorExport ? ' action-form-error' : ''}`}
          onSubmit={onSubmit}
          onReset={onReset}
        >
          <div>
            <span>
              We will send a link to download the exported Glossary to your
              email.
            </span>
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
      ) : (
        <div className="export-successfull">
          <span>You should receive the link at {email}</span>
          <span>Request submitted</span>
        </div>
      )}
    </div>
  )
}

ExportGlossary.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
