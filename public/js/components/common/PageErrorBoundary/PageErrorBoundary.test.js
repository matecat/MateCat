import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {PageErrorBoundary} from './PageErrorBoundary'
import {rootErrorOptions} from '../../../utils/rootErrorOptions'

function Thrower() {
  throw new Error('page crashed')
}

beforeEach(() => {
  // React and jsdom log the errors these tests provoke on purpose
  jest.spyOn(console, 'error').mockImplementation(() => {})
})

afterEach(() => {
  console.error.mockRestore()
})

test('a page that throws while rendering is replaced by the fallback', () => {
  render(
    <PageErrorBoundary>
      <Thrower />
    </PageErrorBoundary>,
  )

  expect(screen.getByRole('main')).toBeInTheDocument()
  expect(
    screen.getByRole('heading', {level: 1, name: 'Something went wrong'}),
  ).toBeInTheDocument()
  expect(
    screen.getByText(
      'An unexpected error stopped this page. Reload it to continue; changes that were already saved are kept.',
    ),
  ).toBeInTheDocument()
  expect(
    screen.getByRole('link', {name: 'support@matecat.com'}),
  ).toHaveAttribute('href', 'mailto:support@matecat.com')
  expect(screen.getByRole('link', {name: 'Matecat'})).toHaveAttribute(
    'href',
    '/',
  )
  expect(screen.getByRole('button', {name: 'Reload page'})).toHaveFocus()
  expect(screen.queryByText('page crashed')).not.toBeInTheDocument()
})

test('the reload button reloads the page', () => {
  render(
    <PageErrorBoundary>
      <Thrower />
    </PageErrorBoundary>,
  )
  console.error.mockClear()

  fireEvent.click(screen.getByRole('button', {name: 'Reload page'}))

  // jsdom cannot navigate: location.reload() reports that instead of
  // reloading, and it cannot be replaced by a mock
  expect(console.error).toHaveBeenCalledWith(
    expect.objectContaining({
      type: 'not implemented',
      message: expect.stringContaining('navigation'),
    }),
  )
})

test('a page that renders normally is shown unchanged', () => {
  render(
    <PageErrorBoundary>
      <p>Page content</p>
    </PageErrorBoundary>,
  )

  expect(screen.getByText('Page content')).toBeInTheDocument()
  expect(screen.queryByText('Something went wrong')).not.toBeInTheDocument()
})

test('an error thrown by an event handler leaves the page in place', () => {
  const globalErrors = []
  const onError = (event) => {
    globalErrors.push(event.error?.message)
    event.preventDefault()
  }
  window.addEventListener('error', onError)
  render(
    <PageErrorBoundary>
      <button
        onClick={() => {
          throw new Error('click failed')
        }}
      >
        Act
      </button>
    </PageErrorBoundary>,
  )

  fireEvent.click(screen.getByRole('button', {name: 'Act'}))
  window.removeEventListener('error', onError)

  expect(globalErrors).toEqual(['click failed'])
  expect(screen.getByRole('button', {name: 'Act'})).toBeInTheDocument()
  expect(screen.queryByText('Something went wrong')).not.toBeInTheDocument()
})

test('a page crash is tracked once with its component stack', () => {
  const reports = []
  const onTrackError = (event) => reports.push(event.detail)
  document.addEventListener('track-error', onTrackError)

  render(
    <PageErrorBoundary>
      <Thrower />
    </PageErrorBoundary>,
    {onCaughtError: rootErrorOptions.onCaughtError},
  )
  document.removeEventListener('track-error', onTrackError)

  expect(screen.getByText('Something went wrong')).toBeInTheDocument()
  expect(reports).toHaveLength(1)
  expect(reports[0].message).toEqual(new Error('page crashed'))
  expect(reports[0].attachment.componentStack).toMatch(/Thrower/)
})
