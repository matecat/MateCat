import React, {useState} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import {getFilteredSegments} from '../../api/getFilteredSegments'
import SegmentActions from '../../actions/SegmentActions'
import ModalsActions from '../../actions/ModalsActions'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'
import {Checkbox, CHECKBOX_SIZE, CHECKBOX_STATE} from '../common/Checkbox'

export const HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE =
  'unlock-segments-modal' + config.id_job
export const UnlockAllSegmentsModal = () => {
  const [dontShowAgain, setDontShowAgain] = useState(false)
  const successCallback = () => {
    // currentPassword, not password: in a revision the job password names the translate phase, and
    // the server reads the phase off the credential it is given, so asking with it returns the locked
    // segments of a phase this editor is not on.
    getFilteredSegments(config.id_job, config.currentPassword, {
      sample: {type: 'locked'},
    }).then((data) => {
      SegmentActions.unlockSegments(data.segment_ids)
      SegmentStore.consecutiveUnlockSegments = []
    })
    checkboxCheck()
    ModalsActions.onCloseModal()
  }

  const checkboxCheck = () => {
    if (dontShowAgain) {
      localStorage.setItem(HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE, 1)
    }
  }

  return (
    <div className="message-modal">
      <div className="matecat-modal-middle">
        <div className={'modal-grid'}>
          <div className="modal-grid__body" style={{fontSize: '18px'}}>
            Would you like to unlock all locked segments?
          </div>
          <div className="modal-buttons">
            <Button
              mode={BUTTON_MODE.OUTLINE}
              onClick={() => {
                SegmentStore.consecutiveUnlockSegments = []
                checkboxCheck()
                ModalsActions.onCloseModal()
              }}
            >
              Cancel
            </Button>
            <Button type={BUTTON_TYPE.PRIMARY} onClick={successCallback}>
              Confirm
            </Button>
          </div>
          <div className="modal-grid__body">
            <Checkbox
              id="checkbox_unlock"
              label="Don't show this dialog again for the current job"
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
