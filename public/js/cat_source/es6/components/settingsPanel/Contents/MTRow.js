import React from 'react'

export const MTRow = ({row}) => {
  return (
    <>
      <div>{row.name}</div>
      <div>{row.description}</div>
    </>
  )
}
