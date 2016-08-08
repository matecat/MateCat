/**
 * React Component .

 */
var React = require('react');
var SegmentStore = require('../../stores/SegmentStore');
var SegmentConstants = require('../../constants/SegmentConstants');


class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            source : this.decodeTextSource(this.props.segment, this.props.segment.segment)

        };
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.decodeTextSource = this.decodeTextSource.bind(this);
        this.replaceSource = this.replaceSource.bind(this);
    }

    replaceSource(sid, source) {
        if (this.props.segment.sid == sid) {
            this.setState({
                source: this.decodeTextSource(this.props.segment, source)
            });
        }
    }

    decodeTextSource(segment, source) {
        return this.props.decodeTextFn(segment, source);
    }

    createEscapedSegment() {
        var text = this.props.segment.segment;
        if (!$.parseHTML(text).length) {
            text = text.replace(/<span(.*?)>/gi, '').replace(/<\/span>/gi, '');
        }

        var escapedSegment = htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        escapedSegment = escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
        return escapedSegment;
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.REPLACE_SOURCE, this.replaceSource);

    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_SOURCE, this.replaceSource);
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var escapedSegment = this.createEscapedSegment();
        return (
            <div className={"source item"}
                 tabIndex={0}
                 id={"segment-" + this.props.segment.sid +"-source"}
                 data-original={escapedSegment}
                 dangerouslySetInnerHTML={ this.allowHTML(this.state.source) }/>
        )
    }
}

export default SegmentSource;
