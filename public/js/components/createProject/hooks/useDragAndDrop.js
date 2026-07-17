import {useState, useRef, useCallback} from 'react'
import CreateProjectActions from '../../../actions/CreateProjectActions'

/**
 * Custom hook that manages drag-and-drop interactions for file upload.
 */
export function useDragAndDrop({handleFiles}) {
  const [isDragging, setIsDragging] = useState(false)
  const dragCounter = useRef(0)

  const handleDrop = useCallback(
    (e) => {
      e.preventDefault()
      CreateProjectActions.hideErrors()
      dragCounter.current = 0
      let files = Array.from(e.dataTransfer.files)

      for (var i = 0; i < files.length; i++) {
        let f = files[i]
        if (f.type === '' && f.size % 4096 === 0) {
          CreateProjectActions.showError(
            'Uploading unzipped folders is not allowed. Please upload individual files, or a zipped folder.',
          )
          files = files.filter((file) => file !== f)
        }
      }
      handleFiles(files)
      setIsDragging(false)
    },
    [handleFiles],
  )

  const handleDragEnter = useCallback((e) => {
    e.preventDefault()
    dragCounter.current += 1
    if (dragCounter.current === 1) {
      setIsDragging(true)
    }
  }, [])

  const handleDragLeave = useCallback((e) => {
    e.preventDefault()
    dragCounter.current -= 1
    if (dragCounter.current === 0) {
      setIsDragging(false)
    }
  }, [])

  const handleDragOver = useCallback((e) => {
    e.preventDefault()
  }, [])

  const dragHandlers = {
    onDrop: handleDrop,
    onDragEnter: handleDragEnter,
    onDragLeave: handleDragLeave,
    onDragOver: handleDragOver,
  }

  return {isDragging, dragHandlers}
}
