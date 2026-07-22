import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {ModalOverlay} from './ModalOverlay'

describe('ModalOverlay', () => {
  test('renders title and children', () => {
    render(
      <ModalOverlay title="My title" onClose={jest.fn()}>
        <div>overlay content</div>
      </ModalOverlay>,
    )

    expect(screen.getByText('My title')).toBeInTheDocument()
    expect(screen.getByText('overlay content')).toBeInTheDocument()
  })

  test('clicking the close button calls onClose', () => {
    const onClose = jest.fn()
    render(
      <ModalOverlay title="t" onClose={onClose}>
        <div />
      </ModalOverlay>,
    )

    fireEvent.click(screen.getByTestId('close-button'))

    expect(onClose).toHaveBeenCalledTimes(1)
  })

  test('does not throw when onClose is not provided', () => {
    render(
      <ModalOverlay title="t">
        <div />
      </ModalOverlay>,
    )

    expect(() =>
      fireEvent.click(screen.getByTestId('close-button')),
    ).not.toThrow()
  })
})
