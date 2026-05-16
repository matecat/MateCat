import {useState, useRef, useCallback, useEffect} from 'react'

const useResizable = ({initialHeight = 500, minHeight = 100, maxHeight}) => {
  const [height, setHeight] = useState(initialHeight)
  const [isDragging, setIsDragging] = useState(false)
  const isDraggingRef = useRef(false)
  const startYRef = useRef(0)
  const startHeightRef = useRef(0)

  const handleMouseDown = useCallback((e) => {
    e.preventDefault()
    isDraggingRef.current = true
    setIsDragging(true)
    startYRef.current = e.clientY
    startHeightRef.current = height
    document.body.style.cursor = 'row-resize'
    document.body.style.userSelect = 'none'
  }, [height])

  useEffect(() => {
    const handleMouseMove = (e) => {
      if (!isDraggingRef.current) return
      const delta = startYRef.current - e.clientY
      const computedMax = maxHeight || window.innerHeight - 200
      const newHeight = Math.min(
        Math.max(startHeightRef.current + delta, minHeight),
        computedMax,
      )
      setHeight(newHeight)
    }

    const handleMouseUp = () => {
      if (!isDraggingRef.current) return
      isDraggingRef.current = false
      setIsDragging(false)
      document.body.style.cursor = ''
      document.body.style.userSelect = ''
    }

    window.addEventListener('mousemove', handleMouseMove)
    window.addEventListener('mouseup', handleMouseUp)

    return () => {
      window.removeEventListener('mousemove', handleMouseMove)
      window.removeEventListener('mouseup', handleMouseUp)
      if (isDraggingRef.current) {
        isDraggingRef.current = false
        document.body.style.cursor = ''
        document.body.style.userSelect = ''
      }
    }
  }, [minHeight, maxHeight])

  return {height, isDragging, handleMouseDown}
}

export default useResizable

