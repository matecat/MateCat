import React, {useRef, useState} from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {SearchTerms} from './SearchTerms'
import {TabGlossaryContext} from './TabGlossaryContext'

jest.mock('../../../actions/SegmentActions', () => ({
  setGlossaryForSegmentBySearch: jest.fn(),
  searchGlossary: jest.fn(),
}))

const SegmentActions = require('../../../actions/SegmentActions')

const segment = {sid: '10', segment: 'Hello world'}

const Harness = ({initialSearchTerm = '', openForm, isLoading = false} = {}) => {
  const [searchTerm, setSearchTerm] = useState(initialSearchTerm)
  const previousSearchTermRef = useRef('')
  const notifyLoadingStatusToParent = jest.fn()

  return (
    <TabGlossaryContext.Provider
      value={{
        searchTerm,
        setSearchTerm,
        segment,
        previousSearchTermRef,
        openForm,
        isLoading,
        notifyLoadingStatusToParent,
      }}
    >
      <SearchTerms />
    </TabGlossaryContext.Provider>
  )
}

beforeAll(() => {
  global.config = Object.assign(global.config ?? {}, {
    source_code: 'en-US',
    target_code: 'it-IT',
  })
})

afterEach(() => {
  jest.clearAllMocks()
  jest.useRealTimers()
})

describe('SearchTerms', () => {
  test('renders the search input with the current search term', () => {
    render(<Harness initialSearchTerm="foo" />)
    expect(screen.getByPlaceholderText('Search term')).toHaveValue('foo')
  })

  test('typing into the input updates the search term', () => {
    render(<Harness />)
    fireEvent.change(screen.getByPlaceholderText('Search term'), {
      target: {value: 'bar'},
    })
    expect(screen.getByPlaceholderText('Search term')).toHaveValue('bar')
  })

  test('reset button clears the search term when clicked', () => {
    render(<Harness initialSearchTerm="baz" />)
    const resetButton = document.querySelector(
      '.search_term_reset_button--visible',
    )
    fireEvent.click(resetButton)
    expect(screen.getByPlaceholderText('Search term')).toHaveValue('')
  })

  test('reset button is hidden when there is no search term', () => {
    render(<Harness />)
    expect(
      document.querySelector('.search_term_reset_button--hidden'),
    ).toBeInTheDocument()
  })

  test('Add Term button calls openForm and is disabled while loading', () => {
    const openForm = jest.fn()
    render(<Harness openForm={openForm} isLoading />)
    const button = screen.getByRole('button', {name: /Add Term/})
    expect(button).toBeDisabled()
    fireEvent.click(button)
    expect(openForm).not.toHaveBeenCalled()
  })

  test('Add Term button calls openForm when enabled', () => {
    const openForm = jest.fn()
    render(<Harness openForm={openForm} />)
    fireEvent.click(screen.getByRole('button', {name: /Add Term/}))
    expect(openForm).toHaveBeenCalled()
  })

  test('debounces search and dispatches searchGlossary for the selected source type', () => {
    jest.useFakeTimers()
    render(<Harness initialSearchTerm="glossary term" />)

    act(() => {
      jest.advanceTimersByTime(500)
    })

    expect(SegmentActions.searchGlossary).toHaveBeenCalledWith(
      expect.objectContaining({
        sentence: 'glossary term',
        idSegment: segment.sid,
        sourceLanguage: 'en-US',
        targetLanguage: 'it-IT',
        isSearchingInTarget: false,
      }),
    )
  })

  test('switching to Target search type flips the language payload', () => {
    jest.useFakeTimers()
    render(<Harness initialSearchTerm="glossary term" />)

    fireEvent.click(screen.getByTestId('radio-option-1'))

    act(() => {
      jest.advanceTimersByTime(500)
    })

    expect(SegmentActions.searchGlossary).toHaveBeenCalledWith(
      expect.objectContaining({
        sourceLanguage: 'it-IT',
        targetLanguage: 'en-US',
        isSearchingInTarget: true,
      }),
    )
  })
})
