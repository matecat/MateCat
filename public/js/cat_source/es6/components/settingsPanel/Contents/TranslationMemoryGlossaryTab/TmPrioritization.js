import React, {useState} from 'react'
import Switch from '../../../common/Switch'

export const TmPrioritization = () => {
  const [isActive, setIsActive] = useState(false)

  const onChange = (isActive) => {
    setIsActive(isActive)
  }

  return (
    <div className="tm-prioritization-container">
      <div className="tm-prioritization-text-content">
        <h4>Activate prioritization</h4>
        <span>Lorem ipsum bla bla</span>
      </div>
      <Switch onChange={onChange} active={isActive} />
    </div>
  )
}
