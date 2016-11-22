/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabGlossary extends React.Component {

    constructor(props) {
        super(props);
    }

    setGlossary(sid, matches) {
        if ( this.props.id_segment == sid ) {
            var matchesProcessed = this.processContributions(matches);
            this.setState({
                matches: matchesProcessed
            });
        }
    }

    componentDidMount() {
        console.log("Mount SegmentFooterGlossary" + this.props.id_segment);
        SegmentStore.addListener(SegmentConstants.RENDER_GLOSSARY, this.setGlossary);

    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooterGlossary" + this.props.id_segment);
        SegmentStore.removeListener(SegmentConstants.RENDER_GLOSSARY, this.setGlossary);

    }



    componentWillMount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var html = '';
        if ( config.tms_enabled ) {
            html = <div className="gl-search">
                <div className="input search-source" contentEditable="true" ></div>
                <div className="input search-target" contentEditable="true" ></div>
                <a className="set-glossary disabled" href="#"/>
                <div className="comment">
                    <a href="#">(+) Comment</a>
                    <div className="input gl-comment" contentEditable="true" />
                </div>
                <div className="results"></div>
            </div>;
        } else {
            html = <ul className="graysmall message">
                <li>Glossary is not available when the TM feature is disabled</li>
            </ul>;
        }
        return (

            <div key={"container_" + this.props.code} className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                 id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
                <div className="overflow">
                    {html}
                </div>
            </div>
        )
    }
}

export default SegmentFooterTabGlossary;