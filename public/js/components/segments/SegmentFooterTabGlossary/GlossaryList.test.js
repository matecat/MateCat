import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import GlossaryList from './GlossaryList'
import {TabGlossaryContext} from './TabGlossaryContext'
import '../../../extensions/extensionManifest'

jest.mock('../../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = cb
    }),
    removeListener: jest.fn(),
    __emit: (event, data) => listeners[event] && listeners[event](data),
  }
})

jest.mock('../../../actions/SegmentActions', () => ({
  deleteGlossaryItem: jest.fn(),
  copyGlossaryItemInEditarea: jest.fn(),
}))

const SegmentStore = require('../../../stores/SegmentStore')
const SegmentActions = require('../../../actions/SegmentActions')
const SegmentConstants = require('../../../constants/SegmentConstants').default

const segment = {
  sid: '1',
  glossary: [{term_id: 'a'}],
  glossary_search_results: [{term_id: 'a'}],
}

const term = {
  term_id: 'a',
  metadata: {key: 'key-1', domain: 'Legal', subdomain: 'Contracts'},
  source: {term: 'src', note: ''},
  target: {term: 'tgt', note: ''},
}

const defaultContext = {
  terms: [],
  searchTerm: '',
  previousSearchTermRef: {current: ''},
  isLoading: false,
  setSearchTerm: jest.fn(),
  segment,
  keys: [{id: 'k1', key: 'key-1'}],
  setShowForm: jest.fn(),
  setModifyElement: jest.fn(),
  setShowMore: jest.fn(),
  setSelectsActive: jest.fn(),
  domains: [{name: 'Legal'}],
  subdomains: [{name: 'Contracts'}],
  getRequestPayloadTemplate: jest.fn(() => ({payload: true})),
  termsStatusDeleting: [],
  setTermsStatusDeleting: jest.fn(),
}

const renderList = (contextOverrides = {}) =>
  render(
    <TabGlossaryContext.Provider
      value={{...defaultContext, ...contextOverrides}}
    >
      <GlossaryList />
    </TabGlossaryContext.Provider>,
  )

beforeAll(() => {
  // jsdom does not implement Element.scrollTo
  Element.prototype.scrollTo = jest.fn()
})

afterEach(() => {
  jest.clearAllMocks()
})

describe('GlossaryList', () => {
  test('shows loading label when no terms and isLoading', () => {
    renderList({isLoading: true})
    expect(screen.getByText('Loading')).toBeInTheDocument()
  })

  test('shows "No results" when no terms, not loading, and no search term', () => {
    renderList()
    expect(screen.getByText('No results')).toBeInTheDocument()
  })

  test('shows "No results for X" when the search returned nothing', () => {
    renderList({
      searchTerm: 'xyz',
      previousSearchTermRef: {current: 'xyz'},
    })
    expect(screen.getByText('xyz')).toBeInTheDocument()
  })

  test('renders a GlossaryItem per term', () => {
    renderList({terms: [term]})
    expect(screen.getByText('src')).toBeInTheDocument()
    expect(screen.getByText('tgt')).toBeInTheDocument()
  })

  test('registers and unregisters the HIGHLIGHT_GLOSSARY_TERM listener', () => {
    const {unmount} = renderList({terms: [term]})
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.HIGHLIGHT_GLOSSARY_TERM,
      expect.any(Function),
    )
    unmount()
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.HIGHLIGHT_GLOSSARY_TERM,
      expect.any(Function),
    )
  })

  test('clicking modify on an enabled item prefills selects and opens the form', () => {
    const setShowForm = jest.fn()
    const setModifyElement = jest.fn()
    const setShowMore = jest.fn()
    const setSelectsActive = jest.fn()
    renderList({
      terms: [term],
      setShowForm,
      setModifyElement,
      setShowMore,
      setSelectsActive,
    })

    fireEvent.click(document.querySelector('.glossary_item-actions div'))

    expect(setShowMore).toHaveBeenCalledWith(true)
    expect(setShowForm).toHaveBeenCalledWith(true)
    expect(setModifyElement).toHaveBeenCalledWith(term)
    expect(setSelectsActive).toHaveBeenCalled()
  })

  test('clicking delete on an enabled item marks it as deleting and dispatches the delete action', () => {
    const setTermsStatusDeleting = jest.fn()
    renderList({terms: [term], setTermsStatusDeleting})

    const actionDivs = document.querySelectorAll('.glossary_item-actions div')
    fireEvent.click(actionDivs[actionDivs.length - 1])

    expect(setTermsStatusDeleting).toHaveBeenCalled()
    expect(SegmentActions.deleteGlossaryItem).toHaveBeenCalledWith({
      payload: true,
    })
  })

  test('clicking the target term copies it into the editarea', () => {
    renderList({terms: [term]})
    fireEvent.mouseDown(
      screen.getByLabelText('Click to insert the term in the target segment'),
    )
    expect(SegmentActions.copyGlossaryItemInEditarea).toHaveBeenCalledWith(
      term.target.term,
      segment,
    )
  })

  test('ignores HIGHLIGHT_GLOSSARY_TERM events for a different segment', () => {
    renderList({terms: [term]})
    expect(() =>
      SegmentStore.__emit(SegmentConstants.HIGHLIGHT_GLOSSARY_TERM, {
        sid: 'other-sid',
        termId: 'a',
      }),
    ).not.toThrow()
  })
})
