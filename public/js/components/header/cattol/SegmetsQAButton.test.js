import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {SegmentsQAButton} from './SegmetsQAButton'
import CatToolActions from '../../../actions/CatToolActions'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'

jest.mock('../../../actions/CatToolActions')
jest.mock('./search/searchUtils', () => ({
  searchOpen: false,
}))

const buildWarnings = ({error = 0, warning = 0, info = 0, total} = {}) => ({
  matecat: {
    total: total ?? error + warning + info,
    ERROR: {total: error},
    WARNING: {total: warning},
    INFO: {total: info},
  },
})

beforeEach(() => {
  jest.clearAllMocks()
})

test('renders disabled with the "well done" tooltip when there are no issues', () => {
  render(<SegmentsQAButton />)
  const button = screen.getByRole('button')
  expect(button).toBeDisabled()
  expect(button).toHaveAttribute(
    'aria-label',
    'Well done, no errors found!',
  )
})

test('enables the button and shows the error badge when there are error warnings', () => {
  render(<SegmentsQAButton />)
  act(() => {
    SegmentStore.emit(
      SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      buildWarnings({error: 3}),
    )
  })
  const button = screen.getByRole('button')
  expect(button).not.toBeDisabled()
  expect(button).toHaveAttribute(
    'aria-label',
    'Click to see the segments with potential issues',
  )
  expect(screen.getByText('3')).toHaveClass('button-badge-error')

  fireEvent.click(button)
  expect(CatToolActions.toggleQaIssues).toHaveBeenCalledTimes(1)
})

test('shows the warning badge class when only warning issues exist', () => {
  render(<SegmentsQAButton />)
  act(() => {
    SegmentStore.emit(
      SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      buildWarnings({warning: 2}),
    )
  })
  expect(screen.getByText('2')).toHaveClass('button-badge-warning')
})

test('shows the info badge class when only info issues exist', () => {
  render(<SegmentsQAButton />)
  act(() => {
    SegmentStore.emit(
      SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      buildWarnings({info: 1}),
    )
  })
  expect(screen.getByText('1')).toHaveClass('button-badge-info')
})

test('toggles the open icon state when the qaComponent container is toggled from the store', () => {
  render(<SegmentsQAButton />)
  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'qaComponent')
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
  act(() => {
    CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
})
