import React from 'react'
import _ from 'lodash'

import Shortcuts from '../../utils/shortcuts'
import {getXliffRegExpression} from './utils/DraftMatecatUtils/tagModel'
import SegmentWrapper from './SegmentWrapper'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'

class SegmentBody extends React.Component {
  static contextType = SegmentContext

  constructor(props, context) {
    super(props)
    this.openStatusSegmentMenu = this.openStatusSegmentMenu.bind(this)
    this.handleClickOutside = this.handleClickOutside.bind(this)
    this.state = {
      showStatusMenu: false,
      clickedTagId: null,
      clickedTagText: null,
      tagClickedInSource: false,
    }
    this.isReviewExtended = context.reviewType === 'extended'
  }

  statusHandleTitleAttr(status) {
    status = status.toUpperCase()
    return (
      status.charAt(0) + status.slice(1).toLowerCase() + ', click to change it'
    )
  }

  checkLockTags() {
    if (config.tagLockCustomizable || !UI.tagLockEnabled) {
      return false
    } else return this.context.segment.segment.match(/&lt;.*?&gt;/gi)
  }

  openStatusSegmentMenu(e) {
    e.preventDefault()
    this.setState({
      showStatusMenu: !this.state.showStatusMenu,
    })
  }

  changeStatus(status) {
    UI.changeStatus(this.context.segment, status)
    this.setState({
      showStatusMenu: false,
    })
  }

  /**
   * Alert if clicked on outside of element
   */
  handleClickOutside(event) {
    if (this.statusMenuRef && !this.statusMenuRef.contains(event.target)) {
      this.setState({
        showStatusMenu: false,
      })
    }
  }

  hasSourceOrTargetTags() {
    var regExp = getXliffRegExpression()
    var sourceTags = this.context.segment.segment.match(regExp)
    return sourceTags && sourceTags.length > 0
  }

  hasMissingTargetTags() {
    var regExp = getXliffRegExpression()
    var sourceTags = this.context.segment.segment.match(regExp)
    if (!sourceTags || sourceTags.length === 0) {
      return false
    }
    var targetTags = this.context.segment.translation.match(regExp)

    return (
      (targetTags && sourceTags.length > targetTags.length) ||
      (targetTags && !_.isEqual(sourceTags.sort(), targetTags.sort()))
    )
  }

  getStatusMenu() {
    if (this.state.showStatusMenu && !this.isReviewExtended) {
      return (
        <ul className="statusmenu" ref={(menu) => (this.statusMenuRef = menu)}>
          <li className="arrow">
            <span className="arrow-mcolor" />
          </li>

          <li>
            <a
              className="draftStatusMenu"
              data-sid={'segment-' + this.context.segment.sid}
              title="set draft as status"
              onClick={this.changeStatus.bind(this, 'draft')}
            >
              DRAFT
            </a>
          </li>
          <li>
            <a
              className="translatedStatusMenu"
              data-sid={'segment-' + this.context.segment.sid}
              title="set translated as status"
              onClick={this.changeStatus.bind(this, 'translated')}
            >
              TRANSLATED
            </a>
          </li>
          <li>
            <a
              className="approvedStatusMenu"
              data-sid={'segment-' + this.context.segment.sid}
              title="set approved as status"
              onClick={this.changeStatus.bind(this, 'approved')}
            >
              APPROVED
            </a>
          </li>

          <li>
            <a
              className="rejectedStatusMenu"
              data-sid={'segment-' + this.context.segment.sid}
              title="set rejected as status"
              onClick={this.changeStatus.bind(this, 'rejected')}
            >
              REJECTED
            </a>
          </li>
        </ul>
      )
    } else {
      return ''
    }
  }
  copySource(e) {
    e.preventDefault()
    SegmentActions.copySourceToTarget(this.context.segment.sid)
  }

  componentDidMount() {
    document.addEventListener('mousedown', this.handleClickOutside)
  }

  componentWillUnmount() {
    document.removeEventListener('mousedown', this.handleClickOutside)
  }

  render() {
    var status_change_title
    if (this.context.segment.status) {
      status_change_title = this.statusHandleTitleAttr(
        this.context.segment.status,
      )
    } else {
      status_change_title = 'Change segment status'
    }
    let copySourceShortcuts = UI.isMac
      ? Shortcuts.cattol.events.copySource.keystrokes.mac
      : Shortcuts.cattol.events.copySource.keystrokes.standard
    return (
      <div
        onClick={this.props.onClick}
        className="text segment-body-content"
        ref={(body) => (this.segmentBody = body)}
      >
        <div className="wrap">
          <div className="outersource">
            <SegmentWrapper />

            <div
              className="copy"
              title="Copy source to target"
              onClick={(e) => this.copySource(e)}
            >
              <a href="#" />
              <p>{copySourceShortcuts.toUpperCase()}</p>
            </div>

            <SegmentWrapper isTarget />
          </div>
        </div>
        <div className="status-container">
          {this.isReviewExtended ? (
            <a
              href="#"
              className="status no-hover"
              onClick={this.openStatusSegmentMenu}
            />
          ) : (
            <a
              href="#"
              title={status_change_title}
              className="status"
              id={'segment-' + this.context.segment.sid + '-changestatus'}
              onClick={this.openStatusSegmentMenu}
            />
          )}
        </div>

        {this.getStatusMenu()}
      </div>
    )
  }
}

export default SegmentBody
