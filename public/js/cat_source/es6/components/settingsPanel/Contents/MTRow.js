import React from 'react'

export const MTRow = ({row}) => {
  return (
    <>
      <div>{row.name}</div>
      <div>{row.description}</div>
      <div>
        <input type="checkbox"></input>
      </div>
      <div>
        <button className="settings-panel-button">Delete</button>
      </div>
    </>
  )
}
