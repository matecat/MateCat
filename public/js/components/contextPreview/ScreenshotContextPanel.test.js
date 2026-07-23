import {render, screen, waitFor, fireEvent} from '@testing-library/react'
import React from 'react'
import {ScreenshotContextPanel} from './ScreenshotContextPanel'
import {resolveScreenshotUrl} from '../../utils/contextPreviewUtils'

jest.mock('../../utils/contextPreviewUtils')

describe('ScreenshotContextPanel', () => {
  test('shows the placeholder and does not attempt to resolve when no screenshot url is provided', () => {
    render(
      <ScreenshotContextPanel
        screenshotUrl={null}
        zoomLevel={100}
        title="Source"
      />,
    )

    expect(screen.getByText('No screenshot available')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
    expect(resolveScreenshotUrl).not.toHaveBeenCalled()
  })

  test('renders neither the image nor the placeholder while the url is resolving', async () => {
    let resolvePromise
    resolveScreenshotUrl.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    render(
      <ScreenshotContextPanel
        screenshotUrl="https://example.com/shot.png"
        zoomLevel={100}
        title="Source"
      />,
    )

    expect(
      screen.queryByText('No screenshot available'),
    ).not.toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()

    resolvePromise('https://example.com/resolved.png')
    await waitFor(() => expect(screen.getByRole('img')).toBeInTheDocument())
  })

  test('renders the resolved screenshot scaled and offset when zoomed in beyond 100%', async () => {
    resolveScreenshotUrl.mockResolvedValue('https://example.com/resolved.png')

    render(
      <ScreenshotContextPanel
        screenshotUrl="https://example.com/shot.png"
        zoomLevel={200}
        title="Source"
      />,
    )

    const img = await screen.findByRole('img', {
      name: 'Segment context screenshot',
    })
    expect(img).toHaveAttribute('src', 'https://example.com/resolved.png')
    expect(img.parentElement).toHaveStyle({
      transform: 'scale(2)',
      margin: '25%',
    })
  })

  test('applies no margin when zoomed at or below 100%', async () => {
    resolveScreenshotUrl.mockResolvedValue('https://example.com/resolved.png')

    render(
      <ScreenshotContextPanel
        screenshotUrl="https://example.com/shot.png"
        zoomLevel={100}
        title="Source"
      />,
    )

    const img = await screen.findByRole('img')
    expect(img.parentElement).toHaveStyle({transform: 'scale(1)', margin: '0'})
  })

  test('falls back to the placeholder when the resolved image fails to load', async () => {
    resolveScreenshotUrl.mockResolvedValue('https://example.com/resolved.png')

    render(
      <ScreenshotContextPanel
        screenshotUrl="https://example.com/shot.png"
        zoomLevel={100}
        title="Source"
      />,
    )

    const img = await screen.findByRole('img')
    fireEvent.error(img)

    expect(
      await screen.findByText('No screenshot available'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })

  test('falls back to the placeholder when resolving the screenshot url rejects', async () => {
    resolveScreenshotUrl.mockRejectedValue(new Error('boom'))

    render(
      <ScreenshotContextPanel
        screenshotUrl="https://example.com/shot.png"
        zoomLevel={100}
        title="Source"
      />,
    )

    expect(
      await screen.findByText('No screenshot available'),
    ).toBeInTheDocument()
  })

  test('spreads extra props onto the root element and re-resolves when the url changes', async () => {
    resolveScreenshotUrl.mockResolvedValueOnce('https://example.com/first.png')
    resolveScreenshotUrl.mockResolvedValueOnce('https://example.com/second.png')

    const {rerender, container} = render(
      <ScreenshotContextPanel
        screenshotUrl="https://example.com/shot-1.png"
        zoomLevel={100}
        title="Source"
        data-testid="panel"
      />,
    )

    expect(container.querySelector('[data-testid="panel"]')).toBeInTheDocument()
    await screen.findByRole('img')
    expect(screen.getByRole('img')).toHaveAttribute(
      'src',
      'https://example.com/first.png',
    )

    rerender(
      <ScreenshotContextPanel
        screenshotUrl="https://example.com/shot-2.png"
        zoomLevel={100}
        title="Source"
        data-testid="panel"
      />,
    )

    await waitFor(() =>
      expect(screen.getByRole('img')).toHaveAttribute(
        'src',
        'https://example.com/second.png',
      ),
    )
  })
})
