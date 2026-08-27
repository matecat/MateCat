import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {NumericStepper} from './NumericStepper'

const defaultProps = {
  value: 5,
  onChange: jest.fn(),
  minimumValue: 0,
  maximumValue: 10,
  name: 'stepper',
}

describe('NumericStepper', () => {
  afterEach(() => jest.clearAllMocks())

  it('renders the current value in the input', () => {
    render(<NumericStepper {...defaultProps} />)
    expect(screen.getByRole('textbox')).toHaveValue('5')
  })

  it('shows the placeholder when not focused and a placeholder is given', () => {
    render(<NumericStepper {...defaultProps} valuePlaceholder="Enter value" />)
    expect(screen.getByRole('textbox')).toHaveValue('Enter value')
  })

  it('shows the real value once the input is focused', () => {
    render(<NumericStepper {...defaultProps} valuePlaceholder="Enter value" />)
    const input = screen.getByRole('textbox')
    fireEvent.focus(input)
    expect(input).toHaveValue('5')
  })

  it('calls onChange with value + stepValue when the increase button is clicked', () => {
    const onChange = jest.fn()
    const {container} = render(
      <NumericStepper {...defaultProps} onChange={onChange} stepValue={2} />,
    )
    const buttons = container.querySelectorAll('button')
    fireEvent.click(buttons[0])
    expect(onChange).toHaveBeenCalledWith(7)
  })

  it('caps the increase at maximumValue', () => {
    const onChange = jest.fn()
    const {container} = render(
      <NumericStepper
        {...defaultProps}
        value={9}
        onChange={onChange}
        stepValue={5}
      />,
    )
    const buttons = container.querySelectorAll('button')
    fireEvent.click(buttons[0])
    expect(onChange).toHaveBeenCalledWith(10)
  })

  it('calls onChange with value - stepValue when the decrease button is clicked', () => {
    const onChange = jest.fn()
    const {container} = render(
      <NumericStepper {...defaultProps} onChange={onChange} stepValue={2} />,
    )
    const buttons = container.querySelectorAll('button')
    fireEvent.click(buttons[1])
    expect(onChange).toHaveBeenCalledWith(3)
  })

  it('caps the decrease at minimumValue', () => {
    const onChange = jest.fn()
    const {container} = render(
      <NumericStepper
        {...defaultProps}
        value={1}
        onChange={onChange}
        stepValue={5}
      />,
    )
    const buttons = container.querySelectorAll('button')
    fireEvent.click(buttons[1])
    expect(onChange).toHaveBeenCalledWith(0)
  })

  it('calls onChange with the parsed integer when typing digits', () => {
    const onChange = jest.fn()
    render(<NumericStepper {...defaultProps} onChange={onChange} />)
    const input = screen.getByRole('textbox')
    fireEvent.change(input, {target: {value: '42'}})
    expect(onChange).toHaveBeenCalledWith(42)
    expect(input).toHaveValue('42')
  })

  it('clears the input when the typed value is emptied, without calling onChange', () => {
    const onChange = jest.fn()
    render(<NumericStepper {...defaultProps} onChange={onChange} />)
    const input = screen.getByRole('textbox')
    fireEvent.change(input, {target: {value: ''}})
    expect(input).toHaveValue('')
    expect(onChange).not.toHaveBeenCalled()
  })

  it('ignores non-digit input', () => {
    const onChange = jest.fn()
    render(<NumericStepper {...defaultProps} onChange={onChange} />)
    const input = screen.getByRole('textbox')
    fireEvent.change(input, {target: {value: 'abc'}})
    expect(onChange).not.toHaveBeenCalled()
    expect(input).toHaveValue('5')
  })

  it('clamps below minimumValue on blur', () => {
    const onChange = jest.fn()
    render(<NumericStepper {...defaultProps} value={-3} onChange={onChange} />)
    const input = screen.getByRole('textbox')
    fireEvent.focus(input)
    fireEvent.blur(input)
    expect(onChange).toHaveBeenCalledWith(0)
  })

  it('clamps above maximumValue on blur', () => {
    const onChange = jest.fn()
    render(<NumericStepper {...defaultProps} value={99} onChange={onChange} />)
    const input = screen.getByRole('textbox')
    fireEvent.focus(input)
    fireEvent.blur(input)
    expect(onChange).toHaveBeenCalledWith(10)
  })

  it('does not change the value on blur when within range', () => {
    const onChange = jest.fn()
    render(<NumericStepper {...defaultProps} value={5} onChange={onChange} />)
    const input = screen.getByRole('textbox')
    fireEvent.focus(input)
    fireEvent.blur(input)
    expect(onChange).toHaveBeenCalledWith(5)
  })

  it('blurs the input when Enter is pressed', () => {
    render(<NumericStepper {...defaultProps} />)
    const input = screen.getByRole('textbox')
    act(() => input.focus())
    expect(input).toHaveFocus()
    fireEvent.keyUp(input, {key: 'Enter'})
    expect(input).not.toHaveFocus()
  })

  it('does not blur the input for other keys', () => {
    render(<NumericStepper {...defaultProps} />)
    const input = screen.getByRole('textbox')
    act(() => input.focus())
    fireEvent.keyUp(input, {key: 'a'})
    expect(input).toHaveFocus()
  })

  it('disables the input and both buttons when disabled is true', () => {
    const {container} = render(<NumericStepper {...defaultProps} disabled />)
    expect(screen.getByRole('textbox')).toBeDisabled()
    const buttons = container.querySelectorAll('button')
    buttons.forEach((button) => expect(button).toBeDisabled())
  })

  it('sets the name attribute on the input', () => {
    render(<NumericStepper {...defaultProps} name="my-stepper" />)
    expect(screen.getByRole('textbox')).toHaveAttribute('name', 'my-stepper')
  })
})
