import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {ModalContainer} from './ModalContainer'

describe('ModalContainer', () => {
  test('renders header, title and children when showHeader is true', () => {
    render(
      <ModalContainer
        title="Modal title"
        showHeader
        onClose={jest.fn()}
        closeOnOutsideClick={true}
      >
        <div>body content</div>
      </ModalContainer>,
    )

    expect(screen.getByText('Modal title')).toBeInTheDocument()
    expect(screen.getByText('body content')).toBeInTheDocument()
    expect(screen.getByTestId('close-button')).toBeInTheDocument()
  })

  test('hides the header entirely when showHeader is false', () => {
    render(
      <ModalContainer
        title="Hidden title"
        showHeader={false}
        onClose={jest.fn()}
      >
        <div>body</div>
      </ModalContainer>,
    )

    expect(screen.queryByText('Hidden title')).not.toBeInTheDocument()
    expect(screen.queryByTestId('close-button')).not.toBeInTheDocument()
  })

  test('hides the close button when isCloseButtonDisabled is true', () => {
    render(
      <ModalContainer
        title="t"
        showHeader
        onClose={jest.fn()}
        isCloseButtonDisabled
      >
        <div />
      </ModalContainer>,
    )

    expect(screen.queryByTestId('close-button')).not.toBeInTheDocument()
  })

  test('clicking the close button calls onClose', () => {
    const onClose = jest.fn()
    render(
      <ModalContainer title="t" showHeader onClose={onClose}>
        <div />
      </ModalContainer>,
    )

    fireEvent.click(screen.getByTestId('close-button'))

    expect(onClose).toHaveBeenCalledTimes(1)
  })

  test('clicking outside closes the modal when closeOnOutsideClick is true', () => {
    const onClose = jest.fn()
    const {container} = render(
      <ModalContainer title="t" onClose={onClose} closeOnOutsideClick>
        <div />
      </ModalContainer>,
    )

    fireEvent.click(container.querySelector('.matecat-modal-background'))

    expect(onClose).toHaveBeenCalledTimes(1)
  })

  test('clicking outside does nothing when closeOnOutsideClick is false', () => {
    const onClose = jest.fn()
    const {container} = render(
      <ModalContainer title="t" onClose={onClose} closeOnOutsideClick={false}>
        <div />
      </ModalContainer>,
    )

    fireEvent.click(container.querySelector('.matecat-modal-background'))

    expect(onClose).not.toHaveBeenCalled()
  })

  test('clicking outside does nothing when the close button is disabled', () => {
    const onClose = jest.fn()
    const {container} = render(
      <ModalContainer
        title="t"
        onClose={onClose}
        closeOnOutsideClick
        isCloseButtonDisabled
      >
        <div />
      </ModalContainer>,
    )

    fireEvent.click(container.querySelector('.matecat-modal-background'))

    expect(onClose).not.toHaveBeenCalled()
  })

  test('Tab on the last focusable element wraps focus to the first', () => {
    render(
      <ModalContainer title="t" onClose={jest.fn()}>
        <input data-testid="first-input" />
        <input data-testid="last-input" />
      </ModalContainer>,
    )

    const first = screen.getByTestId('first-input')
    const last = screen.getByTestId('last-input')
    last.focus()

    fireEvent.keyDown(last, {key: 'Tab', shiftKey: false})

    expect(document.activeElement).toBe(first)
  })

  test('Shift+Tab on the first focusable element wraps focus to the last', () => {
    render(
      <ModalContainer title="t" onClose={jest.fn()}>
        <input data-testid="first-input" />
        <input data-testid="last-input" />
      </ModalContainer>,
    )

    const first = screen.getByTestId('first-input')
    const last = screen.getByTestId('last-input')
    first.focus()

    fireEvent.keyDown(first, {key: 'Tab', shiftKey: true})

    expect(document.activeElement).toBe(last)
  })

  test('Tab in the middle of the dialog does not steal focus', () => {
    render(
      <ModalContainer title="t" onClose={jest.fn()}>
        <input data-testid="first-input" />
        <input data-testid="middle-input" />
        <input data-testid="last-input" />
      </ModalContainer>,
    )

    const middle = screen.getByTestId('middle-input')
    middle.focus()

    fireEvent.keyDown(middle, {key: 'Tab', shiftKey: false})

    expect(document.activeElement).toBe(middle)
  })

  test('non-Tab keydown is ignored', () => {
    render(
      <ModalContainer title="t" showHeader onClose={jest.fn()}>
        <input data-testid="first-input" />
      </ModalContainer>,
    )

    const input = screen.getByTestId('first-input')
    input.focus()

    expect(() => fireEvent.keyDown(input, {key: 'Enter'})).not.toThrow()
  })
})
