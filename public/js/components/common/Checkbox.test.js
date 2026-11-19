import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {Checkbox, CHECKBOX_SIZE, CHECKBOX_STATE} from './Checkbox'

const defaultProps = {
  onChange: jest.fn(),
}

describe('Checkbox', () => {
  afterEach(() => jest.clearAllMocks())

  it('renders unchecked by default', () => {
    render(<Checkbox {...defaultProps} />)
    expect(screen.getByRole('checkbox')).not.toBeChecked()
  })

  it('renders checked when value is CHECKBOX_STATE.CHECKED', () => {
    render(<Checkbox {...defaultProps} value={CHECKBOX_STATE.CHECKED} />)
    expect(screen.getByRole('checkbox')).toBeChecked()
  })

  it('renders indeterminate when value is CHECKBOX_STATE.INDETERMINATE', () => {
    render(<Checkbox {...defaultProps} value={CHECKBOX_STATE.INDETERMINATE} />)
    expect(screen.getByRole('checkbox')).toBePartiallyChecked()
    expect(screen.getByRole('checkbox')).not.toBeChecked()
  })

  it('applies the medium size class by default', () => {
    const {container} = render(<Checkbox {...defaultProps} />)
    expect(container.querySelector('.input-checkbox')).toHaveClass('medium')
  })

  it('applies the small size class when size is CHECKBOX_SIZE.SMALL', () => {
    const {container} = render(
      <Checkbox {...defaultProps} size={CHECKBOX_SIZE.SMALL} />,
    )
    expect(container.querySelector('.input-checkbox')).toHaveClass('small')
  })

  it('renders the label when provided', () => {
    render(<Checkbox {...defaultProps} label="Accept terms" />)
    expect(screen.getByText('Accept terms')).toBeInTheDocument()
  })

  it('associates the label with the input so it is reachable by accessible name', () => {
    render(<Checkbox {...defaultProps} label="Accept terms" />)
    expect(screen.getByLabelText('Accept terms')).toBe(screen.getByRole('checkbox'))
  })

  it('associates the label using an explicit id when provided', () => {
    render(<Checkbox {...defaultProps} id="terms" label="Accept terms" />)
    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).toHaveAttribute('id', 'terms')
    expect(screen.getByLabelText('Accept terms')).toBe(checkbox)
  })

  it('does not render a label element when label is not provided', () => {
    render(<Checkbox {...defaultProps} />)
    expect(screen.queryByText('Accept terms')).not.toBeInTheDocument()
  })

  it('renders the error message when provided', () => {
    render(<Checkbox {...defaultProps} error="This field is required" />)
    expect(screen.getByText('This field is required')).toBeInTheDocument()
  })

  it('disables the input when disabled is true', () => {
    render(<Checkbox {...defaultProps} disabled />)
    expect(screen.getByRole('checkbox')).toBeDisabled()
  })

  it('sets the data-testid from the testId prop', () => {
    render(<Checkbox {...defaultProps} testId="terms-checkbox" />)
    expect(screen.getByTestId('terms-checkbox')).toBe(screen.getByRole('checkbox'))
  })

  it('calls onChange with the new checked value and no shift key on a normal click', () => {
    const onChange = jest.fn()
    render(
      <Checkbox
        {...defaultProps}
        onChange={onChange}
        value={CHECKBOX_STATE.UNCHECKED}
      />,
    )
    fireEvent.click(screen.getByRole('checkbox'))
    expect(onChange).toHaveBeenCalledWith(true, false)
  })

  it('forces isChecked to true and reports the shift key when shift-clicking the container', () => {
    const onChange = jest.fn()
    const {container} = render(
      <Checkbox
        {...defaultProps}
        onChange={onChange}
        value={CHECKBOX_STATE.CHECKED}
      />,
    )
    fireEvent.click(container.querySelector('.input-checkbox'), {
      shiftKey: true,
    })
    expect(onChange).toHaveBeenCalledWith(true, true)
  })

  it('forwards extra props to the input element', () => {
    render(<Checkbox {...defaultProps} data-group="plan" />)
    expect(screen.getByRole('checkbox')).toHaveAttribute('data-group', 'plan')
  })
})
