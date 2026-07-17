import React from 'react'
class WrapperLoader extends React.Component {
  constructor(props) {
    super(props)
    this.state = {}
  }

  render() {
    let overlayLoaderStyle = {
      position: 'absolute',
      left: 0,
      top: 0,
      background: 'rgba(255,255,255,0.6)',
      width: '100%',
      height: '100%',
      zIndex: 999,
    }
    let loaderStyle = {
      left: '50%',
      top: '50%',
      transform: 'translate(-50%)',
      display: 'block',
    }
    return (
      <div className="overlayLoader" style={overlayLoaderStyle}>
        <div className="loader" style={loaderStyle}></div>
      </div>
    )
  }
}

export default WrapperLoader
