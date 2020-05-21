
import React, {Component} from 'react';
import SegmentActions from '../../../actions/SegmentActions';

class GlossaryHighlight extends Component {
    constructor(props) {
        super(props);
    }
    render() {
        const { children, sid } = this.props;
        return <span className={'glossaryItem'} style={{borderBottom: '1px dotted #c0c', cursor: 'pointer'}}
                onClick={()=>SegmentActions.activateTab(sid, 'glossary')}
        >{children}</span>
    };
}


export default GlossaryHighlight;
