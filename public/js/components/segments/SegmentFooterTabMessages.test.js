import React from 'react'
import {render, screen} from '@testing-library/react'
import SegmentFooterTabMessages from './SegmentFooterTabMessages'

jest.mock('../../utils/textUtils', () => ({
  getContentWithAllowedLinkRedirect: (text) => [text],
}))

const defaultProps = {
  notes: [],
  context_groups: {},
  metadata: [],
  segment: {sid: 1},
  code: 'messages',
  active_class: '',
  tab_class: 'messages',
}

const renderComponent = (props = {}) =>
  render(<SegmentFooterTabMessages {...defaultProps} {...props} />)

describe('SegmentFooterTabMessages', () => {
  test('renders a regular note unchanged', () => {
    renderComponent({notes: [{note: 'Translate as informal'}]})
    expect(screen.getByText('Translate as informal')).toBeInTheDocument()
  })

  test('strips the translation_context|¶| prefix before rendering', () => {
    renderComponent({
      notes: [{note: 'translation_context|¶|living room description'}],
    })
    expect(
      screen.queryByText('living room description'),
    ).not.toBeInTheDocument()
    expect(screen.queryByText(/translation_context/)).not.toBeInTheDocument()
  })

  test('does not render a note that is only the translation_context marker', () => {
    renderComponent({notes: [{note: 'translation_context|¶|'}]})
    expect(document.querySelector('.note')).not.toBeInTheDocument()
  })
})
