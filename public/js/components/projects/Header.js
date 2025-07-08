import React from 'react'
import ReactDOM from 'react-dom'
class DasboardHeader extends React.Component {
  render() {
    const headerMountPoint = document.getElementsByTagName('header')[0]
    return ReactDOM.createPortal(this.props.children, headerMountPoint)
  }
}

export default DasboardHeader
