import React, {useState} from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {Slot} from './Slot'
import {
  defineSlot,
  registerSlot,
  resetExtensionOverrides,
} from './extensionPoints'

const EMPTY = 'test.emptySlot'
const FILLED = 'test.filledSlot'

defineSlot(EMPTY)
defineSlot(FILLED)

afterEach(() => {
  resetExtensionOverrides()
})

test('renders nothing when nothing is registered', () => {
  const {container} = render(
    <div data-testid="host">
      <Slot name={EMPTY} />
    </div>,
  )
  expect(container.querySelector('[data-testid="host"]').innerHTML).toBe('')
})

test('renders the registered component', () => {
  registerSlot(FILLED, () => <span>filled in</span>)
  render(<Slot name={FILLED} />)
  expect(screen.getByText('filled in')).toBeInTheDocument()
})

test('passes its props through to the registered component', () => {
  registerSlot(FILLED, ({label}) => <span>{label}</span>)
  render(<Slot name={FILLED} label="from core" />)
  expect(screen.getByText('from core')).toBeInTheDocument()
})

// The reason a slot exists rather than a method returning JSX: the registered
// implementation is a real component, so it can hold state.
test('the registered component may use hooks', async () => {
  registerSlot(FILLED, () => {
    const [clicks, setClicks] = useState(0)
    return (
      <button onClick={() => setClicks(clicks + 1)}>clicked {clicks}</button>
    )
  })
  render(<Slot name={FILLED} />)
  await userEvent.click(screen.getByRole('button'))
  expect(screen.getByRole('button')).toHaveTextContent('clicked 1')
})

// A slot that rebuilt its component type each render would drop that state.
test('keeps the registered component mounted across a parent re-render', async () => {
  registerSlot(FILLED, () => {
    const [clicks, setClicks] = useState(0)
    return (
      <button onClick={() => setClicks(clicks + 1)}>clicked {clicks}</button>
    )
  })

  const Host = () => {
    const [, setTick] = useState(0)
    return (
      <div>
        <button onClick={() => setTick((t) => t + 1)}>re-render host</button>
        <Slot name={FILLED} />
      </div>
    )
  }

  render(<Host />)
  await userEvent.click(screen.getByRole('button', {name: /clicked/}))
  await userEvent.click(screen.getByRole('button', {name: 're-render host'}))
  expect(screen.getByRole('button', {name: /clicked/})).toHaveTextContent(
    'clicked 1',
  )
})

test('throws when the slot was never defined', () => {
  const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})
  expect(() => render(<Slot name="test.undefinedSlot" />)).toThrow(
    'Unknown extension point: test.undefinedSlot',
  )
  consoleError.mockRestore()
})
