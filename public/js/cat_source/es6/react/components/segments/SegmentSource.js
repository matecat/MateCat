/**
 * React Component .

 */
class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            source : this.props.segment.segment

        };
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.decodeTextSource = this.decodeTextSource.bind(this);
    }

    decodeTextSource() {
        var decoded_source = this.props.segment.segment;
        /**if Tag Projection enabled and there are not tags in the segment translation, remove it and add the class that identify
         * tha Tag Projection is enabled
         */
        if (UI.enableTagProjection && (UI.getSegmentStatus(this.props.segment) === 'draft' || UI.getSegmentStatus(this.props.segment) === 'new')
            && !UI.checkXliffTagsInText(this.props.segment.translation) ) {
            decoded_source = UI.removeAllTags(this.props.segment.segment);
        }

        decoded_source = UI.decodePlaceholdersToText(
            decoded_source || '',
            true, this.props.segment.sid, 'source');

        return decoded_source;
    }

    createEscapedSegment() {
        var text = this.props.segment.segment;
        if (!$.parseHTML(text).length) {
            text = UI.stripSpans(text);
        }

        var escapedSegment = htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        escapedSegment = escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
        return escapedSegment;
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
        var decoded_text = this.decodeTextSource();
        var escapedSegment = this.createEscapedSegment();
        return (
            <div className={"source item"}
                 tabIndex={0}
                 id={"segment-" + this.props.segment.sid +"-source"}
                 data-original={escapedSegment}
                 dangerouslySetInnerHTML={ this.allowHTML(decoded_text) }/>
        )
    }
}

export default SegmentSource;
