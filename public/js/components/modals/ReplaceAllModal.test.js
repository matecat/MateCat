import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {ReplaceAllModal} from './ReplaceAllModal'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import ModalsActions from '../../actions/ModalsActions'
import CatToolActions from '../../actions/CatToolActions'
import SearchUtils from '../header/cattol/search/searchUtils'
import AlertModal from './AlertModal'

jest.mock('../../stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    getCurrentSegmentId: jest.fn(),
  },
}))

jest.mock('../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    removeAllSegments: jest.fn(),
  },
}))

jest.mock('../../actions/ModalsActions', () => ({
  __esModule: true,
  default: {
    onCloseModal: jest.fn(),
    showModalComponent: jest.fn(),
  },
}))

jest.mock('../../actions/CatToolActions', () => ({
  __esModule: true,
  default: {
    onRender: jest.fn(),
    storeSearchResults: jest.fn(),
  },
}))

jest.mock('../header/cattol/search/searchUtils', () => ({
  __esModule: true,
  default: {
    execReplaceAll: jest.fn(),
  },
}))

const search = {
  searchSource: 'foo',
  searchTarget: 'bar',
  replaceTarget: 'baz',
  selectStatus: 'all',
  matchCase: false,
  exactMatch: false,
}

describe('ReplaceAllModal', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    config.isReview = false
  })

  test('renders the confirmation copy, checkbox and actions', () => {
    render(<ReplaceAllModal search={search} />)

    expect(
      screen.getByText(/You are about to replace this text/),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Include locked segments')).not.toBeChecked()
    expect(screen.getByText('Cancel')).toBeInTheDocument()
    expect(screen.getByText('Replace all')).toBeInTheDocument()
  })

  test('shows the draft status by default', () => {
    render(<ReplaceAllModal search={search} />)

    expect(screen.getByText('draft')).toBeInTheDocument()
  })

  test('shows the translated status when the job is in review', () => {
    config.isReview = true
    render(<ReplaceAllModal search={search} />)

    expect(screen.getByText('translated')).toBeInTheDocument()
  })

  test('cancel closes the modal without running the replace', () => {
    render(<ReplaceAllModal search={search} />)

    fireEvent.click(screen.getByText('Cancel'))

    expect(ModalsActions.onCloseModal).toHaveBeenCalled()
    expect(SearchUtils.execReplaceAll).not.toHaveBeenCalled()
  })

  test('replace all runs the search params through execReplaceAll with includeLocked false by default', async () => {
    SearchUtils.execReplaceAll.mockResolvedValue({})
    render(<ReplaceAllModal search={search} />)

    await act(async () => {
      fireEvent.click(screen.getByText('Replace all'))
    })

    expect(SearchUtils.execReplaceAll).toHaveBeenCalledWith(search, false)
    expect(ModalsActions.onCloseModal).toHaveBeenCalled()
    expect(CatToolActions.storeSearchResults).toHaveBeenCalledWith({
      total: 0,
      searchResults: [],
      occurrencesList: [],
      searchResultsDictionary: {},
      featuredSearchResult: null,
    })
  })

  test('replace all passes includeLocked true when the checkbox is checked', async () => {
    SearchUtils.execReplaceAll.mockResolvedValue({})
    render(<ReplaceAllModal search={search} />)

    fireEvent.click(screen.getByLabelText('Include locked segments'))
    await act(async () => {
      fireEvent.click(screen.getByText('Replace all'))
    })

    expect(SearchUtils.execReplaceAll).toHaveBeenCalledWith(search, true)
  })

  test('reloads the current segment on a successful replace', async () => {
    SegmentStore.getCurrentSegmentId.mockReturnValue('1-1')
    SearchUtils.execReplaceAll.mockResolvedValue({})
    render(<ReplaceAllModal search={search} />)

    await act(async () => {
      fireEvent.click(screen.getByText('Replace all'))
    })

    expect(SegmentActions.removeAllSegments).toHaveBeenCalled()
    expect(CatToolActions.onRender).toHaveBeenCalledWith({
      firstLoad: false,
      segmentToOpen: '1-1',
    })
  })

  test('shows the first error message when the replace fails', async () => {
    SearchUtils.execReplaceAll.mockRejectedValue([{message: 'boom'}])
    render(<ReplaceAllModal search={search} />)

    await act(async () => {
      fireEvent.click(screen.getByText('Replace all'))
    })

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      AlertModal,
      {text: 'boom'},
      'Replace all alert',
    )
  })

  test('shows a generic error message when the replace fails without errors', async () => {
    SearchUtils.execReplaceAll.mockRejectedValue(undefined)
    render(<ReplaceAllModal search={search} />)

    await act(async () => {
      fireEvent.click(screen.getByText('Replace all'))
    })

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      AlertModal,
      {text: 'We got an error, please contact support'},
      'Replace all alert',
    )
  })
})
