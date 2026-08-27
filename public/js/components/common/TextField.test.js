import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import TextField from './TextField'

describe('TextField', () => {
  it('renders an input with the given placeholder', () => {
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={() => {}}
        placeholder="Enter text"
      />,
    )
    expect(screen.getByPlaceholderText('Enter text')).toBeInTheDocument()
  })

  it('defaults to a text input type when no type is given', () => {
    render(
      <TextField showError={false} errorText="" onFieldChanged={() => {}} />,
    )
    expect(screen.getByRole('textbox')).toHaveAttribute('type', 'text')
  })

  it('respects a custom type prop', () => {
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={() => {}}
        type="password"
      />,
    )
    const input = document.querySelector('input')
    expect(input).toHaveAttribute('type', 'password')
  })

  it('sets defaultValue from the text prop', () => {
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={() => {}}
        text="hello"
      />,
    )
    expect(screen.getByRole('textbox')).toHaveValue('hello')
  })

  it('sets the name attribute', () => {
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={() => {}}
        name="my-field"
      />,
    )
    expect(screen.getByRole('textbox')).toHaveAttribute('name', 'my-field')
  })

  it('does not render an error message when showError is false', () => {
    render(
      <TextField
        showError={false}
        errorText="Some error"
        onFieldChanged={() => {}}
      />,
    )
    expect(screen.queryByText('Some error')).not.toBeInTheDocument()
  })

  it('does not render an error message when errorText is empty', () => {
    render(
      <TextField showError={true} errorText="" onFieldChanged={() => {}} />,
    )
    expect(document.querySelector('.validation-error')).not.toBeInTheDocument()
  })

  it('renders an error message when showError is true and errorText is set', () => {
    render(
      <TextField
        showError={true}
        errorText="This field is required"
        onFieldChanged={() => {}}
      />,
    )
    expect(screen.getByText('This field is required')).toBeInTheDocument()
  })

  it('calls onFieldChanged when the input changes', () => {
    const onFieldChanged = jest.fn()
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={onFieldChanged}
      />,
    )
    fireEvent.change(screen.getByRole('textbox'), {
      target: {value: 'typed value'},
    })
    expect(onFieldChanged).toHaveBeenCalled()
  })

  it('calls onFieldChanged on mount when a text prop is provided', () => {
    const onFieldChanged = jest.fn()
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={onFieldChanged}
        text="preset value"
      />,
    )
    expect(onFieldChanged).toHaveBeenCalledWith({
      target: {value: 'preset value'},
    })
  })

  it('does not call onFieldChanged on mount when there is no text prop', () => {
    const onFieldChanged = jest.fn()
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={onFieldChanged}
      />,
    )
    expect(onFieldChanged).not.toHaveBeenCalled()
  })

  it('calls onKeyPress when a key is pressed', () => {
    const onKeyPress = jest.fn()
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={() => {}}
        onKeyPress={onKeyPress}
      />,
    )
    fireEvent.keyPress(screen.getByRole('textbox'), {
      key: 'Enter',
      code: 13,
      charCode: 13,
    })
    expect(onKeyPress).toHaveBeenCalled()
  })

  it('applies the classes prop as the input className', () => {
    render(
      <TextField
        showError={false}
        errorText=""
        onFieldChanged={() => {}}
        classes="my-input-class"
      />,
    )
    expect(screen.getByRole('textbox')).toHaveClass('my-input-class')
  })
})
