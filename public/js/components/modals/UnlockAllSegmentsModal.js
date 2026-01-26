import React, {useRef} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import {getFilteredSegments} from '../../api/getFilteredSegments'
import SegmentActions from '../../actions/SegmentActions'
import ModalsActions from '../../actions/ModalsActions'
import {Button, BUTTON_TYPE} from '../common/Button/Button'

export const HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE =
  'unlock-segments-modal' + config.id_job
export const UnlockAllSegmentsModal = () => {
  const checkbox = useRef()
  const successCallback = () => {
    getFilteredSegments(config.id_job, config.password, {
      sample: {type: 'ice'},
    }).then((data) => {
      SegmentActions.unlockSegments(data.segment_ids)
      SegmentStore.consecutiveUnlockSegments = []
    })
    checkboxCheck()
    ModalsActions.onCloseModal()
  }

  const checkboxCheck = () => {
    if (checkbox.current.checked) {
      localStorage.setItem(HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE, 1)
    }
  }

  return (
    <div className="message-modal">
      <div className="matecat-modal-middle">
        <div className={'ui one column grid'}>
          <div className="column left aligned" style={{fontSize: '18px'}}>
            Would you like to unlock all 101% segments?
          </div>
          <div className="modal-buttons">
            <Button
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
          <div className="column left aligned">
            <input
              id="checkbox_unlock"
              type="checkbox"
              className=""
              ref={checkbox}
            />
            <label htmlFor="checkbox_unlock">
              {` Don't show this dialog again for the current job`}
            </label>
          </div>
        </div>
      </div>
    </div>
  )
}
