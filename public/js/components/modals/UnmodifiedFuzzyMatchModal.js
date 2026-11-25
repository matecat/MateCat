import React, {useState} from 'react'
import ModalsActions from '../../actions/ModalsActions'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'
import {Checkbox, CHECKBOX_SIZE, CHECKBOX_STATE} from '../common/Checkbox'

export const HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE =
  'unmodified-fuzzy-match-modal' + config.id_job + '-' + config.userMail

/**
 * Warns the translator when a fuzzy TM match is about to be confirmed without
 * any modification. A "don't show again" checkbox persists the choice for the
 * current job and user (same pattern used for the ICE unlock and copy-source
 * modals, scoped per user like JobMetadata's instructions popup).
 */
export const UnmodifiedFuzzyMatchModal = ({
  successCallback,
  cancelCallback,
}) => {
  const [dontShowAgain, setDontShowAgain] = useState(false)

  const persistDontShowAgain = () => {
    if (dontShowAgain) {
      localStorage.setItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE, 1)
    }
  }

  const onConfirm = () => {
    persistDontShowAgain()
    ModalsActions.onCloseModal()
    successCallback?.()
  }

  const onCancel = () => {
    persistDontShowAgain()
    ModalsActions.onCloseModal()
    cancelCallback?.()
  }

  return (
    <div className="message-modal">
      <div className="matecat-modal-middle">
        <div className="modal-grid">
          <div className="modal-grid__body">
            You are confirming a fuzzy match without making any changes. Fuzzy
            matches usually need to be edited due to differences in the source
            text. Are you sure you want to proceed?
          </div>
          <div className="modal-grid__footer">
            <Button mode={BUTTON_MODE.OUTLINE} onClick={onCancel}>
              Cancel
            </Button>
            <Button type={BUTTON_TYPE.PRIMARY} onClick={onConfirm}>
              Confirm anyway
            </Button>
          </div>
          <div className="modal-grid__body">
            <Checkbox
              id="checkbox_unmodified_fuzzy"
              label="Don't show this again for this job"
              value={
                dontShowAgain
                  ? CHECKBOX_STATE.CHECKED
                  : CHECKBOX_STATE.UNCHECKED
              }
              size={CHECKBOX_SIZE.SMALL}
              onChange={(isChecked) => setDontShowAgain(isChecked)}
            />
          </div>
        </div>
      </div>
    </div>
  )
}

export default UnmodifiedFuzzyMatchModal
