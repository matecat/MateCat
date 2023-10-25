import {useState, useCallback, useEffect} from 'react'
import ReactDOM from 'react-dom'
const usePortal = (el) => {
  const [portal, setPortal] = useState({
    render: () => null,
    remove: () => null,
  })

  const createPortal = useCallback((el) => {
    //render a portal at the given DOM node:
    const Portal = ({children}) => ReactDOM.createPortal(children, el)
    //delete the portal from memory:
    const remove = () => ReactDOM.unmountComponentAtNode(el)
    return {render: Portal, remove}
  }, [])

  useEffect(() => {
    //if there is an existing portal, remove the new instance.
    //is prevents memory leaks
    if (el) portal.remove()
    //otherwise, create a new portal and render it
    const newPortal = createPortal(el)
    setPortal(newPortal)
    //when the user exits the page, delete the portal from memory.
    return () => newPortal.remove(el)
  }, [el])

  return portal.render
}
export default usePortal //link this Hook with the project
