import React from 'react'

const SearchHighlight = (props) => {
  let occurrence = props.occurrences.find(
    (occ) => occ.start === props.start && occ.key === props.blockKey,
  )
  if (occurrence && occurrence.searchProgressiveIndex === props.currentIndex) {
    return (
      <span style={{backgroundColor: 'rgb(255,210,14)'}}>{props.children}</span>
    )
  }
  return (
    <span style={{backgroundColor: 'rgba(255, 255, 0, 1.0)'}}>
      {props.children}
    </span>
  )
}

export default SearchHighlight
