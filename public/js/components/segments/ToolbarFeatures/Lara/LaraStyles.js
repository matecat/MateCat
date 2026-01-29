import React from 'react'
import {LARA_STYLES} from '../../../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions'
import {Popover, POPOVER_VERTICAL_ALIGN} from '../../../common/Popover/Popover'
import {BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import Palette from '../../../icons/Palette'
import SegmentActions from '../../../../actions/SegmentActions'

export const LaraStyles = ({sid}) => {
  const options = [
    {
      id: LARA_STYLES.FAITHFUL,
      label: 'Faithful',
      description:
        'Precise translation, maintaining original structure and meaning accurately.',
    },
    {
      id: LARA_STYLES.FLUID,
      label: 'Fluid',
      description:
        'Smooth translation, emphasizing readability and natural language flow. For general content.',
    },
    {
      id: LARA_STYLES.CREATIVE,
      label: 'Creative',
      description:
        'Imaginative translation, capturing essence with vivid and engaging language. For marketing, literature, etc.',
    },
  ]

  const setStyle = (style) => SegmentActions.setLaraStyle({sid, style})

  return (
    <Popover
      className="lara-styles-popover"
      toggleButtonProps={{
        title: 'Lara styles',
        size: BUTTON_SIZE.ICON_SMALL,
        mode: BUTTON_MODE.OUTLINE,
        className: 'segment-target-toolbar-icon',
        children: (
          <>
            <Palette size={16} />
          </>
        ),
      }}
      verticalAlign={POPOVER_VERTICAL_ALIGN.BOTTOM}
    >
      <ul className="lara-styles-popover-list">
        {options.map((option, index) => (
          <li key={index}>
            <div
              className="lara-styles-popover-item"
              onClick={() => setStyle(option.id)}
            >
              <span>{option.label}</span>
              <p>{option.description}</p>
            </div>
          </li>
        ))}
      </ul>
    </Popover>
  )
}
