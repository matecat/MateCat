import React from 'react'
import Cookies from 'js-cookie'
import ModalsActions from '../../actions/ModalsActions'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import {COPY_SOURCE_COOKIE} from '../../constants/ModalKeys'

class CopySourceModal extends React.Component {
  constructor(props) {
    super(props)
  }

  copyAllSources() {
    this.props.confirmCopyAllSources()
    this.checkCheckbox()
    ModalsActions.onCloseModal()
  }

  copySegmentOnly() {
    this.props.abortCopyAllSources()
    this.checkCheckbox()
    ModalsActions.onCloseModal()
  }

  checkCheckbox() {
    const checked = this.checkbox.checked
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

  render() {
    return (
      <div className="copy-source-modal">
        <div className="modal-grid">
          <div className="modal-grid__body">
            Do you really want to copy source to target for all new segments?
            <br />
            This action cannot be undone.
            <br />
            <br />
            Copy source to target for:
          </div>

          <div className="modal-grid__footer">
            <Button
              mode={BUTTON_MODE.OUTLINE}
              onClick={this.copyAllSources.bind(this)}
            >
              ALL new segments
            </Button>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              className="btn-ok"
              onClick={this.copySegmentOnly.bind(this)}
            >
              This segment only
            </Button>
            <div className="notes-action"></div>
          </div>
          <div className="modal-grid__body">
            <input
              id="copy_s2t_dont_show"
              type="checkbox"
              ref={(checkbox) => (this.checkbox = checkbox)}
              className="dont_show"
            />
            <label htmlFor="copy_s2t_dont_show">
              {` Don't show this dialog again for the current job`}
            </label>
          </div>
        </div>
      </div>
    )
  }
}

export default CopySourceModal
