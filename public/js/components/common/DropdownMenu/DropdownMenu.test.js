import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {DropdownMenu} from './DropdownMenu'

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
})
