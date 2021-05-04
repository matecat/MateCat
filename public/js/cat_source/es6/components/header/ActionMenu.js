import React from 'react'

import Icon3Dots from '../icons/Icon3Dots'

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
        </ul>
      </div>
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
