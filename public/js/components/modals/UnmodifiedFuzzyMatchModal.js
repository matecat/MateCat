import React, {useRef} from 'react'
import ModalsActions from '../../actions/ModalsActions'
import {Button, BUTTON_TYPE} from '../common/Button/Button'

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
  const checkbox = useRef()

  const persistDontShowAgain = () => {
    if (checkbox.current?.checked) {
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
            You are confirming a fuzzy match without making any changes.
            Fuzzy matches usually need to be edited due to differences in the
            source text. Are you sure you want to proceed?
          </div>
          <div className="modal-grid__footer">
            <Button onClick={onCancel}>Cancel</Button>
            <Button type={BUTTON_TYPE.PRIMARY} onClick={onConfirm}>
              Confirm anyway
            </Button>
          </div>
          <div className="modal-grid__body">
            <input
              id="checkbox_unmodified_fuzzy"
              type="checkbox"
              ref={checkbox}
            />
            <label htmlFor="checkbox_unmodified_fuzzy">
              {` Don't show this again for this job`}
            </label>
          </div>
        </div>
      </div>
    </div>
  )
}

export default UnmodifiedFuzzyMatchModal

