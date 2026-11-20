import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {Radio, RADIO_SIZE} from './Radio'

const defaultProps = {
  name: 'option',
  onChange: jest.fn(),
}

describe('Radio', () => {
  afterEach(() => jest.clearAllMocks())

  it('renders an unchecked radio input by default', () => {
    render(<Radio {...defaultProps} />)
    expect(screen.getByRole('radio')).not.toBeChecked()
  })

  it('renders a checked radio input when checked is true', () => {
    render(<Radio {...defaultProps} checked />)
    expect(screen.getByRole('radio')).toBeChecked()
  })

  it('sets the name and id from the name prop', () => {
    render(<Radio {...defaultProps} />)
    const input = screen.getByRole('radio')
    expect(input).toHaveAttribute('name', 'option')
    expect(input).toHaveAttribute('id', 'option')
  })

  it('applies the medium size class by default', () => {
    render(<Radio {...defaultProps} />)
    expect(screen.getByRole('radio')).toHaveClass('medium')
  })

  it('applies the small size class when size is RADIO_SIZE.SMALL', () => {
    render(<Radio {...defaultProps} size={RADIO_SIZE.SMALL} />)
    expect(screen.getByRole('radio')).toHaveClass('small')
  })

  it('renders the label when provided and associates it with the input', () => {
    render(<Radio {...defaultProps} label="Option A" />)
    expect(screen.getByLabelText('Option A')).toBe(screen.getByRole('radio'))
  })

  it('does not render a label element when label is not provided', () => {
    render(<Radio {...defaultProps} />)
    expect(screen.queryByText('Option A')).not.toBeInTheDocument()
  })

  it('disables the input when disabled is true', () => {
    render(<Radio {...defaultProps} disabled />)
    expect(screen.getByRole('radio')).toBeDisabled()
  })

  it('sets the data-testid from the testId prop', () => {
    render(<Radio {...defaultProps} testId="option-radio" />)
    expect(screen.getByTestId('option-radio')).toBe(screen.getByRole('radio'))
  })

  it('calls onChange with true when an unchecked radio is clicked', () => {
    const onChange = jest.fn()
    render(<Radio {...defaultProps} onChange={onChange} checked={false} />)
    fireEvent.click(screen.getByRole('radio'))
    expect(onChange).toHaveBeenCalledWith(true)
  })

  it('does not call onChange when an already-checked radio is clicked', () => {
    const onChange = jest.fn()
    render(<Radio {...defaultProps} onChange={onChange} checked />)
    fireEvent.click(screen.getByRole('radio'))
    expect(onChange).not.toHaveBeenCalled()
  })

  it('forwards extra props to the input element', () => {
    render(<Radio {...defaultProps} data-group="plan" />)
    expect(screen.getByRole('radio')).toHaveAttribute('data-group', 'plan')
  })
})
