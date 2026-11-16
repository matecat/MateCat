import {useMemo, useState} from 'react'

const useShowMore = (items, limit = 5) => {
  const [isExpanded, setIsExpanded] = useState(false)

  const hasMore = items.length > limit

  const visibleItems = useMemo(() => items.slice(0, limit), [items, limit])
  const extraItems = useMemo(() => items.slice(limit), [items, limit])

  return {
    visibleItems,
    extraItems,
    isExpanded,
    hasMore,
    toggle: () => setIsExpanded((prev) => !prev),
  }
}

export default useShowMore
