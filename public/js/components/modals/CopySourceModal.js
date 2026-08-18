import React, {useRef} from 'react'
import Cookies from 'js-cookie'
import ModalsActions from '../../actions/ModalsActions'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import {COPY_SOURCE_COOKIE} from '../../constants/ModalKeys'

const CopySourceModal = ({confirmCopyAllSources, abortCopyAllSources}) => {
  const checkboxRef = useRef(null)

  const checkCheckbox = () => {
    const checked = checkboxRef.current.checked
    if (checked) {
      sessionStorage.setItem(COPY_SOURCE_COOKIE, 0)
      Cookies.set(
        COPY_SOURCE_COOKIE,
        '0',
        //expiration: 1 day
        {expires: 1, secure: true},
      )
    } else {
      sessionStorage.removeItem(COPY_SOURCE_COOKIE)
    }
  }

  const copyAllSources = () => {
    confirmCopyAllSources()
    checkCheckbox()
    ModalsActions.onCloseModal()
  }

  const copySegmentOnly = () => {
    abortCopyAllSources()
    checkCheckbox()
    ModalsActions.onCloseModal()
  }

  return (
    <div className="copy-source-modal">
      <h3 className="text-container-top">
        Do you really want to copy source to target for all new segments?
        <br />
        This action cannot be undone.
      </h3>

      <div className="buttons-popup-container">
        <label>Copy source to target for:</label>
        <Button
          mode={BUTTON_MODE.OUTLINE}
          size={BUTTON_SIZE.BIG}
          onClick={copyAllSources}
        >
          ALL new segments
        </Button>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.BIG}
          className="btn-ok"
          onClick={copySegmentOnly}
        >
          This segment only
        </Button>
        <div className="notes-action"></div>
      </div>
      <div className="boxed">
        <input
          id="copy_s2t_dont_show"
          type="checkbox"
          className="dont_show"
          ref={checkboxRef}
        />
        <label htmlFor="copy_s2t_dont_show">
          {` Don't show this dialog again for the current job`}
        </label>
      </div>
    </div>
  )
}

export default CopySourceModal
