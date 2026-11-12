import React from 'react'
import {act, fireEvent, render, screen} from '@testing-library/react'
import ReviewExtendedIssuesTabGroup from './ReviewExtendedIssuesTabGroup'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'

jest.mock('../../stores/SegmentStore', () => {
  const {EventEmitter} = require('events')
  const store = new EventEmitter()
  store.setMaxListeners(0)
  return store
})

const tabs = [
  {
    id: 'r1',
    label: 'R1 issues',
    disabled: false,
    content: <div>R1 content</div>,
  },
  {
    id: 'r2',
    label: 'R2 issues',
    disabled: true,
    content: <div>R2 content</div>,
  },
]

describe('ReviewExtendedIssuesTabGroup', () => {
  afterEach(() => {
    SegmentStore.removeAllListeners()
  })

  test('renders all tab labels', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r1" />)
    expect(screen.getByText('R1 issues')).toBeInTheDocument()
    expect(screen.getByText('R2 issues')).toBeInTheDocument()
  })

  test('shows content of the tab matching selectedTabId initially', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r2" />)
    expect(screen.getByText('R2 content')).toBeInTheDocument()
    expect(screen.queryByText('R1 content')).not.toBeInTheDocument()
  })

  test('falls back to first tab when selectedTabId matches nothing', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="nope" />)
    expect(screen.getByText('R1 content')).toBeInTheDocument()
  })

  test('falls back to first tab when selectedTabId is not provided', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} />)
    expect(screen.getByText('R1 content')).toBeInTheDocument()
  })

  test('applies active class to the selected tab only', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r1" />)
    expect(screen.getByText('R1 issues')).toHaveClass('active')
    expect(screen.getByText('R2 issues')).not.toHaveClass('active')
  })

  test('applies disabled class to disabled tabs', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r1" />)
    expect(screen.getByText('R2 issues')).toHaveClass('disabled')
    expect(screen.getByText('R1 issues')).not.toHaveClass('disabled')
  })

  test('clicking a tab switches the active tab and displayed content', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r1" />)
    fireEvent.click(screen.getByText('R2 issues'))
    expect(screen.getByText('R2 issues')).toHaveClass('active')
    expect(screen.getByText('R2 content')).toBeInTheDocument()
    expect(screen.queryByText('R1 content')).not.toBeInTheDocument()
  })

  test('clicking the already-active tab keeps it active', () => {
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r1" />)
    fireEvent.click(screen.getByText('R1 issues'))
    expect(screen.getByText('R1 issues')).toHaveClass('active')
    expect(screen.getByText('R1 content')).toBeInTheDocument()
  })

  test('switches to r1 on ISSUE_ADDED when there is no second revision pass', () => {
    global.config = {secondRevisionsCount: 0}
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r2" />)
    act(() => {
      SegmentStore.emit(SegmentConstants.ISSUE_ADDED)
    })
    expect(screen.getByText('R1 issues')).toHaveClass('active')
    expect(screen.getByText('R1 content')).toBeInTheDocument()
  })

  test('switches to r2 on ISSUE_ADDED when a second revision pass is enabled', () => {
    global.config = {secondRevisionsCount: 1}
    render(<ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r1" />)
    act(() => {
      SegmentStore.emit(SegmentConstants.ISSUE_ADDED)
    })
    expect(screen.getByText('R2 issues')).toHaveClass('active')
    expect(screen.getByText('R2 content')).toBeInTheDocument()
  })

  test('removes the ISSUE_ADDED listener on unmount', () => {
    global.config = {secondRevisionsCount: 0}
    const {unmount} = render(
      <ReviewExtendedIssuesTabGroup tabs={tabs} selectedTabId="r1" />,
    )
    unmount()
    expect(() => {
      act(() => {
        SegmentStore.emit(SegmentConstants.ISSUE_ADDED)
      })
    }).not.toThrow()
    expect(SegmentStore.listenerCount(SegmentConstants.ISSUE_ADDED)).toBe(0)
  })
})
