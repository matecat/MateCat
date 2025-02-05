import React, {Component, createRef} from 'react'
import SegmentActions from '../../../actions/SegmentActions'
import Tooltip from '../../common/Tooltip'

class GlossaryHighlight extends Component {
  constructor(props) {
    super(props)
    this.contentRef = createRef()
  }
  onClickTerm = () => {
    const {glossary, children, sid} = this.props
    const text = children[0].props.text.trim()
    const glossaryTerm = glossary.find(({matching_words: matchingWords}) =>
      matchingWords.find((value) => value.toLowerCase() === text.toLowerCase()),
    )
    //Call Segment footer Action
    SegmentActions.highlightGlossaryTerm({
      sid,
      termId: glossaryTerm.term_id,
      type: 'glossary',
    })
  }
  render() {
    const {children} = this.props

    return (
      <Tooltip
        stylePointerElement={{display: 'inline-block', position: 'relative'}}
        content="Glossary term"
      >
        <div ref={this.contentRef} className="glossaryItem">
          <span onClick={() => this.onClickTerm()}>{children}</span>
        </div>
      </Tooltip>
    )
  }
}

export default GlossaryHighlight
