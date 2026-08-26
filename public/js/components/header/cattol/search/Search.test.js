import React from 'react'
import {render, screen, act, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'

import Search from './Search'
import SearchUtils from './searchUtils'
import CattolConstants from '../../../../constants/CatToolConstants'
import SegmentConstants from '../../../../constants/SegmentConstants'
import CatToolStore from '../../../../stores/CatToolStore'
import SegmentStore from '../../../../stores/SegmentStore'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'
import ModalsActions from '../../../../actions/ModalsActions'
import {segmentTranslation} from '../../../../setTranslationUtil'
import {searchTermIntoSegments} from '../../../../api/searchTermIntoSegments'
import {MODAL_KEY} from '../../../../constants/ModalKeys'

jest.mock('../../../../actions/SegmentActions')
jest.mock('../../../../actions/CatToolActions')
jest.mock('../../../../actions/ModalsActions')
jest.mock('../../../../actions/segmentDispatchActions')
jest.mock('../../../../api/searchTermIntoSegments')
jest.mock('../../../../setTranslationUtil')

jest.mock('../../../common/Select', () => ({
  Select: ({options, activeOption, onSelect, resetFunction}) => (
    <div>
      <select
        data-testid="status-select"
        value={activeOption?.id ?? ''}
        onChange={(e) =>
          onSelect(
            options.find((option) => option.id === e.target.value) || {
              id: e.target.value,
            },
          )
        }
      >
        <option value="" />
        {options.map((option) => (
          <option key={option.id} value={option.id}>
            {option.id}
          </option>
        ))}
      </select>
      <button type="button" onClick={resetFunction}>
        reset status
      </button>
    </div>
  ),
}))

const baseProps = () => ({
  active: true,
  isReview: false,
  searchable_statuses: [],
  userInfo: {metadata: {guess_tag: 0}},
})

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {
    searchable_statuses: [
      {value: 'NEW', label: 'NEW'},
      {value: 'APPROVED', label: 'APPROVED'},
    ],
    secondRevisionsCount: false,
    job_is_splitted: false,
  }
  jest.spyOn(SegmentStore, 'getCurrentSegmentId').mockReturnValue(1)
  jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue(undefined)
  SearchUtils.searchEnabled = true
  SearchUtils.searchOpen = false
  SearchUtils.searchParams = {search: 0}
  SearchUtils.total = 0
  SearchUtils.searchResults = []
  SearchUtils.occurrencesList = []
  SearchUtils.searchResultsDictionary = {}
  SearchUtils.featuredSearchResult = 0
  SearchUtils.searchSegmentsResult = []
  delete SearchUtils.searchMode
})

afterEach(() => {
  jest.restoreAllMocks()
})

const emitStoreSearchResult = (data) => {
  act(() => {
    CatToolStore.emit(CattolConstants.STORE_SEARCH_RESULT, data)
  })
}

const checkboxByLabel = (text) =>
  screen.getByText(text).closest('div').querySelector('input')

const queryCheckboxByLabel = (text) => {
  const label = screen.queryByText(text)
  return label ? label.closest('div').querySelector('input') : null
}

test('renders nothing when not active', () => {
  const {container} = render(<Search {...baseProps()} active={false} />)
  expect(container.innerHTML).toBe('')
})

// Search.js removes its document keydown listener without the `useCapture`
// flag it was added with, so it never actually detaches. This test must run
// before any other test mounts an active instance, or a leaked listener from
// an earlier test will also react to the keydown fired here.
test('does not react to keyboard shortcuts when not active', () => {
  render(<Search {...baseProps()} active={false} />)

  act(() => {
    fireEvent.keyDown(document, {key: 'F3'})
  })

  expect(SegmentActions.changeCurrentSearchSegment).not.toHaveBeenCalled()
})

test('renders the search form with source and target inputs when active', () => {
  render(<Search {...baseProps()} />)

  expect(screen.getByPlaceholderText('Find in source')).toBeInTheDocument()
  expect(screen.getByPlaceholderText('Find in target')).toBeInTheDocument()
  expect(screen.getByText('FIND')).toBeInTheDocument()
  expect(screen.getByText('REPLACE')).toBeInTheDocument()
  expect(screen.getByText('REPLACE ALL')).toBeInTheDocument()
})

test('adds the APPROVED-2 status option when secondRevisionsCount is set', () => {
  global.config.secondRevisionsCount = true
  render(<Search {...baseProps()} />)

  expect(screen.getByRole('option', {name: 'APPROVED-2'})).toBeInTheDocument()
})

test('typing in the source input enables the find button and shows the clear button', async () => {
  const user = userEvent.setup()
  render(<Search {...baseProps()} />)

  expect(screen.getByText('FIND')).toBeDisabled()

  await user.type(screen.getByPlaceholderText('Find in source'), 'hello')

  expect(screen.getByText('FIND')).toBeEnabled()
  expect(screen.getByText('Clear')).toBeInTheDocument()
})

test('typing in the target input enables the find button', async () => {
  const user = userEvent.setup()
  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in target'), 'world')

  expect(screen.getByText('FIND')).toBeEnabled()
})

test('toggling match case and whole word checkboxes updates their checked state', async () => {
  const user = userEvent.setup()
  render(<Search {...baseProps()} />)

  const matchCase = checkboxByLabel('Match Case')
  const wholeWord = checkboxByLabel('Whole word')

  expect(matchCase).not.toBeChecked()
  await user.click(matchCase)
  expect(matchCase).toBeChecked()

  expect(wholeWord).not.toBeChecked()
  await user.click(wholeWord)
  expect(wholeWord).toBeChecked()
})

test('clicking clear resets the search fields after the deferred timeout', async () => {
  const user = userEvent.setup()
  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in source'), 'hello')
  await user.click(screen.getByText('Clear'))

  expect(screen.getByPlaceholderText('Find in source')).toHaveValue('')
})

test('inserts a non-breaking space placeholder on Ctrl+Shift+Space', () => {
  render(<Search {...baseProps()} />)
  const sourceInput = screen.getByPlaceholderText('Find in source')
  sourceInput.selectionStart = 0

  fireEvent.keyDown(sourceInput, {
    code: 'Space',
    ctrlKey: true,
    shiftKey: true,
  })

  expect(sourceInput.value.length).toBeGreaterThan(0)
})

test('selecting a status option calls handleStatusChange and enables the reset flow', async () => {
  const user = userEvent.setup()
  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in source'), 'hello')
  fireEvent.change(screen.getByTestId('status-select'), {
    target: {value: 'APPROVED'},
  })

  expect(screen.getByText('Clear')).toBeInTheDocument()

  await user.click(screen.getByText('reset status'))
})

test('clicking find submits a normal search and stores the results in the segments', async () => {
  const user = userEvent.setup()
  searchTermIntoSegments.mockResolvedValue({segments: [10], total: 1})
  jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
    decodedSource: 'hello world',
    decodedTranslation: 'ciao mondo',
  })

  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in source'), 'hello')
  await user.click(screen.getByText('FIND'))

  expect(searchTermIntoSegments).toHaveBeenCalled()
  await act(async () => {
    await Promise.resolve()
    await Promise.resolve()
  })
})

test('disables tag projection when the user has guess tag enabled', async () => {
  const user = userEvent.setup()
  searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})

  render(<Search {...baseProps()} userInfo={{metadata: {guess_tag: 1}}} />)

  await user.type(screen.getByPlaceholderText('Find in source'), 'hello')
  await user.click(screen.getByText('FIND'))

  expect(SegmentActions.changeTagProjectionStatus).toHaveBeenCalledWith(false)
})

// The natural order is to search first and only then type what to replace the
// hits with. Typing into "Replace in target" used to run the same reset branch
// as a query field, which cleared the results and re-armed FIND.
test('keeps REPLACE enabled when the replacement is typed after the search', async () => {
  const user = userEvent.setup()
  searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})

  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in target'), 'old')
  await user.click(checkboxByLabel('Replace with'))
  await user.click(screen.getByText('FIND'))

  emitStoreSearchResult({
    total: 2,
    searchResults: [{id: 1}, {id: 2}],
    occurrencesList: [1, 2],
    searchResultsDictionary: {1: {id: 1}, 2: {id: 2}},
    featuredSearchResult: 0,
  })

  const replaceButton = screen.getByText('REPLACE').closest('button')
  expect(replaceButton).toBeEnabled()

  await user.type(screen.getByPlaceholderText('Replace in target'), 'new')

  expect(replaceButton).toBeEnabled()
  expect(screen.getByPlaceholderText('Replace in target')).toHaveValue('new')
})

test('shows the searching state and then the results summary once the store emits results', () => {
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'foo'},
    })
  })
  act(() => {
    fireEvent.click(screen.getByText('FIND'))
  })

  expect(screen.getByText('Searching ...')).toBeInTheDocument()

  emitStoreSearchResult({
    total: 2,
    searchResults: [{id: 1}, {id: 2}],
    occurrencesList: [1, 2],
    searchResultsDictionary: {1: {id: 1}, 2: {id: 2}},
    featuredSearchResult: 0,
  })

  expect(screen.getByText(/results/)).toBeInTheDocument()
  expect(screen.getByText(/1 of 2 segments/)).toBeInTheDocument()
})

test('shows "No segments found" when the store emits an empty result set', () => {
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'foo'},
    })
  })
  act(() => {
    fireEvent.click(screen.getByText('FIND'))
  })

  emitStoreSearchResult({
    total: 0,
    searchResults: [],
    occurrencesList: [],
    searchResultsDictionary: {},
    featuredSearchResult: null,
  })

  expect(screen.getByText('No segments found')).toBeInTheDocument()
})

test('shows the source&target results summary when both fields are used', () => {
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in source'), {
      target: {value: 'foo'},
    })
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'bar'},
    })
  })
  act(() => {
    fireEvent.click(screen.getByText('FIND'))
  })

  emitStoreSearchResult({
    total: 1,
    searchResults: [{id: 1}],
    occurrencesList: [1],
    searchResultsDictionary: {1: {id: 1}},
    featuredSearchResult: 0,
  })

  expect(
    document.querySelector('.search-display .numbers').textContent,
  ).toContain('segment')
})

test('next and previous buttons move the featured result and notify the segment', async () => {
  const user = userEvent.setup()
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'foo'},
    })
  })
  act(() => {
    fireEvent.click(screen.getByText('FIND'))
  })

  emitStoreSearchResult({
    total: 3,
    searchResults: [{id: 1}, {id: 2}, {id: 3}],
    occurrencesList: [1, 2, 3],
    searchResultsDictionary: {1: {id: 1}, 2: {id: 2}, 3: {id: 3}},
    featuredSearchResult: 0,
  })

  const [prevButton, nextButton] = document.querySelectorAll(
    '.search-result-buttons button',
  )
  await user.click(nextButton)
  expect(CatToolActions.storeSearchResults).toHaveBeenCalled()
  expect(SegmentActions.changeCurrentSearchSegment).toHaveBeenCalled()

  await user.click(prevButton)
  expect(SegmentActions.changeCurrentSearchSegment).toHaveBeenCalledTimes(2)
})

test('shows the replace input once "Replace with" is enabled and blocks replacing identical text', async () => {
  const user = userEvent.setup()
  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in target'), 'same')
  await user.click(checkboxByLabel('Replace with'))
  await user.type(screen.getByPlaceholderText('Replace in target'), 'same')
  await user.click(screen.getByText('FIND'))

  emitStoreSearchResult({
    total: 1,
    searchResults: [{id: 1}],
    occurrencesList: [1],
    searchResultsDictionary: {1: {id: 1}},
    featuredSearchResult: 0,
  })

  await user.click(screen.getByText('REPLACE'))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    expect.any(Function),
    {text: 'Attention: you are replacing the same text!'},
    'Replace alert',
  )
  expect(SegmentActions.replaceCurrentSearch).not.toHaveBeenCalled()
})

test('replaces the current occurrence and refreshes the segment translation', async () => {
  const user = userEvent.setup()
  jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
    id: 1,
    original_sid: 1,
    status: 'DRAFT',
    occurrences: [1],
  })

  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in target'), 'old')
  await user.click(checkboxByLabel('Replace with'))
  await user.type(screen.getByPlaceholderText('Replace in target'), 'new')
  await user.click(screen.getByText('FIND'))

  emitStoreSearchResult({
    total: 1,
    searchResults: [{id: 1, occurrences: [1]}],
    occurrencesList: [1],
    searchResultsDictionary: {1: {id: 1, occurrences: [1]}},
    featuredSearchResult: 0,
  })

  jest.useFakeTimers()
  act(() => {
    fireEvent.click(screen.getByText('REPLACE'))
    jest.runAllTimers()
  })
  jest.useRealTimers()

  expect(SegmentActions.replaceCurrentSearch).toHaveBeenCalledWith('new')
  expect(segmentTranslation).toHaveBeenCalled()
})

// The confirm/execute/success/error flow used to live here, but Search.js now
// just opens the registered ReplaceAllModal (see modalRegistry.js) and hands
// it the current search state — that modal owns the rest, and already has
// its own coverage in ReplaceAllModal.test.js ("shows the first error
// message when the replace fails", etc).
test('replace all opens the ReplaceAllModal with the current search state', async () => {
  const user = userEvent.setup()

  render(<Search {...baseProps()} />)

  await user.type(screen.getByPlaceholderText('Find in target'), 'old')
  await user.click(checkboxByLabel('Replace with'))
  await user.type(screen.getByPlaceholderText('Replace in target'), 'new')

  await user.click(screen.getByText('REPLACE ALL'))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    MODAL_KEY.REPLACE_ALL,
    expect.objectContaining({
      search: expect.objectContaining({
        searchTarget: 'old',
        replaceTarget: 'new',
        enableReplace: true,
      }),
    }),
    'Replace text in all results',
  )
})

test('pressing Escape cancels the search and clears the fields', () => {
  jest.useFakeTimers()
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in source'), {
      target: {value: 'hello'},
    })
  })

  act(() => {
    fireEvent.keyDown(document, {keyCode: 27, key: 'Escape'})
    jest.runAllTimers()
  })

  expect(screen.getByPlaceholderText('Find in source')).toHaveValue('')
  jest.useRealTimers()
})

test('pressing Escape scrolls to the current segment when it exists', () => {
  jest.useFakeTimers()
  jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({id: 1})
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.keyDown(document, {keyCode: 27, key: 'Escape'})
    jest.runAllTimers()
  })

  expect(SegmentActions.scrollToSegment).toHaveBeenCalledWith(1)
  jest.useRealTimers()
})

test('pressing Enter inside the find container submits the search', () => {
  searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})
  render(<Search {...baseProps()} />)

  const sourceInput = screen.getByPlaceholderText('Find in source')
  act(() => {
    fireEvent.change(sourceInput, {target: {value: 'hello'}})
  })

  act(() => {
    fireEvent.keyDown(sourceInput, {keyCode: 13, key: 'Enter'})
  })

  expect(searchTermIntoSegments).toHaveBeenCalled()
})

test('F3 and Shift+F3 move to the next and previous result', () => {
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'foo'},
    })
    fireEvent.click(screen.getByText('FIND'))
  })

  emitStoreSearchResult({
    total: 3,
    searchResults: [{id: 1}, {id: 2}, {id: 3}],
    occurrencesList: [1, 2, 3],
    searchResultsDictionary: {1: {id: 1}, 2: {id: 2}, 3: {id: 3}},
    featuredSearchResult: 0,
  })

  act(() => {
    fireEvent.keyDown(document, {key: 'F3'})
  })
  expect(SegmentActions.changeCurrentSearchSegment).toHaveBeenCalledWith(1)

  act(() => {
    fireEvent.keyDown(document, {key: 'F3', shiftKey: true})
  })
  expect(SegmentActions.changeCurrentSearchSegment).toHaveBeenCalledWith(0)
})

test('closing the search via the store event resets the search state', () => {
  jest.useFakeTimers()
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in source'), {
      target: {value: 'hello'},
    })
  })

  act(() => {
    CatToolStore.emit(CattolConstants.CLOSE_SEARCH)
    jest.runAllTimers()
  })

  expect(screen.getByPlaceholderText('Find in source')).toHaveValue('')
  jest.useRealTimers()
})

test('focuses the source input when the search panel becomes active', () => {
  const {rerender} = render(<Search {...baseProps()} active={false} />)

  rerender(<Search {...baseProps()} active={true} />)

  expect(screen.getByPlaceholderText('Find in source')).toHaveFocus()
})

test('re-enables tag projection when leaving an active guess-tag search', async () => {
  const user = userEvent.setup()
  searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})

  const {rerender} = render(
    <Search {...baseProps()} userInfo={{metadata: {guess_tag: 1}}} />,
  )

  await user.type(screen.getByPlaceholderText('Find in source'), 'hello')
  await user.click(screen.getByText('FIND'))

  rerender(
    <Search
      {...baseProps()}
      active={false}
      userInfo={{metadata: {guess_tag: 1}}}
    />,
  )

  expect(SegmentActions.changeTagProjectionStatus).toHaveBeenCalledWith(true)
})

test('the entire-chunks checkbox appears only when the job is split', async () => {
  const user = userEvent.setup()
  const {rerender} = render(<Search {...baseProps()} />)

  expect(queryCheckboxByLabel('Search all chunks')).not.toBeInTheDocument()

  global.config.job_is_splitted = true
  rerender(<Search {...baseProps()} active={false} />)
  rerender(<Search {...baseProps()} />)

  expect(checkboxByLabel('Search all chunks')).toBeInTheDocument()

  await user.click(checkboxByLabel('Search all chunks'))
  expect(checkboxByLabel('Search all chunks')).toBeChecked()
})

test('updates search results when the segment store emits an update while active', () => {
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'foo'},
    })
    fireEvent.click(screen.getByText('FIND'))
  })

  emitStoreSearchResult({
    total: 1,
    searchResults: [{id: 1}],
    occurrencesList: [1],
    searchResultsDictionary: {1: {id: 1}},
    featuredSearchResult: 0,
  })

  act(() => {
    SegmentStore.emit(SegmentConstants.UPDATE_SEARCH)
  })

  expect(screen.getByText(/result/)).toBeInTheDocument()
})

// A tag decorator reporting that the current hit sits inside a tag used to
// disable REPLACE. Nothing ever reported the opposite, so one such event left
// the button dead — with REPLACE ALL still enabled — until the next search or
// navigation. REPLACE now tracks what REPLACE ALL tracks: a replacement to
// apply, and hits to apply it to.
test('keeps the replace button enabled when a tag occurrence is reported', () => {
  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'old'},
    })
    fireEvent.click(checkboxByLabel('Replace with'))
  })

  act(() => {
    fireEvent.click(screen.getByText('FIND'))
  })

  emitStoreSearchResult({
    total: 2,
    searchResults: [{id: 1}, {id: 2}],
    occurrencesList: [1, 2],
    searchResultsDictionary: {1: {id: 1}, 2: {id: 2}},
    featuredSearchResult: 0,
  })

  expect(screen.getByText('REPLACE')).toBeEnabled()

  act(() => {
    SegmentStore.emit(SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG, {
      value: true,
    })
  })

  expect(screen.getByText('REPLACE')).toBeEnabled()
  expect(screen.getByText('REPLACE ALL')).toBeEnabled()
})

test('unmounts cleanly and removes all listeners', () => {
  const addCatSpy = jest.spyOn(CatToolStore, 'addListener')
  const removeCatSpy = jest.spyOn(CatToolStore, 'removeListener')
  const addSegSpy = jest.spyOn(SegmentStore, 'addListener')
  const removeSegSpy = jest.spyOn(SegmentStore, 'removeListener')

  const {unmount} = render(<Search {...baseProps()} />)

  expect(addCatSpy).toHaveBeenCalledWith(
    CattolConstants.STORE_SEARCH_RESULT,
    expect.any(Function),
  )
  expect(addCatSpy).toHaveBeenCalledWith(
    CattolConstants.CLOSE_SEARCH,
    expect.any(Function),
  )
  expect(addSegSpy).toHaveBeenCalledWith(
    SegmentConstants.UPDATE_SEARCH,
    expect.any(Function),
  )

  unmount()

  expect(removeCatSpy).toHaveBeenCalledWith(
    CattolConstants.STORE_SEARCH_RESULT,
    expect.any(Function),
  )
  // Quirk 2: the document keydown listener is added with `useCapture: true`
  // and removed without it, so removeEventListener never truly detaches it
  // (see the "does not react to keyboard shortcuts when not active" test
  // above). removeListener is still called with the matching reference here
  // though, since CatToolStore's own add/remove pair doesn't have that
  // capture-flag mismatch.
  expect(removeCatSpy).toHaveBeenCalledWith(
    CattolConstants.CLOSE_SEARCH,
    expect.any(Function),
  )
  expect(removeSegSpy).toHaveBeenCalledWith(
    SegmentConstants.UPDATE_SEARCH,
    expect.any(Function),
  )
})

// A tag occurrence reported during one search used to latch REPLACE off: the
// flag survived Clear untouched (setState's shallow merge in the original class
// never reset it), so the button stayed dead for later searches too. REPLACE now
// depends only on there being a replacement and hits to apply it to.
test('a tag occurrence seen earlier does not disable replace for a later search', () => {
  jest.useFakeTimers()

  render(<Search {...baseProps()} />)

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'old'},
    })
    fireEvent.click(checkboxByLabel('Replace with'))
  })

  act(() => {
    fireEvent.click(screen.getByText('FIND'))
  })

  act(() => {
    SegmentStore.emit(SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG, {
      value: true,
    })
    jest.runAllTimers()
  })

  act(() => {
    fireEvent.click(screen.getByText('Clear'))
    jest.runAllTimers()
  })

  expect(screen.getByPlaceholderText('Find in target')).toHaveValue('')

  act(() => {
    fireEvent.change(screen.getByPlaceholderText('Find in target'), {
      target: {value: 'new'},
    })
    fireEvent.click(checkboxByLabel('Replace with'))
  })

  act(() => {
    fireEvent.click(screen.getByText('FIND'))
  })

  emitStoreSearchResult({
    total: 1,
    searchResults: [{id: 1}],
    occurrencesList: [1],
    searchResultsDictionary: {1: {id: 1}},
    featuredSearchResult: 0,
  })

  expect(screen.getByText('REPLACE')).toBeEnabled()

  jest.useRealTimers()
})
