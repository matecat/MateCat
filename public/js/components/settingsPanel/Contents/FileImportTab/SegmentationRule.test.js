import React from 'react'
import {render, screen} from '@testing-library/react'
import {SegmentationRule} from './SegmentationRule'

const setup = (segmentationRule = {id: 'standard'}) => {
  const setSegmentationRule = jest.fn()
  const utils = render(
    <SegmentationRule
      segmentationRule={segmentationRule}
      setSegmentationRule={setSegmentationRule}
    />,
  )
  return {setSegmentationRule, ...utils}
}

describe('SegmentationRule', () => {
  beforeEach(() => {
    global.config = {is_cattool: false, segmentation_rule: ''}
  })

  test('renders the select with the current active rule', () => {
    setup()

    expect(screen.getByText('Segmentation Rules')).toBeInTheDocument()
    expect(screen.getByTestId('container-segmentationrule')).toBeInTheDocument()
    expect(screen.getByText('General')).toBeInTheDocument()
  })

  test('reports the initial rule through setSegmentationRule outside of cattool', () => {
    global.config = {is_cattool: false, segmentation_rule: ''}
    const {setSegmentationRule} = setup({id: 'standard'})

    expect(setSegmentationRule).toHaveBeenCalledWith({
      id: 'standard',
      name: 'General',
    })
  })

  test('updates active rule when segmentationRule prop changes', () => {
    const {rerender} = setup({id: 'standard'})

    expect(screen.getByText('General')).toBeInTheDocument()

    rerender(
      <SegmentationRule
        segmentationRule={{id: 'patent'}}
        setSegmentationRule={jest.fn()}
      />,
    )

    expect(screen.getByText('Patent')).toBeInTheDocument()
  })

  test('applies the default rule when in cattool mode with no configured segmentation_rule', () => {
    global.config = {is_cattool: true, segmentation_rule: ''}

    setup({id: 'standard'})

    expect(screen.getByText('General')).toBeInTheDocument()
  })

  test('applies the configured segmentation_rule when in cattool mode', () => {
    global.config = {is_cattool: true, segmentation_rule: 'paragraph'}

    setup({id: 'standard'})

    expect(screen.getByText('Paragraph')).toBeInTheDocument()
  })

  test('does not call setSegmentationRule when in cattool mode', () => {
    global.config = {is_cattool: true, segmentation_rule: ''}

    const {setSegmentationRule} = setup({id: 'standard'})

    expect(setSegmentationRule).not.toHaveBeenCalled()
  })
})
