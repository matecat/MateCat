import React, {useEffect, useState} from 'react'
import {Select} from '../../../common/Select'

const options = [
  {
    name: 'General',
    id: 'standard',
    description: (
      <>
        Generates a new segment at the end of each layout element (e.g. a
        paragraph, a table cell etc.) and every time a strong punctuation mark
        is detected (e.g. full stop, exclamation mark).
      </>
    ),
  },
  {
    name: 'Patent',
    id: 'patent',
    description: (
      <>
        Works like the general rule, but includes a series of exceptions for
        abbreviations used commonly in patents.
      </>
    ),
  },
  {
    name: 'Paragraph',
    id: 'paragraph',
    description: (
      <>
        Only generates a new segment at the end of each layout element (e.g. a
        paragraph, a table cell etc.).
      </>
    ),
  },
]

export const SegmentationRule = ({segmentationRule, setSegmentationRule}) => {
  const [active, setActive] = useState(segmentationRule.id)

  useEffect(() => {
    setActive(segmentationRule.id)
  }, [segmentationRule])

  useEffect(() => {
    if (config.is_cattool) {
      let activeRule
      if (config.segmentation_rule === '') {
        activeRule = options[0]
      } else {
        activeRule = options.find(
          (option) => option.id === config.segmentation_rule,
        )
      }
      activeRule && setActive(activeRule.id)
    }
  }, [])
  useEffect(() => {
    if (!config.is_cattool) {
      //eslint-disable-next-line
      const {description, ...rule} = options.find(
        (option) => option.id === active,
      )

      setSegmentationRule(rule)
    }
  }, [active, setSegmentationRule])

  return (
    <div className="options-box seg_rule">
      <div className="option-description">
        <h2>Segmentation Rules</h2>
        <p>
          Select how sentences are split according to specific types of content.
        </p>
      </div>
      <div
        className="options-select-container"
        data-testid="container-segmentationrule"
      >
        <Select
          options={options.map((option) => ({
            ...option,
            name: (
              <div className="option-dropdown-with-descrition-select-content">
                {option.name}
                <p>{option.description}</p>
              </div>
            ),
          }))}
          dropdownClassName="select-dropdown__wrapper-portal option-dropdown-with-descrition"
          activeOption={options.find(({id}) => id === active)}
          isPortalDropdown={true}
          onSelect={(option) => setActive(option.id)}
        />
      </div>
    </div>
  )
}
