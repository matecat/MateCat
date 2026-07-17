import React, {useRef} from 'react'
import ModalsActions from '../../actions/ModalsActions'

export const HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE =
  'unmodified-fuzzy-match-modal' + config.id_job

/**
 * Warns the translator when a fuzzy TM match is about to be confirmed without
 * any modification. A "don't show again" checkbox persists the choice for the
 * current job (same pattern used for the ICE unlock and copy-source modals).
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
        <div className={'ui one column grid'}>
          <div className="column left aligned" style={{fontSize: '18px'}}>
            You are about to confirm a fuzzy match without making any changes.
            Fuzzy matches are not exact and usually need to be reviewed and
            edited. Are you sure you want to confirm it as is?
          </div>
          <div className="column right aligned">
            <div className="ui button cancel-button" onClick={onCancel}>
              Cancel
            </div>
            <div
              className="ui primary button right floated"
              onClick={onConfirm}
            >
              Confirm
            </div>
          </div>
          <div className="column left aligned">
            <input
              id="checkbox_unmodified_fuzzy"
              type="checkbox"
              ref={checkbox}
            />
            <label htmlFor="checkbox_unmodified_fuzzy">
              {` Don't show this dialog again for the current job`}
            </label>
          </div>
        </div>
      </div>
    </div>
  )
}

export default UnmodifiedFuzzyMatchModal

