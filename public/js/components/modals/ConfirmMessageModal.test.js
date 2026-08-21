import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import ConfirmMessageModal from './ConfirmMessageModal'

test('renders string text as html', () => {
  const {container} = render(
    <ConfirmMessageModal text="<b>hi</b>" onClose={jest.fn()} />,
  )

  expect(screen.getByText('hi')).toBeInTheDocument()
  expect(container.innerHTML).toContain('<b>')
})

test('renders node text as-is', () => {
  render(
    <ConfirmMessageModal text={<span>node text</span>} onClose={jest.fn()} />,
  )

  expect(screen.getByText('node text')).toBeInTheDocument()
})

test('modalName prop is applied to the grid className', () => {
  const {container} = render(
    <ConfirmMessageModal
      text="t"
      modalName="my-modal-name"
      onClose={jest.fn()}
    />,
  )

  expect(container.innerHTML).toContain('modal-grid my-modal-name')
})

describe('cancel button', () => {
  test('does not render when neither cancelCallback nor cancelText is provided', () => {
    render(<ConfirmMessageModal text="t" onClose={jest.fn()} />)

    expect(screen.queryByText('Cancel')).not.toBeInTheDocument()
  })

  test('renders default label when cancelCallback is provided', () => {
    render(
      <ConfirmMessageModal
        text="t"
        cancelCallback={jest.fn()}
        onClose={jest.fn()}
      />,
    )

    expect(screen.getByText('Cancel')).toBeInTheDocument()
  })

  test('renders when only cancelText is provided', () => {
    render(
      <ConfirmMessageModal text="t" cancelText="Nope" onClose={jest.fn()} />,
    )

    expect(screen.getByText('Nope')).toBeInTheDocument()
  })

  test('click calls cancelCallback but not onClose by default', () => {
    const cancelCallback = jest.fn()
    const onClose = jest.fn()
    render(
      <ConfirmMessageModal
        text="t"
        cancelCallback={cancelCallback}
        onClose={onClose}
      />,
    )

    fireEvent.click(screen.getByText('Cancel'))

    expect(cancelCallback).toHaveBeenCalledTimes(1)
    expect(onClose).not.toHaveBeenCalled()
  })

  test('closeOnSuccess also calls onClose when clicked', () => {
    const cancelCallback = jest.fn()
    const onClose = jest.fn()
    render(
      <ConfirmMessageModal
        text="t"
        cancelCallback={cancelCallback}
        closeOnSuccess
        onClose={onClose}
      />,
    )

    fireEvent.click(screen.getByText('Cancel'))

    expect(cancelCallback).toHaveBeenCalledTimes(1)
    expect(onClose).toHaveBeenCalledTimes(1)
  })
})

describe('warning button', () => {
  test('does not render when warningCallback is not provided', () => {
    render(<ConfirmMessageModal text="t" onClose={jest.fn()} />)

    expect(screen.queryByText('Warn me')).not.toBeInTheDocument()
  })

  test('renders warningText label when warningCallback is provided', () => {
    render(
      <ConfirmMessageModal
        text="t"
        warningCallback={jest.fn()}
        warningText="Warn me"
        onClose={jest.fn()}
      />,
    )

    expect(screen.getByText('Warn me')).toBeInTheDocument()
  })

  test('click calls warningCallback but not onClose by default', () => {
    const warningCallback = jest.fn()
    const onClose = jest.fn()
    render(
      <ConfirmMessageModal
        text="t"
        warningCallback={warningCallback}
        warningText="Warn me"
        onClose={onClose}
      />,
    )

    fireEvent.click(screen.getByText('Warn me'))

    expect(warningCallback).toHaveBeenCalledTimes(1)
    expect(onClose).not.toHaveBeenCalled()
  })

  test('closeOnSuccess also calls onClose when clicked', () => {
    const warningCallback = jest.fn()
    const onClose = jest.fn()
    render(
      <ConfirmMessageModal
        text="t"
        warningCallback={warningCallback}
        warningText="Warn me"
        closeOnSuccess
        onClose={onClose}
      />,
    )

    fireEvent.click(screen.getByText('Warn me'))

    expect(warningCallback).toHaveBeenCalledTimes(1)
    expect(onClose).toHaveBeenCalledTimes(1)
  })
})

describe('success button', () => {
  test('does not render when neither successCallback nor successText is provided', () => {
    render(<ConfirmMessageModal text="t" onClose={jest.fn()} />)

    expect(screen.queryByText('Confirm')).not.toBeInTheDocument()
  })

  test('renders default label when successCallback is provided', () => {
    render(
      <ConfirmMessageModal
        text="t"
        successCallback={jest.fn()}
        onClose={jest.fn()}
      />,
    )

    expect(screen.getByText('Confirm')).toBeInTheDocument()
  })

  test('renders when only successText is provided', () => {
    render(
      <ConfirmMessageModal text="t" successText="Yes" onClose={jest.fn()} />,
    )

    expect(screen.getByText('Yes')).toBeInTheDocument()
  })

  test('click calls successCallback but not onClose by default', () => {
    const successCallback = jest.fn()
    const onClose = jest.fn()
    render(
      <ConfirmMessageModal
        text="t"
        successCallback={successCallback}
        onClose={onClose}
      />,
    )

    fireEvent.click(screen.getByText('Confirm'))

    expect(successCallback).toHaveBeenCalledTimes(1)
    expect(onClose).not.toHaveBeenCalled()
  })

  test('closeOnSuccess also calls onClose when clicked', () => {
    const successCallback = jest.fn()
    const onClose = jest.fn()
    render(
      <ConfirmMessageModal
        text="t"
        successCallback={successCallback}
        closeOnSuccess
        onClose={onClose}
      />,
    )

    fireEvent.click(screen.getByText('Confirm'))

    expect(successCallback).toHaveBeenCalledTimes(1)
    expect(onClose).toHaveBeenCalledTimes(1)
  })
})
