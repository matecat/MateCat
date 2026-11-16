import {useState, useRef, useCallback, useEffect} from 'react'

/**
 * Height of a panel the user resizes by dragging its top edge.
 *
 * With `initialRatio` the height is a fraction of the window height and follows
 * window resizes, including after the user has dragged it. Without it the height
 * is a fixed number of pixels (`initialHeight`).
 */
const useResizable = ({
  initialHeight = 500,
  initialRatio,
  minHeight = 100,
  maxHeight,
}) => {
  const isProportional = typeof initialRatio === 'number'
  const [fixedHeight, setFixedHeight] = useState(initialHeight)
  const [ratio, setRatio] = useState(initialRatio)
  const [viewportHeight, setViewportHeight] = useState(() => window.innerHeight)
  const [isDragging, setIsDragging] = useState(false)
  const isDraggingRef = useRef(false)
  const startYRef = useRef(0)
  const startHeightRef = useRef(0)

  const clamp = useCallback(
    (value, windowHeight) =>
      Math.min(Math.max(value, minHeight), maxHeight || windowHeight - 200),
    [minHeight, maxHeight],
  )

  const height = isProportional
    ? clamp(Math.round(ratio * viewportHeight), viewportHeight)
    : fixedHeight

  const handleMouseDown = useCallback(
    (e) => {
      e.preventDefault()
      isDraggingRef.current = true
      setIsDragging(true)
      startYRef.current = e.clientY
      startHeightRef.current = height
      document.body.style.cursor = 'row-resize'
      document.body.style.userSelect = 'none'
    },
    [height],
  )

  useEffect(() => {
    if (!isProportional) return
    const handleResize = () => setViewportHeight(window.innerHeight)
    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [isProportional])

  useEffect(() => {
    const handleMouseMove = (e) => {
      if (!isDraggingRef.current) return
      const delta = startYRef.current - e.clientY
      const newHeight = clamp(
        startHeightRef.current + delta,
        window.innerHeight,
      )
      if (isProportional) {
        setRatio(newHeight / window.innerHeight)
      } else {
        setFixedHeight(newHeight)
      }
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
  }, [clamp, isProportional])

  return {height, isDragging, handleMouseDown}
}

export default useResizable
