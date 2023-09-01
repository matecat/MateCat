import {find} from 'lodash'

const SearchHighlight = (props) => {
  let occurrence = find(props.occurrences, (occ) => occ.start === props.start)
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
