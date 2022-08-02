import React from 'react'
import Cookies from 'js-cookie'
import ModalsActions from '../../actions/ModalsActions'

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
    var checked = this.checkbox.checked
    if (checked) {
      Cookies.set(
        'source_copied_to_target-' + config.id_job + '-' + config.password,
        '0',
        //expiration: 1 day
        {expires: 30, secure: true},
      )
    } else {
      Cookies.set(
        'source_copied_to_target-' + config.id_job + '-' + config.password,
        null,
        //set expiration date before the current date to delete the cookie
        {expires: new Date(1), secure: true},
      )
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
          <a className="btn-cancel" onClick={this.copyAllSources.bind(this)}>
            ALL new segments
          </a>
          <a className="btn-ok" onClick={this.copySegmentOnly.bind(this)}>
            This segment only
          </a>
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
