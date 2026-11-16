import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {DropdownMenu, DROPDOWN_MENU_TRIGGER_MODE} from './DropdownMenu'

// Radix positions its content with ResizeObserver and pointer-capture APIs,
// none of which jsdom implements.
beforeAll(() => {
  global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
  Element.prototype.hasPointerCapture = () => false
  Element.prototype.setPointerCapture = () => {}
  Element.prototype.releasePointerCapture = () => {}
  Element.prototype.scrollIntoView = () => {}
})

const openMenu = async (user) => {
  await user.click(screen.getByRole('button'))
  return waitFor(() => expect(screen.getByRole('menu')).toBeInTheDocument())
}

describe('DropdownMenu', () => {
  describe('keepOpen', () => {
    test('closes the menu after selecting a regular item', async () => {
      const user = userEvent.setup()
      const onClick = jest.fn()
      render(<DropdownMenu items={[{label: 'Plain action', onClick}]} />)

      await openMenu(user)
      await user.click(screen.getByText('Plain action'))

      expect(onClick).toHaveBeenCalledTimes(1)
      await waitFor(() =>
        expect(screen.queryByRole('menu')).not.toBeInTheDocument(),
      )
    })

    test('leaves the menu open after selecting a keepOpen item', async () => {
      const user = userEvent.setup()
      const onClick = jest.fn()
      render(
        <DropdownMenu
          items={[{label: 'Toggle me', onClick, keepOpen: true}]}
        />,
      )

      await openMenu(user)
      await user.click(screen.getByText('Toggle me'))

      expect(onClick).toHaveBeenCalledTimes(1)
      expect(screen.getByRole('menu')).toBeInTheDocument()
    })

    test('fires once per click, so repeated toggling stays in sync', async () => {
      const user = userEvent.setup()
      const onClick = jest.fn()
      render(
        <DropdownMenu
          items={[{label: 'Toggle me', onClick, keepOpen: true}]}
        />,
      )

      await openMenu(user)
      await user.click(screen.getByText('Toggle me'))
      await user.click(screen.getByText('Toggle me'))
      await user.click(screen.getByText('Toggle me'))

      expect(onClick).toHaveBeenCalledTimes(3)
      expect(screen.getByRole('menu')).toBeInTheDocument()
    })

    test('does not close on a keepOpen item that has no onClick', async () => {
      const user = userEvent.setup()
      render(<DropdownMenu items={[{label: 'Inert', keepOpen: true}]} />)

      await openMenu(user)
      await user.click(screen.getByText('Inert'))

      expect(screen.getByRole('menu')).toBeInTheDocument()
    })
  })

  describe('hover trigger', () => {
    const renderHoverMenu = (items) =>
      render(
        <DropdownMenu
          triggerMode={DROPDOWN_MENU_TRIGGER_MODE.HOVER}
          items={items}
        />,
      )

    test('does not mark an item disabled when disabled is false', async () => {
      const user = userEvent.setup()
      renderHoverMenu([
        {label: 'Download draft', onClick: jest.fn(), disabled: false},
      ])

      await user.hover(screen.getByRole('button'))
      const item = await screen.findByText('Download draft')

      expect(item).not.toHaveAttribute('data-disabled')
    })

    test('marks a disabled item', async () => {
      const user = userEvent.setup()
      renderHoverMenu([
        {label: 'Download draft', onClick: jest.fn(), disabled: true},
      ])

      await user.hover(screen.getByRole('button'))
      const item = await screen.findByText('Download draft')

      expect(item).toHaveAttribute('data-disabled')
    })

    // MATECAT-5NN: the bundled segment toolbar passes items that are only a
    // label (the button inside handles its own click)
    test('clicking an item with no onClick closes the menu without throwing', async () => {
      const user = userEvent.setup()
      const errors = []
      const onError = (event) => {
        errors.push(event.error)
        event.preventDefault()
      }
      window.addEventListener('error', onError)
      render(
        <DropdownMenu
          triggerMode={DROPDOWN_MENU_TRIGGER_MODE.HOVER}
          items={[{label: 'Label only'}]}
        />,
      )

      await user.hover(screen.getByRole('button'))
      fireEvent.click(await screen.findByText('Label only'))

      await waitFor(() =>
        expect(screen.queryByText('Label only')).not.toBeInTheDocument(),
      )
      window.removeEventListener('error', onError)
      expect(errors).toEqual([])
    })
  })
})
