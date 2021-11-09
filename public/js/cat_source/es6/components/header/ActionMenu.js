import React from 'react'
import _ from 'lodash'

import Icon3Dots from '../icons/Icon3Dots'
import {exportQualityReport} from '../../api/exportQualityReport'

class ActionMenu extends React.Component {
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
          <li className="item" title="Export CSV" data-value="export-csv">
            <span onClick={this.handlerExportCsv}>Export CSV</span>
          </li>
        </ul>
      </div>
    )
  }

  handlerExportCsv = () => {
    exportQualityReport()
      .then((blob) => {
        const file = window.URL.createObjectURL(blob)
        window.open(file, '_blank')
      })
      .catch((errors) => {
        const notification = {
          title: 'Error',
          text: `Downloading CSV error status code: ${errors.status}`,
          type: 'error',
        }
        APP.addNotification(notification)
      })
  }

  render = () => {
    const {getThreeDotsMenu} = this
    const threeDotsMenu = getThreeDotsMenu()

    return <div className={'action-menu qr-element'}>{threeDotsMenu}</div>
  }
}

ActionMenu.defaultProps = {}

export default ActionMenu
