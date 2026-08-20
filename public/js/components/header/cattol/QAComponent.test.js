import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import QAComponent from './QAComponent'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'

jest.mock('../../../actions/SegmentActions')

const emitWarnings = (matecat) =>
  act(() => {
    SegmentStore.emit(SegmentConstants.UPDATE_GLOBAL_WARNINGS, {matecat})
  })

beforeEach(() => {
  global.config = {
    lexiqaServer: 'https://lexiqa.example.com',
    id_job: '42',
    password: 'pass123',
    isReview: false,
  }
  jest.clearAllMocks()
})

test('renders nothing when not active', () => {
  const {container} = render(<QAComponent active={false} isReview={false} />)
  expect(container.firstChild).toBeNull()
})

test('renders nothing when there are no warnings yet', () => {
  const {container} = render(<QAComponent active={true} isReview={false} />)
  expect(container.firstChild).toBeNull()
})

test('renders error, warning and info categories and lets the user open a category', () => {
  render(<QAComponent active={true} isReview={false} />)

  emitWarnings({
    total: 6,
    ERROR: {
      total: 2,
      Categories: {TAGS: [1, 2], GLOSSARY: [3]},
    },
    WARNING: {
      total: 2,
      Categories: {TAGS: [4], MISMATCH: [5]},
    },
    INFO: {
      total: 1,
      Categories: {lexiqa: [6]},
    },
  })

  expect(screen.getByText('Segments with:')).toBeInTheDocument()
  expect(screen.getByText(/Tag errors/)).toBeInTheDocument()
  expect(screen.getByText('Glossary')).toBeInTheDocument()
  expect(screen.getByText(/Tag warnings/)).toBeInTheDocument()
  expect(screen.getByText('Repetitions with:')).toBeInTheDocument()
  expect(screen.getByText('Lexiqa')).toBeInTheDocument()

  fireEvent.click(screen.getByText('Glossary'))
  expect(SegmentActions.openSegment).toHaveBeenCalledWith(3)

  // navigator becomes visible once a category is selected
  expect(screen.getByText('1')).toBeInTheDocument()
})

test('navigates through the selected category with the arrow buttons', () => {
  render(<QAComponent active={true} isReview={false} />)

  emitWarnings({
    total: 2,
    ERROR: {total: 2, Categories: {GLOSSARY: [10, 20]}},
    WARNING: {total: 0, Categories: {}},
    INFO: {total: 0, Categories: {}},
  })

  fireEvent.click(screen.getByText('Glossary'))
  expect(SegmentActions.openSegment).toHaveBeenCalledWith(10)

  const buttons = screen.getAllByRole('button')
  const [prevButton, nextButton] = buttons.slice(-2)

  fireEvent.click(nextButton)
  expect(SegmentActions.openSegment).toHaveBeenCalledWith(20)

  fireEvent.click(nextButton)
  expect(SegmentActions.openSegment).toHaveBeenCalledWith(10)

  fireEvent.click(prevButton)
  expect(SegmentActions.openSegment).toHaveBeenCalledWith(20)
})

test('shows the lexiqa report links once the lexiqa info category is selected', () => {
  render(<QAComponent active={true} isReview={false} />)

  emitWarnings({
    total: 1,
    ERROR: {total: 0, Categories: {}},
    WARNING: {total: 0, Categories: {}},
    INFO: {total: 1, Categories: {lexiqa: [7]}},
  })

  fireEvent.click(screen.getByText('Lexiqa'))

  const guideLink = screen.getByText('Guide')
  expect(guideLink).toHaveAttribute(
    'href',
    'https://lexiqa.example.com/documentation.html',
  )
  const reportLink = screen.getByText('Report')
  expect(reportLink.getAttribute('href')).toContain(
    '/errorreport?id=',
  )
  expect(reportLink.getAttribute('href')).toContain('-42-pass123&type=translate')
})

test('renders nothing once the component is deactivated again', () => {
  const {container, rerender} = render(
    <QAComponent active={true} isReview={false} />,
  )
  emitWarnings({
    total: 1,
    ERROR: {total: 1, Categories: {GLOSSARY: [1]}},
    WARNING: {total: 0, Categories: {}},
    INFO: {total: 0, Categories: {}},
  })
  expect(container.firstChild).not.toBeNull()

  rerender(<QAComponent active={false} isReview={false} />)
  expect(container.firstChild).toBeNull()
})
