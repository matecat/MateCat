import React, {createRef} from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Tooltip, {TOOLTIP_POSITION} from './Tooltip'

describe('Tooltip', () => {
  beforeEach(() => jest.useFakeTimers())
  afterEach(() => {
    jest.useRealTimers()
    jest.restoreAllMocks()
  })

  // The tooltip has no ref of its own on the child: it reads the ref the
  // caller put on it to find where to show up. React 19 moved that ref from
  // element.ref to element.props.ref and warns when the old one is read.
  test.each([
    [TOOLTIP_POSITION.TOP, {top: '193px', left: '120px'}],
    [TOOLTIP_POSITION.BOTTOM, {top: '227px', left: '120px'}],
    [TOOLTIP_POSITION.LEFT, {top: '210px', left: '93px'}],
    [TOOLTIP_POSITION.RIGHT, {top: '210px', left: '147px'}],
  ])(
    'places a %s tooltip next to the child it reads from the child ref',
    async (position, expectedStyle) => {
      const consoleError = jest.spyOn(console, 'error')
      const user = userEvent.setup({advanceTimers: jest.advanceTimersByTime})
      const childRef = createRef()
      render(
        <Tooltip content="Tooltip text" position={position}>
          <button ref={childRef}>Trigger</button>
        </Tooltip>,
      )
      childRef.current.getBoundingClientRect = () => ({
        x: 100,
        y: 200,
        width: 40,
        height: 20,
      })

      await user.hover(screen.getByRole('button', {name: 'Trigger'}))
      act(() => jest.advanceTimersByTime(300))

      expect(screen.getByText('Tooltip text')).toHaveStyle(expectedStyle)
      expect(consoleError.mock.calls.flat().join(' ')).not.toContain(
        'element.ref',
      )
    },
  )
})
