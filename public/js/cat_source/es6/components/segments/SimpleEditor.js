import React  from 'react';
import TagUtils from "../../utils/tagUtils";
import SegmentUtils from "../../utils/segmentUtils";

class SimpleEditor extends React.Component {

    constructor(props) {
        super(props);
    }



    render() {
        const { isTarget, sid, text, segment } = this.props;

        let htmlText = SegmentUtils.checkCurrentSegmentTPEnabled(segment) ?
            TagUtils.removeAllTags(text) : text;

        htmlText = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(htmlText), config.isTargetRTL));

        if ( segment.inSearch ) {
            htmlText = SearchUtils.markText(htmlText, !isTarget, sid);
        }

        return <div className={ `${isTarget ? 'target' : 'source'} item`}
                    id={`segment-${sid}-${isTarget ? 'target' : 'source'}`}>
            <div className={isTarget ? `targetarea editarea` : ``}
                 dangerouslySetInnerHTML={{ __html: htmlText }}/>
        </div>
    }
}

export default SimpleEditor;
