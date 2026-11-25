import ReactDOM from 'react-dom'

const DashboardHeader = ({children}) =>
  ReactDOM.createPortal(children, document.getElementsByTagName('header')[0])

export default DashboardHeader
