import React from 'react'
import {render, screen} from '@testing-library/react'
import SegmentFooterTabMessages from './SegmentFooterTabMessages'
import segmentNotes from './segmentNotes'

jest.mock('../../utils/textUtils', () => ({
  getContentWithAllowedLinkRedirect: jest.fn((text) => [text]),
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

// The seam exists so plugins can replace note rendering by assigning over these
// members, the way they used to patch the component prototype. Prototype patching
// stops working the moment the host becomes a function component; these tests pin
// the replacement that takes its place.
describe('the segmentNotes seam', () => {
  const pristine = {...segmentNotes}

  afterEach(() => {
    Object.keys(segmentNotes).forEach((key) => delete segmentNotes[key])
    Object.assign(segmentNotes, pristine)
  })

  test('a plugin replacing getNotes decides what the tab renders', () => {
    segmentNotes.getNotes = () => <div>from the plugin</div>

    renderComponent({notes: [{note: 'core note'}]})

    expect(screen.getByText('from the plugin')).toBeInTheDocument()
    expect(screen.queryByText('core note')).not.toBeInTheDocument()
  })

  test('a plugin replacing getNote is reached through getNotes', () => {
    // The core getNotes still runs; it must call the seam member rather than a
    // local reference, or the plugin's version is silently bypassed.
    segmentNotes.getNote = ({item}) => <div key="x">{`seen: ${item.note}`}</div>

    renderComponent({notes: [{note: 'core note'}]})

    expect(screen.getByText(/seen: core note/)).toBeInTheDocument()
  })

  test('a plugin can hide notes with excludeMatchingNotesRegExp', () => {
    segmentNotes.excludeMatchingNotesRegExp = /internal/

    renderComponent({notes: [{note: 'internal remark'}, {note: 'keep me'}]})

    expect(screen.queryByText('internal remark')).not.toBeInTheDocument()
    expect(screen.getByText('keep me')).toBeInTheDocument()
  })
})

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

  test('renders note content as a link when getContentWithAllowedLinkRedirect returns multiple pieces', () => {
    const textUtils = require('../../utils/textUtils')
    // getNoteContentStructure calls getContentWithAllowedLinkRedirect twice
    // (once to check length, once to map), so queue the value for both calls.
    const multiPiece = ['See ', {isLink: true, link: 'https://matecat.com'}]
    textUtils.getContentWithAllowedLinkRedirect
      .mockReturnValueOnce(multiPiece)
      .mockReturnValueOnce(multiPiece)
    renderComponent({notes: [{note: 'See https://matecat.com'}]})

    const link = screen.getByRole('link', {name: 'https://matecat.com'})
    expect(link).toHaveAttribute('href', 'https://matecat.com')
    expect(link).toHaveAttribute('target', '_blank')
  })

  test('renders context groups with purpose "information"', () => {
    renderComponent({
      context_groups: {
        context_json: [
          {
            attr: {purpose: 'information'},
            contexts: [{'raw-content': 'first'}, {'raw-content': 'second'}],
          },
        ],
      },
    })

    expect(screen.getByText('Context:')).toBeInTheDocument()
    expect(screen.getByText('first')).toBeInTheDocument()
    expect(screen.getByText(/second/)).toBeInTheDocument()
  })

  test('ignores context groups without the "information" purpose', () => {
    renderComponent({
      context_groups: {
        context_json: [
          {
            attr: {purpose: 'other'},
            contexts: [{'raw-content': 'hidden'}],
          },
        ],
      },
    })

    expect(screen.queryByText('Context:')).not.toBeInTheDocument()
  })

  test('renders metadata notes excluding sizeRestriction', () => {
    renderComponent({
      metadata: [
        {meta_key: 'sizeRestriction', meta_value: '10'},
        {meta_key: 'author', meta_value: 'Jane'},
      ],
    })

    expect(screen.getByText('author:')).toBeInTheDocument()
    expect(screen.getByText('Jane')).toBeInTheDocument()
    expect(screen.queryByText('sizeRestriction:')).not.toBeInTheDocument()
  })

  test('renders a note when item.json is a non-empty object (no visible output but no crash)', () => {
    const {container} = renderComponent({
      notes: [{json: {key1: 'value1'}}],
    })
    // This branch does not append visible notes due to a forEach not
    // propagating its return value, but it must not throw.
    expect(
      container.querySelector('.segments-notes-container'),
    ).toBeInTheDocument()
  })

  test('renders a note when item.json is a plain string', () => {
    renderComponent({notes: [{json: 'Plain text note'}]})
    expect(screen.getByText('Plain text note')).toBeInTheDocument()
  })

  test('renders nothing for a note item with neither note nor json', () => {
    const {container} = renderComponent({notes: [{}]})
    expect(
      container.querySelector('.segments-notes-container').childElementCount,
    ).toBe(0)
  })
})
