import _ from 'lodash'

const SearchHighlight = (props) => {
  let occurrence = _.find(props.occurrences, (occ) => occ.start === props.start)
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

module.exports = SearchHighlight
