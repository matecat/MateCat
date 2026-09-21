import React from 'react'
import {fromJS} from 'immutable'
import {isUndefined} from 'lodash'
import segmentNotes from './segmentNotes'

class SegmentFooterTabMessages extends React.Component {
  componentDidMount() {}

  componentWillUnmount() {}

  allowHTML(string) {
    return {__html: string}
  }

  shouldComponentUpdate(nextProps) {
    return (
      isUndefined(nextProps.notes) ||
      isUndefined(this.props.note) ||
      !fromJS(this.props.notes).equals(fromJS(nextProps.notes)) ||
      this.props.loading !== nextProps.loading ||
      this.props.active_class !== nextProps.active_class ||
      this.props.tab_class !== nextProps.tab_class
    )
  }

  render() {
    return (
      <div
        key={'container_' + this.props.code}
        className={
          'tab sub-editor ' +
          this.props.active_class +
          ' ' +
          this.props.tab_class
        }
        id={'segment-' + this.props.segment.sid + '-' + this.props.tab_class}
      >
        <div className="overflow">
          <div className="segment-notes-container">
            <div className="segment-notes-panel-body">
              <div className="segments-notes-container">
                {segmentNotes.getNotes({
                  notes: this.props.notes,
                  metadata: this.props.metadata,
                  segment: this.props.segment,
                  segmentSource: this.props.segment.segment,
                  contextGroups: this.props.context_groups,
                })}
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}

export default SegmentFooterTabMessages
