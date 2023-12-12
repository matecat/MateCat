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

  const onChangeRowsOrderRef = useRef()
  onChangeRowsOrderRef.current = onChangeRowsOrder

  const rowsContainerRef = useRef()
  const rowsRef = useRef([])
  const dragStartInfoRef = useRef()
  const previousDragOverIndex = useRef()
  const dragEndRow = useRef()

  // drag and drop callback's
  const onDragStart = useCallback((index) => {
    dragStartInfoRef.current = {
      index,
      targetContainer: rowsContainerRef.current,
    }
    previousDragOverIndex.current = undefined
    dragEndRow.current = undefined
  }, [])

  const onDragOver = useCallback(({y, halfPoint, row}) => {
    const parentRect = rowsContainerRef.current.getBoundingClientRect()

    if (row.isLocked) {
      setDragOverIndex(undefined)
      previousDragOverIndex.current = undefined
      return
    }

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

    dragStartInfoRef.current = {
      ...dragStartInfoRef.current,
      halfPoint,
    }
    previousDragOverIndex.current = indexToMove
  }, [])

  const onDragEnd = useCallback(
    ({row}) => {
      const isValidRange =
        dragStartInfoRef.current?.index >= 0 &&
        dragOverIndex >= 0 &&
        dragStartInfoRef.current?.index !== dragOverIndex

      if (isValidRange) {
        const {index: startIndex, halfPoint} = dragStartInfoRef.current
        const indexToMove =
          halfPoint === 'bottom' ? dragOverIndex + 1 : dragOverIndex

        if (startIndex !== indexToMove)
          onChangeRowsOrderRef.current({
            index: startIndex,
            indexToMove,
          })
      }

      setDragOverIndex(undefined)
      dragEndRow.current = row
    },
    [dragOverIndex],
  )

  const renderColumns = (column, index) => (
    <div key={index} className="settings-panel-table-rowHeading-column">
      {column.name}
    </div>
  )
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
        dragStartInfoRef,
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
            (rows.length ? (
              rows.map(renderItems)
            ) : (
              <div className="settings-panel-table-row-empty"></div>
            ))}
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
