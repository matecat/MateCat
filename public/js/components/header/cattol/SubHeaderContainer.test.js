import React from 'react'
import {render, screen, act} from '@testing-library/react'
import SubHeaderContainer from './SubHeaderContainer'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'

jest.mock('./search/Search', () => ({active}) => (
  <div data-testid="search" data-active={String(!!active)} />
))
jest.mock('./segment_filter/SegmentsFilter', () => ({active}) => (
  <div data-testid="segments-filter" data-active={String(!!active)} />
))
jest.mock('./QAComponent', () => ({active}) => (
  <div data-testid="qa-component" data-active={String(!!active)} />
))
jest.mock('./bulk_selection_bar/BulkSelectionBar', () => ({active}) => (
  <div data-testid="bulk-selection-bar" data-active={String(!!active)} />
))

beforeEach(() => {
  global.config = {isReview: false, searchable_statuses: []}
})

test('always renders search, qa and the bulk selection bar', () => {
  render(<SubHeaderContainer filtersEnabled={false} />)
  expect(screen.getByTestId('search')).toBeInTheDocument()
  expect(screen.getByTestId('qa-component')).toBeInTheDocument()
  expect(screen.getByTestId('bulk-selection-bar')).toBeInTheDocument()
  expect(screen.getByTestId('bulk-selection-bar')).toHaveAttribute(
    'data-active',
    'true',
  )
})

test('only renders the segments filter when filtersEnabled is true', () => {
  const {rerender} = render(<SubHeaderContainer filtersEnabled={false} />)
  expect(screen.queryByTestId('segments-filter')).toBeNull()

  rerender(<SubHeaderContainer filtersEnabled={true} />)
  expect(screen.getByTestId('segments-filter')).toBeInTheDocument()
})

test('shows the requested container and hides the others when told to via the store', () => {
  render(<SubHeaderContainer filtersEnabled={true} />)

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')
  expect(screen.getByTestId('qa-component')).toHaveAttribute(
    'data-active',
    'false',
  )

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'qaComponent')
  })
  expect(screen.getByTestId('qa-component')).toHaveAttribute(
    'data-active',
    'true',
  )
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'false')

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
  })
  expect(screen.getByTestId('segments-filter')).toHaveAttribute(
    'data-active',
    'true',
  )
})

test('toggles a container open and closed via the store', () => {
  render(<SubHeaderContainer filtersEnabled={true} />)

  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'search')
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')

  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'search')
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'false')
})

test('unmounting removes the CatToolStore listeners', () => {
  const baselineShow = CatToolStore.listenerCount(
    CatToolConstants.SHOW_CONTAINER,
  )
  const baselineToggle = CatToolStore.listenerCount(
    CatToolConstants.TOGGLE_CONTAINER,
  )
  const baselineClose = CatToolStore.listenerCount(
    CatToolConstants.CLOSE_SUBHEADER,
  )

  const {unmount} = render(<SubHeaderContainer filtersEnabled={true} />)

  expect(CatToolStore.listenerCount(CatToolConstants.SHOW_CONTAINER)).toBe(
    baselineShow + 1,
  )
  expect(CatToolStore.listenerCount(CatToolConstants.TOGGLE_CONTAINER)).toBe(
    baselineToggle + 1,
  )
  expect(CatToolStore.listenerCount(CatToolConstants.CLOSE_SUBHEADER)).toBe(
    baselineClose + 1,
  )

  unmount()

  expect(CatToolStore.listenerCount(CatToolConstants.SHOW_CONTAINER)).toBe(
    baselineShow,
  )
  expect(CatToolStore.listenerCount(CatToolConstants.TOGGLE_CONTAINER)).toBe(
    baselineToggle,
  )
  expect(CatToolStore.listenerCount(CatToolConstants.CLOSE_SUBHEADER)).toBe(
    baselineClose,
  )
})

test('closes every container when the subheader is closed', () => {
  render(<SubHeaderContainer filtersEnabled={true} />)

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')

  act(() => {
    CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'false')
  expect(screen.getByTestId('qa-component')).toHaveAttribute(
    'data-active',
    'false',
  )
  expect(screen.getByTestId('segments-filter')).toHaveAttribute(
    'data-active',
    'false',
  )
})
