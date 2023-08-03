import React, {useEffect, useState} from 'react'
import {Select} from '../../../common/Select'
import AlertModal from '../../../modals/AlertModal'

export const SegmentationRule = ({segmentationRule, setSegmentationRule}) => {
  const [active, setActive] = useState(segmentationRule)

  const options = [
    {name: 'General', id: '1'},
    {name: 'Patent', id: 'patent'},
    {name: 'Paragraph', id: 'paragraph'},
  ]
  const onClick = () => {
    if (!!config.is_cattool) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Segment rules settings can only be edited when creating the project.',
          buttonText: 'Continue',
        },
        'Option not editable',
      )
    }
  }
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
      activeRule && setActive(activeRule)
    }
  }, [])
  useEffect(() => {
    if (!config.is_cattool) {
      setSegmentationRule(active)
    }
  }, [active])
  return (
    <div className="options-box seg_rule" onClick={onClick}>
      <h3>Segmentation Rules</h3>
      <div className="option-description">
        <p>
          Select how sentences are split according to specific types of content.
        </p>
        <div>
          <p>
            <b>General</b> - Generates a new segment at the end of each layout
            element (e.g. a paragraph, a table cell etc.) and every time a
            strong punctuation mark is detected (e.g. full stop, exclamation
            mark).
          </p>
          <p>
            <b>Patent</b> - Works like the general rule, but includes a series
            of exceptions for abbreviations used commonly in patents.
          </p>
          <p>
            <b>Paragraph</b> - Only generates a new segment at the end of each
            layout element (e.g. a paragraph, a table cell etc.).
          </p>
        </div>
      </div>
      <div className="options-select-container">
        <Select
          options={options}
          activeOption={active}
          isDisabled={!!config.is_cattool}
          onSelect={setActive}
        />
      </div>
    </div>
  )
}
