import React, {useCallback, useRef, createRef, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelRow} from './SettingsPanelRow'

export const SettingsPanelTable = ({columns, rows}) => {
  const [dragOverIndex, setDragOverIndex] = useState()

  const rowsContainerRef = useRef()
  const rowsRef = useRef([])
  const dragginIndexRef = useRef()
  const previousDragIndex = useRef()

  // drag and drop callback's
  const onDragStart = useCallback((index) => {
    dragginIndexRef.current = index
    previousDragIndex.current = undefined
  }, [])

  const onDragOver = useCallback(({y}) => {
    const parentRect = rowsContainerRef.current.getBoundingClientRect()

    const indexToReplace = rowsRef.current.findIndex(({current}, index) => {
      const rect = current.getBoundingClientRect()
      const currentY = rect.y - parentRect.y

      const nextNode = rowsRef.current[index + 1]?.current
      const nextY = nextNode
        ? nextNode.getBoundingClientRect().y - parentRect.y
        : currentY + current.offsetHeight

      if (y >= currentY && y <= nextY) return true
    })

    if (previousDragIndex.current !== indexToReplace) {
      console.log(
        '# index',
        dragginIndexRef.current,
        'index to replace',
        indexToReplace,
      )

      setDragOverIndex(indexToReplace)
    }

    previousDragIndex.current = indexToReplace
  }, [])

  const onDragEnd = useCallback(() => {
    setDragOverIndex(undefined)
  }, [])

  const renderColumns = (column, index) => <div key={index}>{column.name}</div>
  const renderItems = (row, index) => {
    const ref = createRef()
    rowsRef.current[index] = ref

    return (
      <SettingsPanelRow
        ref={ref}
        key={index}
        {...{
          row,
          index,
          isDragOver: dragOverIndex === index,
          parentRef: rowsContainerRef,
          onDragStart,
          onDragOver,
          onDragEnd,
        }}
      />
    )
  }

  return (
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
  )
}

SettingsPanelTable.propTypes = {
  columns: PropTypes.arrayOf(
    PropTypes.shape({name: PropTypes.string.isRequired}),
  ).isRequired,
  rows: PropTypes.arrayOf(PropTypes.object).isRequired,
}
