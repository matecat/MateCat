import React, {act} from 'react'
import {createRoot} from 'react-dom/client'
import {render, screen} from '@testing-library/react'
import {rootErrorOptions} from './rootErrorOptions'

let thrownError
function Thrower() {
  throw thrownError
}

// Error boundaries can only be class components
// eslint-disable-next-line no-restricted-syntax
class Boundary extends React.Component {
  state = {failed: false}
  static getDerivedStateFromError() {
    return {failed: true}
  }
  render() {
    return this.state.failed ? <p>Fallback</p> : this.props.children
  }
}

let reports
const onTrackError = (event) => reports.push(event.detail)

// Inside act() React hands an uncaught error back to act() instead of the
// root's onUncaughtError, so these roots render the way the browser does.
let rawRoots
const renderUncaught = async (element) => {
  const container = document.createElement('div')
  document.body.appendChild(container)
  const root = createRoot(container, rootErrorOptions)
  rawRoots.push({root, container})
  const actEnvironment = globalThis.IS_REACT_ACT_ENVIRONMENT
  globalThis.IS_REACT_ACT_ENVIRONMENT = false
  try {
    root.render(element)
    for (let i = 0; i < 40 && !reports.length; i++) {
      await new Promise((resolve) => setTimeout(resolve, 5))
    }
  } finally {
    globalThis.IS_REACT_ACT_ENVIRONMENT = actEnvironment
  }
}

// The testing library builds a real createRoot with these two options; it
// refuses onUncaughtError, which is covered by renderUncaught above.
const renderWithRootOptions = (element) =>
  render(element, {
    onCaughtError: rootErrorOptions.onCaughtError,
    onRecoverableError: rootErrorOptions.onRecoverableError,
  })

beforeEach(() => {
  reports = []
  rawRoots = []
  document.addEventListener('track-error', onTrackError)
  jest.spyOn(console, 'error').mockImplementation(() => {})
})

afterEach(() => {
  rawRoots.forEach(({root, container}) => {
    act(() => root.unmount())
    container.remove()
  })
  document.removeEventListener('track-error', onTrackError)
  console.error.mockRestore()
})

test('an uncaught render error is tracked once with its component stack and still logged', async () => {
  thrownError = new Error('render failed')

  await renderUncaught(<Thrower />)

  expect(reports).toHaveLength(1)
  // The error object itself, so the tracker keeps its stack trace
  expect(reports[0].message).toBe(thrownError)
  expect(reports[0].attachment.componentStack).toMatch(/Thrower/)
  expect(console.error).toHaveBeenCalledWith(
    thrownError,
    expect.stringMatching(/The above error occurred in:[\s\S]*Thrower/),
  )
})

test('an error caught by a boundary is tracked once with its component stack and still logged', () => {
  thrownError = new Error('caught failure')

  renderWithRootOptions(
    <Boundary>
      <Thrower />
    </Boundary>,
  )

  expect(screen.getByText('Fallback')).toBeInTheDocument()
  expect(reports).toHaveLength(1)
  expect(reports[0].message).toBe(thrownError)
  expect(reports[0].attachment.componentStack).toMatch(/Thrower/)
  expect(console.error).toHaveBeenCalledWith(
    thrownError,
    expect.stringMatching(/Thrower/),
  )
})

test('a render that recovers on retry is tracked once with its component stack', () => {
  const error = new Error('transient failure')
  let thrown = false
  const ThrowsOnce = () => {
    if (!thrown) {
      thrown = true
      throw error
    }
    return <p>Recovered</p>
  }

  renderWithRootOptions(<ThrowsOnce />)

  expect(screen.getByText('Recovered')).toBeInTheDocument()
  expect(reports).toHaveLength(1)
  expect(reports[0].message.cause).toBe(error)
  expect(reports[0].attachment).toHaveProperty('componentStack')
  expect(console.error).toHaveBeenCalledWith(
    reports[0].message,
    expect.any(String),
  )
})
