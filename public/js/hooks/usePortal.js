import {useState, useCallback, useEffect} from 'react'
import ReactDOM from 'react-dom'

const usePortal = (el) => {
  const [portal, setPortal] = useState({
    render: () => null,
    remove: () => null,
  })

  const createPortal = useCallback((targetEl) => {
    let created = false
    if (targetEl === document.body) {
      targetEl = document.createElement('div')
      targetEl.className = 'portal-container'
      document.body.appendChild(targetEl)
      created = true
    }

    const Portal = ({children}) =>
      targetEl ? ReactDOM.createPortal(children, targetEl) : null

    const remove = () => {
      if (created && targetEl && targetEl.parentNode) {
        targetEl.parentNode.removeChild(targetEl)
      }
    }

    return {render: Portal, remove}
  }, [])

  useEffect(() => {
    if (el) portal.remove()

    const newPortal = createPortal(el)
    setPortal(newPortal)

    return () => newPortal.remove()
  }, [el])

  return portal.render
}

export default usePortal
