import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {SearchButton} from './SearchButton'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'
import SearchUtils from './search/searchUtils'

jest.mock('react-hotkeys-hook', () => ({
  useHotkeys: jest.fn(),
}))

jest.mock('./search/searchUtils', () => ({
  searchOpen: false,
  searchEnabled: true,
  toggleSearch: jest.fn(),
}))

beforeEach(() => {
  global.config = {}
  SearchUtils.searchOpen = false
  SearchUtils.searchEnabled = true
  jest.clearAllMocks()
})

test('renders nothing when search is disabled', () => {
  SearchUtils.searchEnabled = false
  const {container} = render(<SearchButton />)
  expect(container.firstChild).toBeNull()
})

test('toggles search when clicked', () => {
  render(<SearchButton />)
  fireEvent.click(screen.getByRole('button'))
  expect(SearchUtils.toggleSearch).toHaveBeenCalledTimes(1)
})

test('renders the filled icon variant when search is already open', () => {
  SearchUtils.searchOpen = true
  render(<SearchButton />)
  fireEvent.click(screen.getByRole('button'))
  expect(SearchUtils.toggleSearch).toHaveBeenCalledTimes(1)
})

test('toggles open state when the search container is toggled from the store', () => {
  render(<SearchButton />)
  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'search')
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
})

test('closes the search icon when another container is shown or the subheader closes', () => {
  SearchUtils.searchOpen = true
  render(<SearchButton />)
  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
  act(() => {
    CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
})
