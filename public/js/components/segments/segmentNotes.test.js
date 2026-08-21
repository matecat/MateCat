import React from 'react'
import {render, screen} from '@testing-library/react'

import segmentNotes, {filterMetadataKeys} from './segmentNotes'

const {getMetadataNotes, getNote, getNoteContent, getNotes} = segmentNotes

jest.mock('../../utils/textUtils', () => ({
  getContentWithAllowedLinkRedirect: jest.fn((note) => [note]),
}))

import TEXT_UTILS from '../../utils/textUtils'

describe('filterMetadataKeys', () => {
  test('drops the size restriction, which has its own place in the editor', () => {
    expect(
      filterMetadataKeys([
        {meta_key: 'sizeRestriction', meta_value: '80'},
        {meta_key: 'author', meta_value: 'Ada'},
      ]),
    ).toEqual([{meta_key: 'author', meta_value: 'Ada'}])
  })

  test('treats a missing metadata list as empty', () => {
    expect(filterMetadataKeys()).toEqual([])
  })
})

describe('getNoteContent', () => {
  test('returns the note unchanged when it holds no link', () => {
    expect(getNoteContent('a plain note')).toBe('a plain note')
  })

  test('turns the link parts into anchors', () => {
    TEXT_UTILS.getContentWithAllowedLinkRedirect.mockReturnValue([
      'see ',
      {isLink: true, link: 'https://example.com'},
    ])

    render(<div>{getNoteContent('see https://example.com')}</div>)

    const anchor = screen.getByRole('link', {name: 'https://example.com'})
    expect(anchor).toHaveAttribute('href', 'https://example.com')

    TEXT_UTILS.getContentWithAllowedLinkRedirect.mockImplementation((note) => [
      note,
    ])
  })
})

describe('getNote', () => {
  test('renders the note text behind a label', () => {
    render(<div>{getNote({item: {note: 'mind the tags'}, index: 0})}</div>)

    expect(screen.getByText('Note:')).toBeInTheDocument()
    expect(screen.getByText('mind the tags')).toBeInTheDocument()
  })

  test('hides a translation context note, which the editor shows elsewhere', () => {
    expect(
      getNote({item: {note: 'translation_context|¶|something'}, index: 0}),
    ).toBeNull()
  })

  test('renders a json entry that is a string', () => {
    render(<div>{getNote({item: {json: 'raw payload'}, index: 3})}</div>)

    expect(screen.getByText('raw payload')).toBeInTheDocument()
  })

  test('renders nothing for a json entry that is an object', () => {
    expect(getNote({item: {json: {a: 1}}, index: 0})).toBeNull()
  })

  test('renders nothing for an entry with neither a note nor json', () => {
    expect(getNote({item: {}, index: 0})).toBeNull()
  })
})

describe('getMetadataNotes', () => {
  test('renders one labelled row per metadata entry', () => {
    render(
      <div>
        {getMetadataNotes({
          metadata: [
            {meta_key: 'author', meta_value: 'Ada'},
            {meta_key: 'source', meta_value: 'manual'},
          ],
        })}
      </div>,
    )

    expect(screen.getByText('author:')).toBeInTheDocument()
    expect(screen.getByText('Ada')).toBeInTheDocument()
    expect(screen.getByText('source:')).toBeInTheDocument()
    expect(screen.getByText('manual')).toBeInTheDocument()
  })

  test('renders an empty block when there is no metadata', () => {
    const {container} = render(<div>{getMetadataNotes({})}</div>)

    expect(container.querySelector('.metadata-notes')).toBeEmptyDOMElement()
  })
})

describe('getNotes', () => {
  test('shows the notes, then the information contexts, then the metadata', () => {
    render(
      <div>
        {getNotes({
          notes: [{note: 'first'}, {note: 'second'}],
          contextGroups: {
            context_json: [
              {
                attr: {purpose: 'information'},
                contexts: [{'raw-content': 'a screenshot'}],
              },
            ],
          },
          metadata: [{meta_key: 'author', meta_value: 'Ada'}],
        })}
      </div>,
    )

    expect(screen.getByText('first')).toBeInTheDocument()
    expect(screen.getByText('second')).toBeInTheDocument()
    expect(screen.getByText('Context:')).toBeInTheDocument()
    expect(screen.getByText('a screenshot')).toBeInTheDocument()
    expect(screen.getByText('author:')).toBeInTheDocument()
  })

  test('skips a context group that is not there to inform', () => {
    render(
      <div>
        {getNotes({
          contextGroups: {
            context_json: [
              {
                attr: {purpose: 'location'},
                contexts: [{'raw-content': 'a path'}],
              },
            ],
          },
        })}
      </div>,
    )

    expect(screen.queryByText('Context:')).not.toBeInTheDocument()
  })

  test('skips an information group that carries no context', () => {
    render(
      <div>
        {getNotes({
          contextGroups: {
            context_json: [{attr: {purpose: 'information'}, contexts: []}],
          },
        })}
      </div>,
    )

    expect(screen.queryByText('Context:')).not.toBeInTheDocument()
  })

  test('leaves out the metadata block when there is no metadata', () => {
    const {container} = render(
      <div>{getNotes({notes: [{note: 'only a note'}], metadata: []})}</div>,
    )

    expect(screen.getByText('only a note')).toBeInTheDocument()
    expect(container.querySelector('.metadata-notes')).toBeNull()
  })

  test('returns nothing at all for a segment with no notes', () => {
    expect(getNotes({})).toEqual([])
  })
})
