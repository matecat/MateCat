import React, {useRef} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import ModalsActions from '../../actions/ModalsActions'
import SearchUtils from '../header/cattol/search/searchUtils'
import CatToolActions from '../../actions/CatToolActions'
import AlertModal from './AlertModal'
import {Button, BUTTON_TYPE, BUTTON_MODE} from '../common/Button/Button'

export const ReplaceAllModal = ({search}) => {
  const checkbox = useRef()
  const successCallback = () => {
    SearchUtils.execReplaceAll(search, checkbox.current.checked)
      .then(() => {
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
            <input
              id="checkbox_unlock"
              type="checkbox"
              className=""
              ref={checkbox}
            />
            <label htmlFor="checkbox_unlock">
              {` Include locked segments`}
            </label>
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
