import React from 'react'
import {LARA_STYLES} from '../../../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions'

export const LaraStyles = () => {
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

  return <div>LaraStyles</div>
}
