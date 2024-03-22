import React, {useRef, useState, forwardRef, useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelTableContext} from './SettingsPanelTable'

export const SettingsPanelRow = forwardRef(
  ({index, row, isDragOver, wasDragged}, ref) => {
    const {
      rowsContainerRef,
      dragStartInfoRef,
      onDragStart,
      onDragOver,
      onDragEnd,
    } = useContext(SettingsPanelTableContext)

    const [isActiveDrag, setIsActiveDrag] = useState(false)
    const [isDragging, setIsDragging] = useState(false)
    const [halfDragPoint, setHalfDragPoint] = useState()

    const dragHandleRef = useRef()

    const {
      isDraggable,
      isLocked,
      isExpanded,
      className = '',
      node,
      extraNode,
    } = row

    const onDragStartCallback = () => {
      setIsDragging(true)
      onDragStart(index)
    }

    const onDragOverCallback = (event) => {
      if (
        !rowsContainerRef.current ||
        rowsContainerRef.current !== dragStartInfoRef.current?.targetContainer
      )
        return

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

      if (onDragOver) onDragOver({...point, halfPoint, row})
    }

    const onDragEndCallback = () => {
      setIsActiveDrag(false)
      setIsDragging(false)
      if (onDragEnd) onDragEnd({row})
    }

    const shouldNotAddApplyDragOver =
      isLocked ||
      (dragStartInfoRef.current?.index + 1 === index &&
        halfDragPoint === 'top') ||
      (dragStartInfoRef.current?.index > 0 &&
        dragStartInfoRef.current?.index - 1 === index &&
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
        data-testid={`settings-panel-table-row-${row.id}`}
      >
        <>
          {isDraggable && (
            <div
              ref={dragHandleRef}
              className="settings-panel-row-drag-handle"
              onMouseDown={() => setIsActiveDrag(true)}
              onMouseUp={onDragEndCallback}
            ></div>
          )}
          <div
            className={`settings-panel-row-content ${
              row.isActive && !row.isLocked ? ' settings-panel-row-active' : ''
            } ${className}`}
          >
            {node}
          </div>
          <div
            className={`settings-panel-row-extra-content${
              isExpanded ? ' settings-panel-row-extra-content-expanded' : ''
            }`}
          >
            {isExpanded && extraNode}
          </div>
        </>
      </div>
    )
  },
)

SettingsPanelRow.displayName = 'SettingsPanelRow'

SettingsPanelRow.propTypes = {
  index: PropTypes.number.isRequired,
  row: PropTypes.shape({
    isDraggable: PropTypes.bool,
    isLocked: PropTypes.bool,
    isExpanded: PropTypes.bool,
    node: PropTypes.node.isRequired,
  }).isRequired,
  isDragOver: PropTypes.bool,
  wasDragged: PropTypes.bool,
}
