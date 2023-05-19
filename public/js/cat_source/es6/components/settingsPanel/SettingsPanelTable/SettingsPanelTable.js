import React, {
  useCallback,
  useRef,
  createRef,
  useState,
  createContext,
} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelRow} from './SettingsPanelRow'

export const SettingsPanelTableContext = createContext({})

export const SettingsPanelTable = ({
  columns,
  rows,
  onChangeRowsOrder,
  className,
}) => {
  const [dragOverIndex, setDragOverIndex] = useState()

  const rowsContainerRef = useRef()
  const rowsRef = useRef([])
  const dragStartIndexRef = useRef()
  const previousDragOverIndex = useRef()
  const dragEndRow = useRef()

  // drag and drop callback's
  const onDragStart = useCallback((index) => {
    dragStartIndexRef.current = {index}
    previousDragOverIndex.current = undefined
    dragEndRow.current = undefined
  }, [])

  const onDragOver = useCallback(({y, halfPoint}) => {
    const parentRect = rowsContainerRef.current.getBoundingClientRect()

    const indexToMove = rowsRef.current.findIndex(({current}, index) => {
      if (!current) return false

      const rect = current.getBoundingClientRect()
      const currentY = rect.y - parentRect.y

      const nextNode = rowsRef.current[index + 1]?.current
      const nextY = nextNode
        ? nextNode.getBoundingClientRect().y - parentRect.y
        : currentY + current.offsetHeight

      if (y >= currentY && y <= nextY) return true
    })

    if (previousDragOverIndex.current !== indexToMove)
      setDragOverIndex(indexToMove)

    dragStartIndexRef.current = {
      ...dragStartIndexRef.current,
      halfPoint,
    }
    previousDragOverIndex.current = indexToMove
  }, [])

  const onDragEnd = useCallback(
    ({row}) => {
      const isValidRange =
        dragStartIndexRef.current?.index >= 0 &&
        dragOverIndex >= 0 &&
        dragStartIndexRef.current?.index !== dragOverIndex

      const {index: startIndex, halfPoint} = dragStartIndexRef.current

      if (isValidRange) {
        const indexToMove =
          halfPoint === 'bottom' ? dragOverIndex + 1 : dragOverIndex

        if (startIndex !== indexToMove)
          onChangeRowsOrder({
            index: startIndex,
            indexToMove,
          })
      }

      setDragOverIndex(undefined)
      dragEndRow.current = row
    },
    [dragOverIndex, onChangeRowsOrder],
  )

  const renderColumns = (column, index) => <div key={index}>{column.name}</div>
  const renderItems = (row, index) => {
    const ref = createRef()
    rowsRef.current[index] = ref

    return (
      <SettingsPanelRow
        ref={ref}
        key={index}
        {...{
          index,
          row,
          isDragOver: dragOverIndex === index,
          wasDragged: dragEndRow.current === row,
        }}
      />
    )
  }

  const customClassName = className ? ` ${className}` : ''

  return (
    <SettingsPanelTableContext.Provider
      value={{
        rowsContainerRef,
        dragStartIndexRef,
        onDragStart,
        onDragOver,
        onDragEnd,
      }}
    >
      <div className={`settings-panel-table${customClassName}`}>
        <div>
          <div className="settings-panel-table-rowHeading">
            {columns.map(renderColumns)}
          </div>
        </div>
        <div
          ref={rowsContainerRef}
          className="settings-panel-table-rows-container"
        >
          {Array.isArray(rows) &&
            (rows.length ? rows.map(renderItems) : <span>No results</span>)}
        </div>
      </div>
    </SettingsPanelTableContext.Provider>
  )
}

SettingsPanelTable.propTypes = {
  columns: PropTypes.arrayOf(
    PropTypes.shape({name: PropTypes.string.isRequired}),
  ).isRequired,
  rows: PropTypes.arrayOf(PropTypes.object).isRequired,
  onChangeRowsOrder: PropTypes.func,
  className: PropTypes.string,
}
