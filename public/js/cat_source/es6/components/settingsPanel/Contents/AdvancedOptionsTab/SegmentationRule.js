import React, {useEffect, useState} from 'react'
import {Select} from '../../../common/Select'
import AlertModal from '../../../modals/AlertModal'

export const SegmentationRule = ({segmentationRule, setSegmentationRule}) => {
  const [active, setActive] = useState(segmentationRule)

  const options = [
    {name: 'General', id: ''},
    {name: 'Patent', id: 'patent'},
    {name: 'Paragraph (beta)', id: 'paragraph'},
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
    if (config.is_cattool && config.segmentation_rule) {
      const activeRule = options.find(
        (option) => option.id === config.segmentation_rule,
      )
      activeRule && setActive(activeRule)
    } else {
      setActive(options[0])
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
      <p>
        Select how sentences are split according to specific types of content.
      </p>
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