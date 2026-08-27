import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {SegmentedControl} from './SegmentedControl'

const OPTIONS = [
  {id: 'a', name: 'Option A'},
  {id: 'b', name: 'Option B'},
  {id: 'c', name: 'Option C'},
]

describe('SegmentedControl', () => {
  it('renders an input for each option', () => {
    render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="a"
        onChange={() => {}}
      />,
    )
    expect(screen.getByTestId('radio-option-a')).toBeInTheDocument()
    expect(screen.getByTestId('radio-option-b')).toBeInTheDocument()
    expect(screen.getByTestId('radio-option-c')).toBeInTheDocument()
  })

  it('renders the option labels', () => {
    render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="a"
        onChange={() => {}}
      />,
    )
    expect(screen.getByText('Option A')).toBeInTheDocument()
    expect(screen.getByText('Option B')).toBeInTheDocument()
    expect(screen.getByText('Option C')).toBeInTheDocument()
  })

  it('renders the icon when an option has one', () => {
    const optionsWithIcon = [
      {id: 'a', name: 'Option A', icon: <span data-testid="icon-a" />},
      ...OPTIONS.slice(1),
    ]
    render(
      <SegmentedControl
        name="test"
        options={optionsWithIcon}
        selectedId="a"
        onChange={() => {}}
      />,
    )
    expect(screen.getByTestId('icon-a')).toBeInTheDocument()
  })

  it('marks the selectedId option as checked', () => {
    render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="b"
        onChange={() => {}}
      />,
    )
    expect(screen.getByTestId('radio-option-a')).not.toBeChecked()
    expect(screen.getByTestId('radio-option-b')).toBeChecked()
    expect(screen.getByTestId('radio-option-c')).not.toBeChecked()
  })

  it('calls onChange with the selected option id', () => {
    const onChange = jest.fn()
    render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="a"
        onChange={onChange}
      />,
    )
    fireEvent.click(screen.getByTestId('radio-option-c'))
    expect(onChange).toHaveBeenCalledWith('c')
  })

  it('renders the label when provided', () => {
    render(
      <SegmentedControl
        name="test"
        label="My label"
        options={OPTIONS}
        selectedId="a"
        onChange={() => {}}
      />,
    )
    expect(screen.getByText('My label')).toBeInTheDocument()
  })

  it('does not render a wrapper label element when label is not provided', () => {
    const {container} = render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="a"
        onChange={() => {}}
      />,
    )
    expect(container.querySelector('label[for="test"]')).not.toBeInTheDocument()
  })

  it('disables every radio input when disabled is true', () => {
    render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="a"
        onChange={() => {}}
        disabled
      />,
    )
    OPTIONS.forEach((option) => {
      expect(screen.getByTestId(`radio-option-${option.id}`)).toBeDisabled()
    })
  })

  it('does not disable radio inputs by default', () => {
    render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="a"
        onChange={() => {}}
      />,
    )
    OPTIONS.forEach((option) => {
      expect(screen.getByTestId(`radio-option-${option.id}`)).not.toBeDisabled()
    })
  })

  it('renders with autoWidth enabled without crashing', () => {
    expect(() =>
      render(
        <SegmentedControl
          name="test"
          options={OPTIONS}
          selectedId="a"
          onChange={() => {}}
          autoWidth
        />,
      ),
    ).not.toThrow()
  })

  it('applies a custom className to the outer wrapper', () => {
    const {container} = render(
      <SegmentedControl
        name="test"
        options={OPTIONS}
        selectedId="a"
        onChange={() => {}}
        className="my-extra-class"
      />,
    )
    expect(container.firstChild).toHaveClass('my-extra-class')
  })

  it('renders correctly with a single option', () => {
    const singleOption = [OPTIONS[0]]
    render(
      <SegmentedControl
        name="test"
        options={singleOption}
        selectedId="a"
        onChange={() => {}}
      />,
    )
    expect(screen.getByTestId('radio-option-a')).toBeInTheDocument()
  })

  it('renders correctly with compact prop', () => {
    expect(() =>
      render(
        <SegmentedControl
          name="test"
          options={OPTIONS}
          selectedId="a"
          onChange={() => {}}
          compact
        />,
      ),
    ).not.toThrow()
  })
})
