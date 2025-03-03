import React from 'react'
import Cookies from 'js-cookie'
import ModalsActions from '../../actions/ModalsActions'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
export const COPY_SOURCE_COOKIE = 'source_copied_to_target'
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
            onClick={this.copyAllSources.bind(this)}
          >
            ALL new segments
          </Button>
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.BIG}
            className="btn-ok"
            onClick={this.copySegmentOnly.bind(this)}
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
            ref={(checkbox) => (this.checkbox = checkbox)}
          />
          <label htmlFor="copy_s2t_dont_show">
            {` Don't show this dialog again for the current job`}
          </label>
        </div>
      </div>
    )
  }
}

export default CopySourceModal
