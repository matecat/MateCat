import ReactDOM from 'react-dom'

const Header = ({children}) => {
  const headerMountPoint = document.getElementsByTagName('header')[0]
  return ReactDOM.createPortal(children, headerMountPoint)
}

export default Header
