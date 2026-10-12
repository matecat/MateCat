import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {ButtonCopy} from './ButtonCopy'

describe('ButtonCopy', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    act(() => jest.runOnlyPendingTimers())
    jest.useRealTimers()
  })

  it('renders a button', () => {
    render(<ButtonCopy />)
    expect(screen.getByRole('button')).toBeInTheDocument()
  })

  it('calls onClick when clicked', () => {
    const onClick = jest.fn()
    render(<ButtonCopy onClick={onClick} />)
    fireEvent.click(screen.getByRole('button'))
    expect(onClick).toHaveBeenCalled()
  })

  it('does not throw when clicked without an onClick prop', () => {
    render(<ButtonCopy />)
    expect(() => fireEvent.click(screen.getByRole('button'))).not.toThrow()
  })

  it('reverts to the not-clicked state after the timeout', () => {
    render(<ButtonCopy tooltip="Copy" />)
    fireEvent.click(screen.getByRole('button'))
    expect(screen.getByRole('button')).toHaveAttribute('aria-label', 'Copied!')

    act(() => jest.advanceTimersByTime(500))

    expect(screen.getByRole('button')).toHaveAttribute('aria-label', 'Copy')
  })

  it('shows the tooltip prop before being clicked', () => {
    render(<ButtonCopy tooltip="Copy to clipboard" />)
    expect(screen.getByRole('button')).toHaveAttribute(
      'aria-label',
      'Copy to clipboard',
    )
  })

  it('shows the default tooltipCopied label after being clicked', () => {
    render(<ButtonCopy tooltip="Copy" />)
    fireEvent.click(screen.getByRole('button'))
    expect(screen.getByRole('button')).toHaveAttribute('aria-label', 'Copied!')
  })

  it('supports a custom tooltipCopied label', () => {
    render(<ButtonCopy tooltip="Copy" tooltipCopied="Done!" />)
    fireEvent.click(screen.getByRole('button'))
    expect(screen.getByRole('button')).toHaveAttribute('aria-label', 'Done!')
  })

  it('forwards extra props to the underlying Button', () => {
    render(<ButtonCopy testId="my-copy-button" />)
    expect(screen.getByTestId('my-copy-button')).toBeInTheDocument()
  })

  it('clears the pending timeout on unmount without throwing', () => {
    const {unmount} = render(<ButtonCopy />)
    fireEvent.click(screen.getByRole('button'))
    expect(() => unmount()).not.toThrow()
  })
})
