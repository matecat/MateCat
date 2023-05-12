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

export const SettingsPanelTable = ({columns, rows, onChangeRowsOrder}) => {
  const [dragOverIndex, setDragOverIndex] = useState()

  const rowsContainerRef = useRef()
  const rowsRef = useRef([])
  const draggingIndexRef = useRef()
  const previousDragIndex = useRef()

  // drag and drop callback's
  const onDragStart = useCallback((index) => {
    draggingIndexRef.current = {index}
    previousDragIndex.current = undefined
  }, [])

  const onDragOver = useCallback(({y, halfPoint}) => {
    const parentRect = rowsContainerRef.current.getBoundingClientRect()

    const indexToMove = rowsRef.current.findIndex(({current}, index) => {
      const rect = current.getBoundingClientRect()
      const currentY = rect.y - parentRect.y

      const nextNode = rowsRef.current[index + 1]?.current
      const nextY = nextNode
        ? nextNode.getBoundingClientRect().y - parentRect.y
        : currentY + current.offsetHeight

      if (y >= currentY && y <= nextY) return true
    })

    if (previousDragIndex.current !== indexToMove) setDragOverIndex(indexToMove)

    draggingIndexRef.current = {
      ...draggingIndexRef.current,
      halfPoint,
    }
    previousDragIndex.current = indexToMove
  }, [])

  const onDragEnd = useCallback(() => {
    const isValidRange =
      draggingIndexRef.current?.index >= 0 &&
      dragOverIndex >= 0 &&
      draggingIndexRef.current?.index !== dragOverIndex

    if (isValidRange)
      onChangeRowsOrder({
        index: draggingIndexRef.current.index,
        indexToMove:
          draggingIndexRef.current.halfPoint === 'bottom'
            ? dragOverIndex + 1
            : dragOverIndex,
      })

    setDragOverIndex(undefined)
  }, [dragOverIndex, onChangeRowsOrder])

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
        }}
      />
    )
  }

  return (
    <SettingsPanelTableContext.Provider
      value={{
        rowsContainerRef,
        draggingIndexRef,
        onDragStart,
        onDragOver,
        onDragEnd,
      }}
    >
      <div className="settings-panel-table">
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
}
