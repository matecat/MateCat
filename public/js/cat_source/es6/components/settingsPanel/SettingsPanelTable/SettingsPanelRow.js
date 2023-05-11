import React, {useRef, useState, forwardRef} from 'react'
import PropTypes from 'prop-types'

export const SettingsPanelRow = forwardRef(
  (
    {row, index, isDragOver, parentRef, onDragStart, onDragOver, onDragEnd},
    ref,
  ) => {
    const [isActiveDrag, setIsActiveDrag] = useState(false)
    const [isDragging, setIsDragging] = useState(false)

    const refDragHandle = useRef()

    const {isDraggable, node} = row

    const onDragEndCallback = () => {
      setIsActiveDrag(false)
      setIsDragging(false)
      if (onDragEnd) onDragEnd()
    }

    const onDragging = (event) => {
      if (!parentRef?.current) return

      const rect = parentRef.current.getBoundingClientRect()
      const point = {x: event.clientX - rect.x, y: event.clientY - rect.y}
      if (onDragOver) onDragOver({...point})
    }

    return (
      <div
        ref={ref}
        className={`settings-panel-row${
          isDragging ? ' settings-panel-row-dragging' : ''
        }
        ${isDragOver ? ' settings-panel-row-dragover' : ''}`}
        draggable={isActiveDrag}
        onDragStart={() => {
          setIsDragging(true)
          if (onDragStart) onDragStart(index)
        }}
        onDragOver={onDragging}
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
  row: PropTypes.shape({
    node: PropTypes.node.isRequired,
    isDraggable: PropTypes.bool,
  }),
  index: PropTypes.number.isRequired,
  isDragOver: PropTypes.bool,
  parentRef: PropTypes.object,
  onDragStart: PropTypes.func,
  onDragOver: PropTypes.func,
  onDragEnd: PropTypes.func,
}
