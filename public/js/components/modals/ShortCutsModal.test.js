import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import ShortCutsModal from './ShortCutsModal'
import {isMacOS} from '../../utils/Utils'

jest.mock('../../utils/Utils', () => ({
  isMacOS: jest.fn(),
}))
jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {
    group1: {
      label: 'Editing',
      events: [
        {
          label: 'Save',
          keystrokes: {mac: 'cmd+s', standard: 'ctrl+s'},
        },
      ],
    },
    ungrouped: {
      events: [
        {
          label: 'Hidden group (no label)',
          keystrokes: {mac: 'cmd+x', standard: 'ctrl+x'},
        },
      ],
    },
  },
}))

afterEach(() => jest.clearAllMocks())

test('renders shortcut groups using mac keystrokes on macOS', () => {
  isMacOS.mockReturnValue(true)
  const {container} = render(<ShortCutsModal onClose={jest.fn()} />)

  expect(screen.getByText('Editing')).toBeInTheDocument()
  expect(screen.getByText('Save')).toBeInTheDocument()
  expect(container.querySelector('.keys.cmd')).toBeInTheDocument()
  expect(container.querySelectorAll('.keys.s').length).toBeGreaterThan(0)
  // groups without a label are not rendered as a section
  expect(screen.queryByText('Hidden group (no label)')).not.toBeInTheDocument()
})

test('renders standard keystrokes on non-macOS', () => {
  isMacOS.mockReturnValue(false)
  const {container} = render(<ShortCutsModal onClose={jest.fn()} />)

  expect(container.querySelector('.keys.ctrl')).toBeInTheDocument()
})

test('pressing Escape calls onClose', () => {
  isMacOS.mockReturnValue(true)
  const onClose = jest.fn()
  render(<ShortCutsModal onClose={onClose} />)

  fireEvent.keyUp(document, {key: 'Escape'})

  expect(onClose).toHaveBeenCalledTimes(1)
})

test('other keys do not call onClose', () => {
  isMacOS.mockReturnValue(true)
  const onClose = jest.fn()
  render(<ShortCutsModal onClose={onClose} />)

  fireEvent.keyUp(document, {key: 'Enter'})

  expect(onClose).not.toHaveBeenCalled()
})

test('removes the keyup listener on unmount', () => {
  isMacOS.mockReturnValue(true)
  const onClose = jest.fn()
  const {unmount} = render(<ShortCutsModal onClose={onClose} />)

  unmount()
  fireEvent.keyUp(document, {key: 'Escape'})

  expect(onClose).not.toHaveBeenCalled()
})
