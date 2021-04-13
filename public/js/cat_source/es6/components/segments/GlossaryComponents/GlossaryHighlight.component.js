import React, {Component} from 'react'

class GlossaryHighlight extends Component {
  constructor(props) {
    super(props)
  }
  render() {
    const {children, sid, onClickAction} = this.props
    return (
      <span
        className={'glossaryItem'}
        style={{borderBottom: '1px dotted #c0c', cursor: 'pointer'}}
        onClick={() => onClickAction(sid, 'glossary')}
      >
        {children}
      </span>
    )
  }
}

export default GlossaryHighlight
