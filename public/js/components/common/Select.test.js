import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Select} from './Select'

jest.mock('./Dropdown', () => {
  const {forwardRef, useImperativeHandle} = require('react')
  return {
    Dropdown: forwardRef(({options, onSelect}, ref) => {
      useImperativeHandle(ref, () => ({
        getListRef: () => ({getBoundingClientRect: () => ({top: 0, height: 0})}),
        setListMaxHeight: jest.fn(),
      }))
      return (
        <div data-testid="dropdown">
          {options?.map((opt) => (
            <button
              key={opt.id}
              data-testid={`option-${opt.id}`}
              onClick={() => onSelect(opt)}
            >
              {opt.name}
            </button>
          ))}
        </div>
      )
    }),
  }
})

jest.mock('./Tooltip', () => ({children}) => <>{children}</>)
jest.mock('../../hooks/usePortal', () =>
  jest.fn(
    () =>
      ({children}) =>
        children,
  ),
)
jest.mock('../../../img/icons/ChevronDown', () => () => null)
jest.mock('../icons/IconClose', () => () => null)
jest.mock('../../utils/textUtils', () => ({
  __esModule: true,
  default: {isContentTextEllipsis: jest.fn(() => false)},
}))

const OPTIONS = [
  {id: 'a', name: 'Option A'},
  {id: 'b', name: 'Option B'},
  {id: 'c', name: 'Option C'},
]

const renderSelect = (props = {}) =>
  render(
    <Select
      name="test-select"
      options={OPTIONS}
      {...props}
    />,
  )

describe('Select', () => {
  describe('rendering', () => {
    test('renders outer wrapper', () => {
      const {container} = renderSelect()
      expect(
        container.querySelector('.select-with-label__wrapper'),
      ).toBeInTheDocument()
    })

    test('applies custom className to wrapper', () => {
      const {container} = renderSelect({className: 'my-class'})
      expect(
        container.querySelector('.select-with-label__wrapper.my-class'),
      ).toBeInTheDocument()
    })

    test('renders label when label prop is provided', () => {
      renderSelect({label: 'My Label'})
      expect(screen.getByText('My Label')).toBeInTheDocument()
    })

    test('does not render label element when label prop is absent', () => {
      const {container} = renderSelect()
      expect(container.querySelector('label')).not.toBeInTheDocument()
    })

    test('renders placeholder text when no activeOption is set', () => {
      renderSelect({placeholder: 'Pick one'})
      expect(screen.getByText('Pick one')).toBeInTheDocument()
    })

    test('renders activeOption name', () => {
      renderSelect({activeOption: OPTIONS[0]})
      expect(screen.getByText('Option A')).toBeInTheDocument()
    })

    test('renders comma-separated names for activeOptions in multiple mode', () => {
      renderSelect({
        multipleSelect: 'dropdown',
        activeOptions: [OPTIONS[0], OPTIONS[1]],
      })
      expect(screen.getByText('Option A, Option B')).toBeInTheDocument()
    })

    test('sets hidden input value from activeOption id', () => {
      const {container} = renderSelect({activeOption: OPTIONS[1]})
      expect(container.querySelector('input[type="text"]').value).toBe('b')
    })

    test('applies id to wrapper when id prop is provided', () => {
      const {container} = renderSelect({id: 'my-id'})
      expect(container.querySelector('#my-id')).toBeInTheDocument()
    })

    test('dropdown is not visible on initial render', () => {
      renderSelect()
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })
  })

  describe('CSS classes', () => {
    test('has select--is-placeholder when no activeOption', () => {
      const {container} = renderSelect()
      expect(container.querySelector('.select--is-placeholder')).toBeInTheDocument()
    })

    test('does not have select--is-placeholder when activeOption is set', () => {
      const {container} = renderSelect({activeOption: OPTIONS[0]})
      expect(
        container.querySelector('.select--is-placeholder'),
      ).not.toBeInTheDocument()
    })

    test('has select--is-disabled when isDisabled is true', () => {
      const {container} = renderSelect({isDisabled: true})
      expect(container.querySelector('.select--is-disabled')).toBeInTheDocument()
    })

    test('has select--is-invalid when showValidation=true and isValid=false', () => {
      const {container} = renderSelect({showValidation: true, isValid: false})
      expect(container.querySelector('.select--is-invalid')).toBeInTheDocument()
    })

    test('does not have select--is-invalid when isValid is true', () => {
      const {container} = renderSelect({showValidation: true, isValid: true})
      expect(
        container.querySelector('.select--is-invalid'),
      ).not.toBeInTheDocument()
    })

    test('has select--is-multiple when multipleSelect is not "off"', () => {
      const {container} = renderSelect({multipleSelect: 'dropdown'})
      expect(container.querySelector('.select--is-multiple')).toBeInTheDocument()
    })

    test('has select--is-focused when dropdown is open', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      expect(container.querySelector('.select--is-focused')).toBeInTheDocument()
    })

    test('does not have select--is-focused when dropdown is closed', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      fireEvent.click(container.querySelector('.select'))
      expect(
        container.querySelector('.select--is-focused'),
      ).not.toBeInTheDocument()
    })
  })

  describe('dropdown toggle', () => {
    test('opens dropdown when select div is clicked', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      expect(screen.getByTestId('dropdown')).toBeInTheDocument()
    })

    test('closes dropdown on second click', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      fireEvent.click(container.querySelector('.select'))
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })

    test('opens dropdown when label is clicked', () => {
      const {container} = renderSelect({label: 'My Label'})
      fireEvent.click(container.querySelector('label'))
      expect(screen.getByTestId('dropdown')).toBeInTheDocument()
    })

    test('does not open dropdown when isDisabled is true', () => {
      const {container} = renderSelect({isDisabled: true})
      fireEvent.click(container.querySelector('.select'))
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })

    test('closes dropdown when Escape is pressed', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      expect(screen.getByTestId('dropdown')).toBeInTheDocument()
      fireEvent.keyDown(document, {keyCode: 27})
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })

    test('closes dropdown when Tab is pressed', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      fireEvent.keyDown(document, {keyCode: 9})
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })

    test('closes dropdown when clicking outside the wrapper', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      fireEvent.mouseDown(document.body)
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })

    test('calls onCloseSelect when dropdown is closed', () => {
      const onCloseSelect = jest.fn()
      const {container} = renderSelect({onCloseSelect})
      fireEvent.click(container.querySelector('.select'))
      fireEvent.click(container.querySelector('.select'))
      expect(onCloseSelect).toHaveBeenCalledTimes(1)
    })
  })

  describe('option selection', () => {
    test('calls onSelect with the selected option', () => {
      const onSelect = jest.fn()
      const {container} = renderSelect({onSelect})
      fireEvent.click(container.querySelector('.select'))
      fireEvent.click(screen.getByTestId('option-a'))
      expect(onSelect).toHaveBeenCalledWith(OPTIONS[0])
    })

    test('closes dropdown after selecting an option', () => {
      const {container} = renderSelect()
      fireEvent.click(container.querySelector('.select'))
      fireEvent.click(screen.getByTestId('option-b'))
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })

    test('reflects updated activeOption prop in hidden input value', () => {
      const {container, rerender} = renderSelect({activeOption: OPTIONS[0]})
      expect(container.querySelector('input[type="text"]').value).toBe('a')
      rerender(
        <Select name="test-select" options={OPTIONS} activeOption={OPTIONS[2]} />,
      )
      expect(container.querySelector('input[type="text"]').value).toBe('c')
    })
  })

  describe('reset button', () => {
    test('does not render reset button by default', () => {
      const {container} = renderSelect({activeOption: OPTIONS[0]})
      expect(container.querySelector('.icon-reset')).not.toBeInTheDocument()
    })

    test('renders reset button when showResetButton=true and activeOption is set', () => {
      const {container} = renderSelect({
        showResetButton: true,
        activeOption: OPTIONS[0],
      })
      expect(container.querySelector('.icon-reset')).toBeInTheDocument()
    })

    test('does not render reset button when showResetButton=true but no activeOption', () => {
      const {container} = renderSelect({showResetButton: true})
      expect(container.querySelector('.icon-reset')).not.toBeInTheDocument()
    })

    test('calls resetFunction when reset button is clicked', () => {
      const resetFunction = jest.fn()
      const {container} = renderSelect({
        showResetButton: true,
        activeOption: OPTIONS[0],
        resetFunction,
      })
      fireEvent.click(container.querySelector('.icon-reset'))
      expect(resetFunction).toHaveBeenCalledTimes(1)
    })

    test('clicking reset button does not open the dropdown', () => {
      const {container} = renderSelect({
        showResetButton: true,
        activeOption: OPTIONS[0],
        resetFunction: jest.fn(),
      })
      fireEvent.click(container.querySelector('.icon-reset'))
      expect(screen.queryByTestId('dropdown')).not.toBeInTheDocument()
    })
  })
})
