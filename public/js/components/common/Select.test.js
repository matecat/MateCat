import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Select} from './Select'

// Controlled by individual tests to drive the portal dropdown's max-height
// and positioning effects in Select.js.
let mockListRect = {top: 0, height: 0, bottom: 0}
let mockShowCustomDropdownWrapper = false
let mockCustomDropdownMarginBottom = '0px'
const mockSetListMaxHeight = jest.fn()

jest.mock('./Dropdown', () => {
  const {forwardRef, useImperativeHandle} = require('react')
  return {
    Dropdown: forwardRef(({options, onSelect}, ref) => {
      useImperativeHandle(ref, () => ({
        getListRef: () => ({getBoundingClientRect: () => mockListRect}),
        setListMaxHeight: mockSetListMaxHeight,
      }))
      const content = (
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
      // Mirrors Dropdown's real `.custom-dropdown` wrapper, which Select.js
      // queries for to account for its margin-bottom when reversed.
      return mockShowCustomDropdownWrapper ? (
        <div
          className="custom-dropdown"
          style={{marginBottom: mockCustomDropdownMarginBottom}}
        >
          {content}
        </div>
      ) : (
        content
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
jest.mock('../../../img/icons/IconClose', () => () => null)
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
  render(<Select name="test-select" options={OPTIONS} {...props} />)

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
      expect(
        container.querySelector('.select--is-placeholder'),
      ).toBeInTheDocument()
    })

    test('does not have select--is-placeholder when activeOption is set', () => {
      const {container} = renderSelect({activeOption: OPTIONS[0]})
      expect(
        container.querySelector('.select--is-placeholder'),
      ).not.toBeInTheDocument()
    })

    test('has select--is-disabled when isDisabled is true', () => {
      const {container} = renderSelect({isDisabled: true})
      expect(
        container.querySelector('.select--is-disabled'),
      ).toBeInTheDocument()
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
      expect(
        container.querySelector('.select--is-multiple'),
      ).toBeInTheDocument()
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
        <Select
          name="test-select"
          options={OPTIONS}
          activeOption={OPTIONS[2]}
        />,
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

  describe('portal dropdown (isPortalDropdown)', () => {
    let elementRects
    let resizeObserverInstances

    beforeEach(() => {
      elementRects = {}
      resizeObserverInstances = []
      mockListRect = {top: 0, height: 0, bottom: 0}
      mockShowCustomDropdownWrapper = false
      mockCustomDropdownMarginBottom = '0px'
      mockSetListMaxHeight.mockClear()

      global.ResizeObserver = jest.fn().mockImplementation(() => {
        const instance = {
          observe: jest.fn(),
          unobserve: jest.fn(),
          disconnect: jest.fn(),
        }
        resizeObserverInstances.push(instance)
        return instance
      })

      // wrapperRef, and optionally the real `.custom-dropdown` node rendered
      // by the mocked Dropdown, are real DOM nodes in jsdom (unlike the
      // fully-mocked list node) — keyed by class so each test controls just
      // the rects it cares about.
      jest
        .spyOn(HTMLElement.prototype, 'getBoundingClientRect')
        .mockImplementation(function () {
          for (const className of Object.keys(elementRects)) {
            if (this.classList.contains(className)) {
              return elementRects[className]
            }
          }
          return {top: 0, bottom: 0, left: 0, right: 0, width: 0, height: 0}
        })
    })

    afterEach(() => {
      jest.restoreAllMocks()
    })

    describe('max-height calculation', () => {
      test('uses maxHeightDroplist when there is enough space below the wrapper', () => {
        elementRects['select-with-label__wrapper'] = {
          top: 50,
          bottom: 100,
        }
        const {container} = renderSelect({
          isPortalDropdown: true,
          maxHeightDroplist: 128,
        })
        fireEvent.click(container.querySelector('.select'))

        expect(mockSetListMaxHeight).toHaveBeenCalledWith(128)
        expect(
          container.querySelector('.select__dropdown--is-reversed'),
        ).not.toBeInTheDocument()
      })

      test('reverses and clamps maxHeight when there is not enough space below the wrapper', () => {
        elementRects['select-with-label__wrapper'] = {
          top: 700,
          bottom: 760,
        }
        const {container} = renderSelect({
          isPortalDropdown: true,
          maxHeightDroplist: 128,
        })
        fireEvent.click(container.querySelector('.select'))

        expect(
          container.querySelector('.select__dropdown--is-reversed'),
        ).toBeInTheDocument()
        expect(mockSetListMaxHeight).toHaveBeenCalledWith(128)
      })

      test('does not reverse when checkSpaceToReverse is false, but still clamps maxHeight', () => {
        elementRects['select-with-label__wrapper'] = {
          top: 700,
          bottom: 760,
        }
        const {container} = renderSelect({
          isPortalDropdown: true,
          maxHeightDroplist: 128,
          checkSpaceToReverse: false,
        })
        fireEvent.click(container.querySelector('.select'))

        expect(
          container.querySelector('.select__dropdown--is-reversed'),
        ).not.toBeInTheDocument()
        expect(mockSetListMaxHeight).toHaveBeenCalledWith(128)
      })

      test("uses offsetParent's rect instead of the viewport when provided", () => {
        elementRects['select-with-label__wrapper'] = {
          top: 380,
          bottom: 390,
        }
        const offsetParent = {
          getBoundingClientRect: () => ({top: 200, bottom: 400}),
        }
        const {container} = renderSelect({
          isPortalDropdown: true,
          maxHeightDroplist: 128,
          offsetParent,
        })
        fireEvent.click(container.querySelector('.select'))

        expect(
          container.querySelector('.select__dropdown--is-reversed'),
        ).toBeInTheDocument()
        expect(mockSetListMaxHeight).toHaveBeenCalledWith(128)
      })

      test('reduces the reversed-clamp height by label and search-bar heights', () => {
        elementRects['select-with-label__wrapper'] = {
          top: 700,
          bottom: 760,
        }
        const {container} = renderSelect({
          isPortalDropdown: true,
          maxHeightDroplist: 1000,
          label: 'My label',
          showSearchBar: true,
        })
        fireEvent.click(container.querySelector('.select'))

        // availableHeightAbove = 700 - 0 + 32 (label) - 32 - 48 (searchBar) = 652
        expect(mockSetListMaxHeight).toHaveBeenCalledWith(652)
      })
    })

    describe('positioning', () => {
      test('translates the portal wrapper below the trigger when not reversed', () => {
        elementRects['select-with-label__wrapper'] = {
          x: 10,
          y: 20,
          width: 200,
          height: 50,
          top: 20,
          bottom: 70,
        }
        mockListRect = {height: 150, bottom: 300, top: 150}

        const {container} = renderSelect({isPortalDropdown: true})
        fireEvent.click(container.querySelector('.select'))

        const wrapperDropdown = container.querySelector(
          '.select__dropdown-wrapper',
        )
        expect(wrapperDropdown).toHaveStyle({
          transform: 'translate(10px,70px)',
          width: '200px',
        })
      })

      test('translates the portal wrapper above the trigger, offset by content height, when reversed', () => {
        elementRects['select-with-label__wrapper'] = {
          x: 10,
          y: 700,
          width: 200,
          height: 60,
          top: 700,
          bottom: 760,
        }
        mockListRect = {height: 150, bottom: 850, top: 700}

        const {container} = renderSelect({isPortalDropdown: true})
        fireEvent.click(container.querySelector('.select'))

        const wrapperDropdown = container.querySelector(
          '.select__dropdown-wrapper',
        )
        // reversed (insufficient space below); dropdownHeight = 150 (no
        // custom-dropdown wrapper, so no extra gap) -> y = 700 - 150
        expect(wrapperDropdown).toHaveStyle({
          transform: 'translate(10px,550px)',
        })
      })

      test("adds the custom-dropdown wrapper's margin-bottom to the offset when reversed", () => {
        mockShowCustomDropdownWrapper = true
        mockCustomDropdownMarginBottom = '10px'
        elementRects['select-with-label__wrapper'] = {
          x: 10,
          y: 700,
          width: 200,
          height: 60,
          top: 700,
          bottom: 760,
        }
        elementRects['custom-dropdown'] = {top: 500, bottom: 500}
        mockListRect = {height: 0, bottom: 620, top: 0}

        const {container} = renderSelect({isPortalDropdown: true})
        fireEvent.click(container.querySelector('.select'))

        const wrapperDropdown = container.querySelector(
          '.select__dropdown-wrapper',
        )
        // contentHeight = listNode.bottom(620) - customDropdownNode.top(500) = 120
        // reversedGap = marginBottom = 10 (reversed) -> dropdownHeight = 130
        // y = 700 - 130 = 570
        expect(wrapperDropdown).toHaveStyle({
          transform: 'translate(10px,570px)',
        })
      })

      test('observes the dropdown list node with a ResizeObserver and disconnects when the dropdown closes', () => {
        elementRects['select-with-label__wrapper'] = {
          x: 10,
          y: 20,
          width: 200,
          height: 50,
          top: 20,
          bottom: 70,
        }
        mockListRect = {height: 150, bottom: 300, top: 150}

        const {container} = renderSelect({isPortalDropdown: true})
        fireEvent.click(container.querySelector('.select'))

        expect(resizeObserverInstances).toHaveLength(1)
        expect(resizeObserverInstances[0].observe).toHaveBeenCalled()

        fireEvent.click(container.querySelector('.select'))
        expect(resizeObserverInstances[0].disconnect).toHaveBeenCalled()
      })

      test('does not construct a ResizeObserver when isPortalDropdown is false', () => {
        const {container} = renderSelect()
        fireEvent.click(container.querySelector('.select'))

        expect(resizeObserverInstances).toHaveLength(0)
      })
    })
  })
})
