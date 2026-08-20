import React from 'react'
import PropTypes from 'prop-types'
import {extend} from '../../extensions/extensionPoints'
import {SEGMENT_NOTES} from '../../extensions/extensionPointNames'
import {filterMetadataKeys} from '../../extensions/segmentNoteDefaults'

const SegmentFooterTabMessages = ({
  code,
  active_class,
  tab_class,
  segment,
  notes,
  metadata,
  context_groups: contextGroups,
  segmentSource,
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
            {extend(SEGMENT_NOTES)({
              notes,
              contextGroups,
              metadata: filterMetadataKeys(metadata),
              segment,
              segmentSource,
            })}
          </div>
        </div>
      </div>
    </div>
  </div>
)

SegmentFooterTabMessages.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object.isRequired,
  notes: PropTypes.array,
  metadata: PropTypes.array,
  context_groups: PropTypes.object,
  segmentSource: PropTypes.string,
}

// No memo comparator, deliberately. The shouldComponentUpdate this replaces
// tested `this.props.note` — singular, never a prop — so its first clause was
// always true and the component always re-rendered. Adding a comparator that
// works would be a new optimisation, not a port of this one.
export default SegmentFooterTabMessages
