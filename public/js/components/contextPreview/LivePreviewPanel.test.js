import {render} from '@testing-library/react'
import React, {createRef} from 'react'
import {LivePreviewPanel} from './LivePreviewPanel'

describe('LivePreviewPanel', () => {
  test('attaches a shadow root to the scaler element and stores the wrapper on panelRef', () => {
    const panelRef = createRef()
    const {container} = render(
      <LivePreviewPanel
        panelRef={panelRef}
        scrollRef={createRef()}
        title="Source"
        zoomLevel={100}
      />,
    )

    const host = container.querySelector('.context-preview-content__scaler')
    expect(host.shadowRoot).not.toBeNull()
    expect(host.shadowRoot.querySelector('style')).not.toBeNull()
    expect(panelRef.current).toBe(host.shadowRoot.lastChild)
  })

  test('does not render a header when languageLabel is not provided', () => {
    const {container} = render(
      <LivePreviewPanel
        panelRef={createRef()}
        scrollRef={createRef()}
        title="Source"
        zoomLevel={100}
      />,
    )

    expect(container.querySelector('.context-preview-panel-header')).toBeNull()
  })

  test('renders the language label header when provided', () => {
    const {getByText} = render(
      <LivePreviewPanel
        panelRef={createRef()}
        scrollRef={createRef()}
        title="Target"
        zoomLevel={100}
        languageLabel="Target - it-IT"
      />,
    )

    expect(getByText('Target - it-IT')).toBeInTheDocument()
  })

  test('applies the zoom level as a scale transform on the scaler element', () => {
    const {container} = render(
      <LivePreviewPanel
        panelRef={createRef()}
        scrollRef={createRef()}
        title="Source"
        zoomLevel={150}
      />,
    )

    const scaler = container.querySelector('.context-preview-content__scaler')
    expect(scaler).toHaveStyle({transform: 'scale(1.5)'})
  })

  test('does not reattach a shadow root on an already-hosted node when the effect reruns', () => {
    const panelRefA = createRef()
    const scrollRef = createRef()

    const {rerender, container} = render(
      <LivePreviewPanel
        panelRef={panelRefA}
        scrollRef={scrollRef}
        title="Source"
        zoomLevel={100}
      />,
    )

    const host = container.querySelector('.context-preview-content__scaler')
    expect(panelRefA.current).not.toBeNull()

    // Passing a new panelRef object changes the effect's dependency, forcing it to
    // rerun on the same DOM node. attachShadow() throws if called twice on the same
    // host, so the `host.shadowRoot` guard must bail out early -- leaving the new
    // ref untouched and the original shadow root intact.
    const panelRefB = createRef()
    rerender(
      <LivePreviewPanel
        panelRef={panelRefB}
        scrollRef={scrollRef}
        title="Source"
        zoomLevel={100}
      />,
    )

    expect(panelRefB.current).toBeNull()
    expect(host.shadowRoot).not.toBeNull()
  })

  test('spreads extra props onto the root element', () => {
    const {container} = render(
      <LivePreviewPanel
        panelRef={createRef()}
        scrollRef={createRef()}
        title="Source"
        zoomLevel={100}
        data-testid="live-panel"
      />,
    )

    expect(
      container.querySelector('[data-testid="live-panel"]'),
    ).toBeInTheDocument()
  })
})
