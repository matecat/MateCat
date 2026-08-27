import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'

const mockIsMacOS = jest.fn(() => false)

jest.mock('./SegmentWrapper', () => ({
  __esModule: true,
  default: ({isTarget}) => (
    <div data-testid={isTarget ? 'wrapper-target' : 'wrapper-source'} />
  ),
}))

jest.mock('../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {copySourceToTarget: jest.fn()},
}))

jest.mock('../../utils/Utils', () => ({
  isMacOS: (...args) => mockIsMacOS(...args),
}))

jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {
    cattol: {
      events: {
        copySource: {
          keystrokes: {mac: 'cmd+i', standard: 'ctrl+i'},
        },
      },
    },
  },
}))

import {SegmentBody} from './SegmentBody'
import SegmentBodyDefault from './SegmentBody'
import {SegmentContext} from './SegmentContext'
import SegmentActions from '../../actions/SegmentActions'

function renderBody(props = {}, segment = {sid: '12'}) {
  return render(
    <SegmentContext.Provider value={{segment}}>
      <SegmentBody {...props} />
    </SegmentContext.Provider>,
  )
}

beforeEach(() => {
  mockIsMacOS.mockReset()
  mockIsMacOS.mockReturnValue(false)
  SegmentActions.copySourceToTarget.mockClear()
})

test('exports the component as both a named and a default export', () => {
  expect(SegmentBodyDefault).toBe(SegmentBody)
})

test('renders the source and target wrappers plus the copy control', () => {
  const {container} = renderBody()

  expect(screen.getByTestId('wrapper-source')).toBeTruthy()
  expect(screen.getByTestId('wrapper-target')).toBeTruthy()
  expect(container.querySelector('.text.segment-body-content')).not.toBeNull()
  expect(container.querySelector('.outersource')).not.toBeNull()
  expect(container.querySelector('.status-container .status')).not.toBeNull()
})

test('shows the standard copy-source shortcut on non-Mac platforms', () => {
  const {container} = renderBody()
  expect(container.querySelector('.copy p').textContent).toBe('CTRL+I')
})

test('shows the Mac copy-source shortcut on macOS', () => {
  mockIsMacOS.mockReturnValue(true)
  const {container} = renderBody()
  expect(container.querySelector('.copy p').textContent).toBe('CMD+I')
})

test('copying the source dispatches copySourceToTarget and stops the anchor', () => {
  const {container} = renderBody({}, {sid: '99'})

  const copy = container.querySelector('.copy')
  const clickEvent = new MouseEvent('click', {bubbles: true, cancelable: true})
  fireEvent(copy, clickEvent)

  expect(SegmentActions.copySourceToTarget).toHaveBeenCalledWith('99')
  expect(clickEvent.defaultPrevented).toBe(true)
})

test('forwards clicks on the body to the onClick prop', () => {
  const onClick = jest.fn()
  const {container} = renderBody({onClick})

  fireEvent.click(container.querySelector('.text.segment-body-content'))
  expect(onClick).toHaveBeenCalledTimes(1)
})
