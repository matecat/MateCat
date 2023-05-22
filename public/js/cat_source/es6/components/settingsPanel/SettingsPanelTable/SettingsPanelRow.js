import React, {useRef, useState, forwardRef, useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelTableContext} from './SettingsPanelTable'

export const SettingsPanelRow = forwardRef(
  ({index, row, isDragOver, wasDragged}, ref) => {
    const {
      rowsContainerRef,
      dragStartIndexRef,
      onDragStart,
      onDragOver,
      onDragEnd,
    } = useContext(SettingsPanelTableContext)

    const [isActiveDrag, setIsActiveDrag] = useState(false)
    const [isDragging, setIsDragging] = useState(false)
    const [halfDragPoint, setHalfDragPoint] = useState()

    const refDragHandle = useRef()

    const {isDraggable, isLocked, node} = row

    const onDragStartCallback = () => {
      setIsDragging(true)
      onDragStart(index)
    }

    const onDragOverCallback = (event) => {
      if (!rowsContainerRef?.current) return

      const rect = rowsContainerRef.current.getBoundingClientRect()
      const point = {x: event.clientX - rect.x, y: event.clientY - rect.y}

      const relativeY = event.clientY - ref.current.getBoundingClientRect().y
      const rowHeight = ref.current.offsetHeight
      const halfPoint = relativeY
        ? relativeY > rowHeight / 2
          ? 'bottom'
          : 'top'
        : 'bottom'

      if (relativeY)
        setHalfDragPoint(relativeY > rowHeight / 2 ? 'bottom' : 'top')

      if (onDragOver) onDragOver({...point, halfPoint})
    }

    const onDragEndCallback = () => {
      setIsActiveDrag(false)
      setIsDragging(false)
      if (onDragEnd) onDragEnd({row})
    }

    const shouldNotAddApplyDragOver =
      isLocked ||
      (dragStartIndexRef.current?.index + 1 === index &&
        halfDragPoint === 'top') ||
      (dragStartIndexRef.current?.index > 0 &&
        dragStartIndexRef.current?.index - 1 === index &&
        halfDragPoint === 'bottom')

    const draggingCssClasses = `${
      isDragging ? ' settings-panel-row-dragging' : ''
    }`

    const dragOverCssClasses = `${
      !isDragging && !shouldNotAddApplyDragOver && isDragOver
        ? halfDragPoint === 'top'
          ? ' settings-panel-row-dragover-half-top'
          : ' settings-panel-row-dragover-half-bottom'
        : ''
    }`

    const shouldAddDragendCssClass = wasDragged
      ? ' settings-panel-row-dragend'
      : ''

    return (
      <div
        ref={ref}
        className={`settings-panel-row${draggingCssClasses}${dragOverCssClasses}${shouldAddDragendCssClass}`}
        draggable={isActiveDrag}
        onDragStart={onDragStartCallback}
        onDragOver={onDragOverCallback}
        onDragEnd={onDragEndCallback}
      >
        <>
          {isDraggable && (
            <div
              ref={refDragHandle}
              className="settings-panel-row-drag-handle"
              onMouseDown={() => setIsActiveDrag(true)}
              onMouseUp={onDragEndCallback}
            >
              |
            </div>
          )}
          {node}
        </>
      </div>
    )
  },
)

SettingsPanelRow.displayName = 'SettingsPanelRow'

SettingsPanelRow.propTypes = {
  index: PropTypes.number.isRequired,
  row: PropTypes.shape({
    node: PropTypes.node.isRequired,
    isDraggable: PropTypes.bool,
  }).isRequired,
  isDragOver: PropTypes.bool,
  wasDragged: PropTypes.bool,
}
