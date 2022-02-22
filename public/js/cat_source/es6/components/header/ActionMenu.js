import React from 'react'
import _ from 'lodash'

import Icon3Dots from '../icons/Icon3Dots'
import {exportQualityReport} from '../../api/exportQualityReport'
import CatToolActions from '../../actions/CatToolActions'

class ActionMenu extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      isExportCsvDisabled: false,
    }
  }

  componentDidMount() {
    this.initDropdowns()
  }

  initDropdowns = () => {
    // 3Dots
    if (!_.isUndefined(this.dropdownThreeDots)) {
      let dropdownThreeDots = $(this.dropdownThreeDots)
      dropdownThreeDots.dropdown()
    }
  }

  getTranslateUrl() {}

  getThreeDotsMenu = () => {
    const {jobUrls} = this.props
    return (
      <div
        className={'action-submenu ui pointing top center floating dropdown'}
        id={'action-three-dots'}
        ref={(dropdownThreeDots) =>
          (this.dropdownThreeDots = dropdownThreeDots)
        }
      >
        <Icon3Dots />
        <ul className="menu">
          <li className="item" title="Revise" data-value="revise">
            <a href={jobUrls.revise_urls[0].url}>Revise</a>
          </li>
          <li className="item" title="Translate" data-value="translate">
            <a href={jobUrls.translate_url}>Translate</a>
          </li>
          <li
            className={`item${
              this.state.isExportCsvDisabled ? ' disabled' : ''
            }`}
            title="Export CSV"
            data-value="export-csv"
          >
            <span
              onClick={
                !this.state.isExportCsvDisabled
                  ? this.handlerExportCsv
                  : () => {}
              }
            >
              Download QA Report CSV
            </span>
          </li>
        </ul>
      </div>
    )
  }

  handlerExportCsv = () => {
    this.setState({
      isExportCsvDisabled: true,
    })
    exportQualityReport()
      .then(({blob, filename}) => {
        const aTag = document.createElement('a')
        const blobURL = URL.createObjectURL(blob)
        aTag.download = filename
        aTag.href = blobURL
        document.body.appendChild(aTag)
        aTag.click()
        document.body.removeChild(aTag)
      })
      .catch((errors) => {
        const notification = {
          title: 'Error',
          text: `Downloading CSV error status code: ${errors.status}`,
          type: 'error',
        }
        CatToolActions.addNotification(notification)
      })
      .finally(() =>
        this.setState({
          isExportCsvDisabled: false,
        }),
      )
  }

  render = () => {
    const {getThreeDotsMenu} = this
    const threeDotsMenu = getThreeDotsMenu()

    return <div className={'action-menu qr-element'}>{threeDotsMenu}</div>
  }
}

ActionMenu.defaultProps = {}

export default ActionMenu
