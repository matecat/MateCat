import React, {useState} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import ModalsActions from '../../actions/ModalsActions'
import SearchUtils from '../header/cattol/search/searchUtils'
import CatToolActions from '../../actions/CatToolActions'
import AlertModal from './AlertModal'
import {Button, BUTTON_TYPE, BUTTON_MODE} from '../common/Button/Button'
import {Checkbox, CHECKBOX_SIZE, CHECKBOX_STATE} from '../common/Checkbox'

export const ReplaceAllModal = ({search}) => {
  const [includeLockedSegments, setIncludeLockedSegments] = useState(false)
  const successCallback = () => {
    const request = SearchUtils.execReplaceAll(search, includeLockedSegments)
    // A missing target or replacement returns false: it has already shown
    // its own alert and sent no request
    if (!request) return
    request
      .then((response) => {
        // The unlock lives in localStorage; the reload below rebuilds
        // segment.unlocked from it, so it has to be written first.
        const replacedLocked = (response?.replaced_locked_segments ?? []).map(
          String,
        )
        if (replacedLocked.length > 0) {
          SegmentActions.unlockSegments(replacedLocked)
        }
        const currentId = SegmentStore.getCurrentSegmentId()
        SegmentActions.removeAllSegments()
        CatToolActions.onRender({
          firstLoad: false,
          segmentToOpen: currentId,
        })
      })
      .catch((errors) => {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: errors?.length
              ? errors[0].message
              : 'We got an error, please contact support',
          },
          'Replace all alert',
        )
      })
    ModalsActions.onCloseModal()
    CatToolActions.storeSearchResults({
      total: 0,
      searchResults: [],
      occurrencesList: [],
      searchResultsDictionary: {},
      featuredSearchResult: null,
    })
  }

  return (
    <div className="message-modal">
      <div className="matecat-modal-middle">
        <div className="modal-grid">
          <div className="modal-grid__body" style={{fontSize: '18px'}}>
            You are about to replace this text in all search results.
            <br />
            To let you easily review these changes, modified segments will
            revert to <b>{config.isReview ? 'translated' : 'draft'}</b> status.
          </div>
          <div className="modal-grid__body">
            <Checkbox
              id="checkbox_unlock"
              label="Include locked segments"
              value={
                includeLockedSegments
                  ? CHECKBOX_STATE.CHECKED
                  : CHECKBOX_STATE.UNCHECKED
              }
              size={CHECKBOX_SIZE.SMALL}
              onChange={(isChecked) => setIncludeLockedSegments(isChecked)}
            />
          </div>
          <div className="modal-buttons">
            <Button
              mode={BUTTON_MODE.OUTLINE}
              onClick={() => ModalsActions.onCloseModal()}
            >
              Cancel
            </Button>
            <Button type={BUTTON_TYPE.PRIMARY} onClick={successCallback}>
              Replace all
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
