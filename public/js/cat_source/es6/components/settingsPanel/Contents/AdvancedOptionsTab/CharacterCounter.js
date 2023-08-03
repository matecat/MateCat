import React, {useState} from 'react'
import Switch from '../../../common/Switch'
import SegmentUtils from '../../../../utils/segmentUtils'
import SegmentActions from '../../../../actions/SegmentActions'

export const CharacterCounter = () => {
  const [active, setActive] = useState(SegmentUtils.isCharacterCounterEnable())
  const onChange = (selected) => {
    SegmentActions.toggleCharacterCounter()
    setActive(selected)
  }
  return (
    <div className="options-box charscounter">
      <h3>Character counter</h3>
      <div className="option-description">
        <p>
          Enabling this option makes a counter appear that counts the number of
          characters in the target section of each segment.
        </p>
      </div>
      <div className="options-box-value">
        <Switch active={active} onChange={onChange} />
      </div>
    </div>
  )
}
