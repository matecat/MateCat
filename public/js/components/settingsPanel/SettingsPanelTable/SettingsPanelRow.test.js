import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {SettingsPanelRow} from './SettingsPanelRow'
import {SettingsPanelTableContext} from './SettingsPanelTableContext'

const buildContext = (overrides = {}) => {
  const containerEl = document.createElement('div')
  return {
    rowsContainerRef: {current: containerEl},
    dragStartInfoRef: {current: undefined},
    onDragStart: jest.fn(),
    onDragOver: jest.fn(),
    onDragEnd: jest.fn(),
    ...overrides,
  }
}

const dragOverEvent = ({clientX = 0, clientY = 0} = {}) =>
  new MouseEvent('dragover', {
    bubbles: true,
    cancelable: true,
    clientX,
    clientY,
  })

const renderRow = ({row, index = 0, isDragOver, wasDragged, context} = {}) => {
  const rowRef = React.createRef()
  const ctx = context ?? buildContext()
  const utils = render(
    <SettingsPanelTableContext.Provider value={ctx}>
      <SettingsPanelRow
        ref={rowRef}
        index={index}
        row={row}
        isDragOver={isDragOver}
        wasDragged={wasDragged}
      />
    </SettingsPanelTableContext.Provider>,
  )
  return {...utils, context: ctx, rowRef}
}

describe('SettingsPanelRow', () => {
  test('renders row.node content with the expected testid', () => {
    renderRow({row: {id: 'r1', node: <span>Hello</span>}})
    expect(
      screen.getByTestId('settings-panel-table-row-r1'),
    ).toHaveTextContent('Hello')
  })

  test('does not render a drag handle when isDraggable is falsy', () => {
    const {container} = renderRow({row: {id: 'r1', node: <span />}})
    expect(
      container.querySelector('.settings-panel-row-drag-handle'),
    ).not.toBeInTheDocument()
  })

  test('renders a drag handle when isDraggable is true', () => {
    const {container} = renderRow({
      row: {id: 'r1', node: <span />, isDraggable: true},
    })
    expect(
      container.querySelector('.settings-panel-row-drag-handle'),
    ).toBeInTheDocument()
  })

  test('does not render extraNode when isExpanded is falsy', () => {
    renderRow({
      row: {
        id: 'r1',
        node: <span />,
        extraNode: <div data-testid="extra">Extra</div>,
      },
    })
    expect(screen.queryByTestId('extra')).not.toBeInTheDocument()
  })

  test('renders extraNode and expanded class when isExpanded is true', () => {
    const {container} = renderRow({
      row: {
        id: 'r1',
        node: <span />,
        isExpanded: true,
        extraNode: <div data-testid="extra">Extra</div>,
      },
    })
    expect(screen.getByTestId('extra')).toBeInTheDocument()
    expect(
      container.querySelector('.settings-panel-row-extra-content-expanded'),
    ).toBeInTheDocument()
  })

  test('adds the active and custom classes to the content wrapper', () => {
    const {container} = renderRow({
      row: {
        id: 'r1',
        node: <span />,
        isActive: true,
        className: 'custom-class',
      },
    })
    const content = container.querySelector('.settings-panel-row-content')
    expect(content.className).toContain('settings-panel-row-active')
    expect(content.className).toContain('custom-class')
  })

  test('adds the dragend class when wasDragged is true', () => {
    const {getByTestId} = renderRow({
      row: {id: 'r1', node: <span />},
      wasDragged: true,
    })
    expect(getByTestId('settings-panel-table-row-r1').className).toContain(
      'settings-panel-row-dragend',
    )
  })

  test('mouse down on the drag handle enables dragging, mouse up ends it', () => {
    const context = buildContext()
    const row = {id: 'r1', node: <span />, isDraggable: true}
    const {container, getByTestId} = renderRow({row, context})
    const handle = container.querySelector('.settings-panel-row-drag-handle')
    const outer = getByTestId('settings-panel-table-row-r1')

    expect(outer.getAttribute('draggable')).toBe('false')

    fireEvent.mouseDown(handle)
    expect(outer.getAttribute('draggable')).toBe('true')

    fireEvent.mouseUp(handle)
    expect(outer.getAttribute('draggable')).toBe('false')
    expect(context.onDragEnd).toHaveBeenCalledWith({row})
  })

  test('native dragstart marks the row as dragging and notifies the context', () => {
    const context = buildContext()
    const row = {id: 'r1', node: <span />}
    const {getByTestId} = renderRow({row, context, index: 3})
    const outer = getByTestId('settings-panel-table-row-r1')

    fireEvent.dragStart(outer)

    expect(outer.className).toContain('settings-panel-row-dragging')
    expect(context.onDragStart).toHaveBeenCalledWith(3)
  })

  test('dragover is ignored when the container ref does not match the drag start target', () => {
    const context = buildContext()
    context.dragStartInfoRef.current = {index: 0, targetContainer: null}
    const row = {id: 'r1', node: <span />}
    const {getByTestId} = renderRow({row, context})

    fireEvent(
      getByTestId('settings-panel-table-row-r1'),
      dragOverEvent({clientX: 10, clientY: 100}),
    )

    expect(context.onDragOver).not.toHaveBeenCalled()
  })

  test('dragover with a positive relative offset reports the bottom half', () => {
    const context = buildContext()
    context.dragStartInfoRef.current = {
      index: 0,
      targetContainer: context.rowsContainerRef.current,
    }
    const row = {id: 'r1', node: <span />}
    const {getByTestId} = renderRow({row, context, isDragOver: true})
    const outer = getByTestId('settings-panel-table-row-r1')

    fireEvent(outer, dragOverEvent({clientX: 10, clientY: 100}))

    expect(context.onDragOver).toHaveBeenCalledWith(
      expect.objectContaining({halfPoint: 'bottom', row}),
    )
    expect(outer.className).toContain(
      'settings-panel-row-dragover-half-bottom',
    )
  })

  test('dragover with a small relative offset reports the top half', () => {
    const context = buildContext()
    context.dragStartInfoRef.current = {
      index: 0,
      targetContainer: context.rowsContainerRef.current,
    }
    const row = {id: 'r1', node: <span />}
    const {getByTestId} = renderRow({row, context, isDragOver: true})
    const outer = getByTestId('settings-panel-table-row-r1')

    fireEvent(outer, dragOverEvent({clientX: 10, clientY: 10}))

    expect(context.onDragOver).toHaveBeenCalledWith(
      expect.objectContaining({halfPoint: 'top', row}),
    )
    expect(outer.className).toContain('settings-panel-row-dragover-half-top')
  })

  test('dragover with a zero relative offset defaults to the bottom half without updating state', () => {
    const context = buildContext()
    context.dragStartInfoRef.current = {
      index: 0,
      targetContainer: context.rowsContainerRef.current,
    }
    const row = {id: 'r1', node: <span />}
    const {getByTestId} = renderRow({row, context})
    const outer = getByTestId('settings-panel-table-row-r1')

    fireEvent(outer, dragOverEvent({clientX: 0, clientY: 0}))

    expect(context.onDragOver).toHaveBeenCalledWith(
      expect.objectContaining({halfPoint: 'bottom', row}),
    )
  })

  test('does not apply the dragover class when the row is locked', () => {
    const context = buildContext()
    context.dragStartInfoRef.current = {
      index: 0,
      targetContainer: context.rowsContainerRef.current,
    }
    const row = {id: 'r1', node: <span />, isLocked: true}
    const {getByTestId} = renderRow({row, context, isDragOver: true})
    const outer = getByTestId('settings-panel-table-row-r1')

    fireEvent(outer, dragOverEvent({clientX: 10, clientY: 100}))

    expect(outer.className).not.toContain('settings-panel-row-dragover')
  })
})
