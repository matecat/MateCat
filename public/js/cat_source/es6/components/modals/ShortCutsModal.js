import _ from 'lodash'
import React from 'react'

import Shortcuts from '../../utils/shortcuts'

class ShortCutsModal extends React.Component {
  constructor(props) {
    super(props)
  }

  handleKeyupFunction = (event) => {
    if (event.key === 'Escape') {
      this.props.onClose()
    }
  }

  componentDidMount() {
    document.addEventListener('keyup', this.handleKeyupFunction)
  }
  componentWillUnmount() {
    document.removeEventListener('keyup', this.handleKeyupFunction)
  }

  getShortcutsHtml() {
    let html = []
    let label = UI.isMac ? 'mac' : 'standard'
    _.each(Shortcuts, function (elem, c) {
      let events = []
      _.each(elem.events, function (item, z) {
        let keys = item.keystrokes[label].split('+')
        let keysHtml = []
        keys.forEach(function (key, i) {
          let html = <div key={key} className={'keys ' + key} />
          keysHtml.push(html)
          if (i < keys.length - 1) {
            keysHtml.push('+')
          }
        })
        let sh = (
          <div key={z} className="shortcut-item">
            <div className="shortcut-title">{item.label}</div>
            <div className="shortcut-keys">
              <div className="shortcuts mac">{keysHtml}</div>
            </div>
          </div>
        )
        events.push(sh)
      })
      if (elem.label) {
        let group = (
          <div key={'events' + c} className="shortcut-list">
            <h2>{elem.label}</h2>
            <div className="shortcut-item-list">{events}</div>
          </div>
        )
        html.push(group)
      }
    })
    return html
  }

  render() {
    let html = this.getShortcutsHtml()
    return (
      <div className="shortcuts-modal">
        <div className="matecat-modal-top"></div>
        <div className="matecat-modal-middle">{html}</div>
        <div className="matecat-modal-bottom"></div>
      </div>
    )
  }
}

export default ShortCutsModal
