import React, {useRef} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import ModalsActions from '../../actions/ModalsActions'
import SearchUtils from '../header/cattol/search/searchUtils'
import CatToolActions from '../../actions/CatToolActions'
import AlertModal from './AlertModal'

export const HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE =
  'unlock-segments-modal' + config.id_job
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
            You are about to replace this text in all search results.
            <br />
            To let you easily review these changes, modified segments will
            revert to <b>{config.isReview ? 'translated' : 'draft'}</b> status.
          </div>
          <div className="column left aligned">
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
          <div className="column right aligned">
            <div
              className="ui button cancel-button"
              onClick={() => {
                ModalsActions.onCloseModal()
              }}
            >
              Cancel
            </div>
            <div
              className="ui primary button right floated"
              onClick={successCallback}
            >
              Replace all
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
