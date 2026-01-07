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
            You can:
            <ul
              style={{
                'list-style': 'decimal',
                'margin-left': '40px',
                'padding-top': '20px',
              }}
            >
              <li>
                <b>Review issues and fix them</b> before download
              </li>
              {config.ownerIsMe && (
                <li>
                  <b>Download the target file as it is</b>
                </li>
              )}
              <li>
                <b>Download the target file with error markers</b> (in this
                version, segments with issues will have the source text instead
                of the translation, wrapped in <b>UNTRANSLATED_CONTENT</b>)
              </li>
            </ul>
            <br />
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
            {config.ownerIsMe && (
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
            )}

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
