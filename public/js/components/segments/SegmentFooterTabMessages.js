import React from 'react'
import segmentNotes from './segmentNotes'

// The class carried a shouldComponentUpdate, but it tested
// `isUndefined(this.props.note)` — singular, where the prop is `notes` — so the
// first clause was always true and it never skipped a render. Dropping it is
// therefore not a behaviour change. Fixing the typo would be one, and belongs in
// its own PR.
const SegmentFooterTabMessages = ({
  code,
  active_class,
  tab_class,
  notes,
  metadata,
  context_groups,
  segmentSource,
  segment,
}) => (
  <div
    key={'container_' + code}
    className={'tab sub-editor ' + active_class + ' ' + tab_class}
    id={'segment-' + segment.sid + '-' + tab_class}
  >
    <div className="overflow">
      <div className="segment-notes-container">
        <div className="segment-notes-panel-body">
          <div className="segments-notes-container">
            {segmentNotes.getNotes({
              notes,
              metadata,
              segment,
              segmentSource,
              contextGroups: context_groups,
            })}
          </div>
        </div>
      </div>
    </div>
  </div>
)

export default SegmentFooterTabMessages
