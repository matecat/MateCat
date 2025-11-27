import React from 'react'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import ModalsActions from '../../actions/ModalsActions'

export const DownloadAlertModal = ({
  successCallback,
  successCallbackWithoutErrors,
  cancelCallback,
}) => {
  return (
    <div className="message-modal">
      <div className="matecat-modal-middle">
        <div className={'ui one column grid '}>
          <div className="column left aligned" style={{fontSize: '18px'}}>
            The translation has issues (e.g. missing tags or over‑length text)
            that may cause failed downloads, missing placeholders, or lost
            formatting.
            <br />
            <br />
            You can <b>review issues, download the target file</b> as it is, or{' '}
            <b>download with error markers</b> (in this version, affected
            segments will show the source text instead of the translation,
            wrapped in <b>UNTRANSLATED_CONTENT</b>).
          </div>
          <div className="matecat-modal-buttons">
            <Button
              type={BUTTON_TYPE.DEFAULT}
              mode={BUTTON_MODE.OUTLINE}
              size={BUTTON_SIZE.MEDIUM}
              onClick={() => {
                ModalsActions.onCloseModal()
                cancelCallback()
              }}
            >
              Fix issues
            </Button>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              size={BUTTON_SIZE.MEDIUM}
              onClick={() => {
                ModalsActions.onCloseModal()
                successCallbackWithoutErrors()
              }}
            >
              Download
            </Button>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              size={BUTTON_SIZE.MEDIUM}
              onClick={() => {
                ModalsActions.onCloseModal()
                successCallback()
              }}
            >
              Download with markers
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
