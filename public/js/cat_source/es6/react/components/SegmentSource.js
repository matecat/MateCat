/**
 * React Component .

 */
class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.decodeTextSource = this.decodeTextSource.bind(this);
    }

    decodeTextSource() {
        var decoded_source;
        /**if Tag Projection enabled and there are not tags in the segment translation, remove it and add the class that identify
         * tha Tag Projection is enabled
         */
        if (UI.enableTagProjection && (UI.getSegmentStatus(this.props.segment) === 'draft' || UI.getSegmentStatus(this.props.segment) === 'new')
            && !UI.checkXliffTagsInText(this.props.segment.translation) ) {
            // decoded_translation = UI.removeAllTags(segment.translation);
            decoded_source = UI.removeAllTags(this.props.segment.segment);
            this.segment_classes.push('enableTP');
            this.dataAttrTagged = "nottagged";
        } else {
            // decoded_translation = segment.translation;
            decoded_source = this.props.segment.segment;
            this.dataAttrTagged = "tagged";
        }

        decoded_source = UI.decodePlaceholdersToText(
            decoded_source || '',
            true, this.props.segment.sid, 'source');

        this.decoded_text = decoded_source;
    }

    createEscapedSegment() {
        var text = this.props.segment.segment;
        if (!$.parseHTML(text).length) {
            text = UI.stripSpans(text);
        }

        this.escapedSegment = htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        this.escapedSegment = this.escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        this.escapedSegment = this.escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        this.escapedSegment = this.escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
    }

    componentDidMount() {
        console.log("Mount SegmentSource" + this.props.sid);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentSource" + this.props.sid);
    }

    componentWillMount() {
        this.decodeTextSource();
        this.createEscapedSegment();
    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return (
            <div className={"source item"}
                 tabindex={0}
                 id={"segment-" + this.props.segment.sid +"-source"}
                 data-original={this.escapedSegment}
                 dangerouslySetInnerHTML={ this.allowHTML(this.decoded_text) }/>
        )
    }
}

export default SegmentSource;
