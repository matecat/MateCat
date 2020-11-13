/**
 * React Component .

 */
import React  from 'react';
import Immutable  from 'immutable';
import TagUtils from "../../utils/tagUtils";
import TextUtils from "../../utils/textUtils";


class SegmentFooterTabConflicts extends React.Component {

    constructor(props) {
        super(props);
    }

    chooseAlternative(text) {
        SegmentActions.setFocusOnEditArea();
        SegmentActions.disableTPOnSegment(this.props.segment);
        setTimeout(()=>{
            SegmentActions.replaceEditAreaTextContent( this.props.segment.sid, text);
            SegmentActions.modifiedTranslation(this.props.segment.sid, true);
        });
    }

    renderAlternatives(alternatives) {
        const segment = this.props.segment;
        const segment_id = this.props.segment.sid;
        let html = [];
        const self = this;
        const source = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(segment.segment)));
        $.each(alternatives.editable, function(index) {
            // Execute diff
            let diff_obj = TagUtils.executeDiff( segment.translation, this.translation );
            let translation = TextUtils.diffMatchPatch.diff_prettyHtml(diff_obj);
            translation = translation.replace(/&amp;/g, "&");
            translation = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(translation)));
            // No diff executed on source
            html.push(<ul className="graysmall" data-item={(index + 1)} key={'editable' + index} onDoubleClick={()=>self.chooseAlternative(this.translation)}>
                        <li className="sugg-source">
                            <span id={segment_id + '-tm-' + this.id + '-source'} className="suggestion_source" dangerouslySetInnerHTML={self.allowHTML(source)}/>
                        </li>
                        <li className="b sugg-target">
                            {/*<span className="graysmall-message">{'CTRL' + (index + 1)}</span>*/}
                            <span className="translation" dangerouslySetInnerHTML={self.allowHTML(translation)} />
                            <span className="realData hide" dangerouslySetInnerHTML={self.allowHTML(this.translation)}/>
                        </li>
                        <li className="goto">
                            <a data-goto={this.involved_id[0]} onClick={()=>SegmentActions.openSegment(this.involved_id[0])}>Go to</a>
                        </li>
                    </ul>);
        });

        $.each(alternatives.not_editable, function(index1) {
            // Execute diff
            let diff_obj = TagUtils.executeDiff( segment.translation, this.translation );
            // Restore Tags
            let translation = TextUtils.diffMatchPatch.diff_prettyHtml(diff_obj);
            translation = translation.replace(/&amp;/g, "&");
            translation = TagUtils.matchTag(TagUtils.decodeHtmlInTag(TagUtils.decodePlaceholdersToTextSimple(translation)));
            // No diff executed on source
            html.push( <ul className="graysmall notEditable" data-item={(index1 + alternatives.editable.length + 1)} key={'not-editable' + index1}  onDoubleClick={()=>self.chooseAlternative(this.translation)}>
                <li className="sugg-source">
                    <span id={segment_id + '-tm-' + this.id + '-source'} className="suggestion_source" dangerouslySetInnerHTML={self.allowHTML(source)}/>
                </li>
                <li className="b sugg-target">
                    {/*<span className="graysmall-message">{'CTRL+' + (index1 + alternatives.data.editable.length + 1)}</span>*/}
                    <span className="translation" dangerouslySetInnerHTML={self.allowHTML(translation)}/>
                    <span className="realData hide" dangerouslySetInnerHTML={self.allowHTML(this.translation)}/>
                </li>
                <li className="goto">
                    <a data-goto={this.involved_id[0]} onClick={()=>SegmentActions.openSegment(this.involved_id[0])}>Go to</a>
                </li>
            </ul>);
        });

        return html;
    }


    componentDidMount() {

    }

    componentWillUnmount() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    shouldComponentUpdate(nextProps, nextState) {
        return this.props.active_class !== nextProps.active_class || this.props.tab_class !== nextProps.tab_class ||
            ( (!_.isUndefined(nextProps.segment.alternatives) || !_.isUndefined(this.props.segment.alternatives)) &&
            ( (!_.isUndefined(nextProps.segment.alternatives) && _.isUndefined(this.props.segment.alternatives)) ||
                !Immutable.fromJS(this.props.segment.alternatives).equals(Immutable.fromJS(nextProps.segment.alternatives)) ) )
    }

    render() {
        let html;
        if ( this.props.segment.alternatives && _.size(this.props.segment.alternatives) > 0  ) {
            html = this.renderAlternatives(this.props.segment.alternatives);
            return (
                <div key={"container_" + this.props.code} className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                     id={"segment-" + this.props.id_segment + "-" + this.props.tab_class}>
                    <div className="overflow">
                        {html}
                    </div>
                </div>
            )
        } else {
            return '';
        }

    }
}

export default SegmentFooterTabConflicts;