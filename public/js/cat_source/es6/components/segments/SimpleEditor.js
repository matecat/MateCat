import React  from 'react';
import TagUtils from "../../utils/tagUtils";

class SimpleEditor extends React.Component {

    constructor(props) {
        super(props);
    }



    render() {
        const { isTarget, sid, text } = this.props;
        const htmlText = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(text), config.isTargetRTL));

        return <div className={ `${isTarget ? 'target' : 'source'} item`}
                    id={`segment-${sid}-${isTarget ? 'target' : 'source'}`}>
            <div className={isTarget ? `targetarea editarea` : ``}
                 dangerouslySetInnerHTML={{ __html: htmlText }}/>
        </div>
    }
}

export default SimpleEditor;
