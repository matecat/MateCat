/**
 * React Component .

 */
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabConflicts extends React.Component {

    constructor(props) {
        super(props);
    }

    renderAlternatives(alternatives) {
        let segment = this.props.segment;
        let segment_id = this.props.segment.sid;
        let escapedSegment = UI.decodePlaceholdersToText(segment.segment);
        // Take the .editarea content with special characters (Ex: ##$_0A$##) and transform the placeholders
        let mainStr = htmlEncode(UI.prepareTextToSend(segment.decoded_translation)).replace(/&amp;/g, "&");
        let html = [];
        let self = this;
        let replacementsMap;
        $.each(alternatives.editable, function(index) {
            // Decode the string from the server
            let transDecoded = this.translation;
            // Make the diff between the text with the same codification

            [ mainStr, transDecoded, replacementsMap ] = UI._treatTagsAsBlock( mainStr, transDecoded, [] );

            let diff_obj = UI.execDiff( mainStr, transDecoded );

            //replace the original string in the diff object by the character placeholder
            Object.keys( diff_obj ).forEach( ( element ) => {
                if( replacementsMap[ diff_obj[ element ][ 1 ] ] ){
                    diff_obj[ element ][ 1 ] = replacementsMap[ diff_obj[ element ][ 1 ] ];
                }
            } );

            let translation = UI.transformTextForLockTags(UI.dmp.diff_prettyHtml(diff_obj));
            html.push(<ul className="graysmall" data-item={(index + 1)} key={'editable' + index}>
                        <li className="sugg-source">
                            <span id={segment_id + '-tm-' + this.id + '-source'} className="suggestion_source" dangerouslySetInnerHTML={self.allowHTML(escapedSegment)}/>
                        </li>
                        <li className="b sugg-target">
                            <span className="graysmall-message">{'CTRL' + (index + 1)}</span>
                            <span className="translation" dangerouslySetInnerHTML={self.allowHTML(translation)} />
                            <span className="realData hide" dangerouslySetInnerHTML={self.allowHTML(this.translation)}/>
                        </li>
                        <li className="goto">
                            <a href="#" data-goto={this.involved_id[0]} onClick={()=>SegmentActions.openSegment(this.involved_id[0])}>View</a>
                        </li>
                    </ul>);
        });

        $.each(alternatives.not_editable, function(index1) {
            let diff_obj = UI.execDiff(mainStr, this.translation);
            let translation = UI.transformTextForLockTags(UI.dmp.diff_prettyHtml(diff_obj));

            html.push( <ul className="graysmall notEditable" data-item={(index1 + alternatives.data.editable.length + 1)} key={'not-editable' + index}>
                <li className="sugg-source">
                    <span id={segment_id + '-tm-' + this.id + '-source'} className="suggestion_source" dangerouslySetInnerHTML={self.allowHTML(escapedSegment)}/>
                </li>
                <li className="b sugg-target">
                    <span className="graysmall-message">{'CTRL+' + (index1 + alternatives.data.editable.length + 1)}</span>
                    <span className="translation" dangerouslySetInnerHTML={self.allowHTML(translation)}/>
                    <span className="realData hide" dangerouslySetInnerHTML={self.allowHTML(this.translation)}/>
                </li>
                <li className="goto">
                    <a data-goto={this.involved_id[0]} onClick={()=>SegmentActions.openSegment(this.involved_id[0])}>View</a>
                </li>
            </ul>);
        });
        return html;
    }


    componentDidMount() {

    }

    componentWillUnmount() {

    }

    componentWillMount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        let html;
        if ( this.props.segment.alternatives && Object.size(this.props.segment.alternatives) > 0  ) {
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