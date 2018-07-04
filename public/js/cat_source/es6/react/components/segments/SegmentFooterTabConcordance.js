/**
 * React Component .

 */
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');

class SegmentFooterTabConcordance extends React.Component {

    constructor(props) {
        super(props);
        let extended = false;
        if (Cookies.get('segment_footer_extendend_concordance')) {
            extended = Cookies.get('segment_footer_extendend_concordance') === 'true';
        }

        this.state = {
            noResults: false,
            numDisplayContributionMatches: 3,
            results: [],
            loading: false,
            source: '',
            target: '',
            extended: extended
        }
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.FIND_CONCORDANCE, this.findConcordance);
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.FIND_CONCORDANCE, this.findConcordance.bind(this));
    }

    allowHTML(string) {
        return {__html: string};
    }

    findConcordance(sid, data) {
        if (this.props.id_segment == sid) {
            if (data.inTarget === 1) {
                this.setState({
                    source: '',
                    target: data.text,
                    results: []
                });
            } else {
                this.setState({
                    source: data.text,
                    target: '',
                    results: []
                });
            }
            this.searchSubmit();
        }
    }

    sourceChange(event) {
        this.setState({
            source: event.target.value,
            target: '',
            results: []
        });

    }

    targetChange(event) {
        this.setState({
            source: '',
            target: event.target.value,
            results: []
        });

    }

    getConcordance(query, type) {
        //type 0 = source, 1 = target
        let self = this;
        API.SEGMENT.getConcordance(query, type)
            .done(function (d) {
                self.renderConcordances(d, type);
            }).fail(function () {
            UI.failedConnection(this, 'getConcordance');
        });
        this.setState({
            loading: true,
            results: []
        });
    }

    allowHTML(string) {
        return {__html: string};
    }

    renderConcordances(d, in_target) {
        let self = this;
        let segment = UI.currentSegment;
        let segment_id = UI.currentSegmentId;
        let array = [];

        if (d.data.matches.length) {
            _.each(d.data.matches, function (item, index) {
                if ((item.segment === '') || (item.translation === ''))
                    return;
                let prime = (index < self.state.numDisplayContributionMatches) ? ' prime' : '';

                let cb = item.created_by;

                let leftTxt = (in_target) ? item.translation : item.segment;
                leftTxt = UI.decodePlaceholdersToText(leftTxt);
                leftTxt = leftTxt.replace(/\#\{/gi, "<mark>");
                leftTxt = leftTxt.replace(/\}\#/gi, "</mark>");

                let rightTxt = (in_target) ? item.segment : item.translation;
                rightTxt = UI.decodePlaceholdersToText(rightTxt);
                rightTxt = rightTxt.replace(/\#\{/gi, "<mark>");
                rightTxt = rightTxt.replace(/\}\#/gi, "</mark>");

                let element = <ul key={index} className={["graysmall",
                    prime].join(' ')} data-item={index + 1} data-id={item.id}>
                    <li className={"sugg-source"}>
                        <span id={segment_id + '-tm-' + item.id + '-source'} className={"suggestion_source"}
                              dangerouslySetInnerHTML={self.allowHTML(leftTxt)}/>
                    </li>
                    <li className={"b sugg-target"}>
                        <span id={segment_id + "-tm-" + item.id + "-translation"} className={"translation"}
                              dangerouslySetInnerHTML={self.allowHTML(rightTxt)}/>
                    </li>
                    <ul className={"graysmall-details"}>
                        <li>{item.last_update_date}</li>
                        <li className={"graydesc"}>Source: <span className={"bold"}>{cb}</span></li>
                    </ul>
                </ul>;
                array.push(element);
            });

            this.setState({
                results: array,
                noResults: false,
                loading: false
            })


        } else {
            this.setState({
                noResults: true,
                results: [],
                loading: false
            })
        }
    }


    searchSubmit(event) {
        (event) ? event.preventDefault() : '';
        if (this.state.source.length > 0) {
            this.getConcordance(this.state.source, 0);

        } else if (this.state.target.length > 0) {
            this.getConcordance(this.state.target, 1);
        }
    }

    toggleExtendend() {
        if (this.state.extended) {
            Cookies.set('segment_footer_extendend_concordance', false, {expires: 3650});
        } else {
            Cookies.set('segment_footer_extendend_concordance', true, {expires: 3650});
        }
        this.setState({
            extended: !this.state.extended
        });
    }


    render() {
        let html = '',
            results = '',
            loadingClass = '',
            extended = '',
            haveResults = '',
            isExtendedClass = this.state.extended ? 'extended' : '';
        extended = <a className={"more"}
                      onClick={this.toggleExtendend.bind(this)}>{this.state.extended ? 'Fewer' : 'More'}</a>;

        if (this.state.results.length > 0) {
            haveResults = 'have-results'
        }
        if (this.state.loading) {
            loadingClass = 'loading';
        }
        if (config.tms_enabled) {
            html = <div className={"cc-search " + loadingClass}>
                <form onSubmit={this.searchSubmit.bind(this)}>
                    <div className="input-group">
                        <input type="text" className="input search-source" onChange={this.sourceChange.bind(this)}
                               value={this.state.source}/>
                    </div>
                    <div className="input-group">
                        <input type="text" className="input search-target" onChange={this.targetChange.bind(this)}
                               value={this.state.target}/>
                    </div>
                    <input type="submit" value="" style={{display: "none"}}/>
                </form>
            </div>;
        } else {
            html = <ul className={"graysmall message prime"}>
                <li>TM Search is not available when the TM feature is disabled</li>
            </ul>;
        }

        if (this.state.results.length > 0 && !this.state.noResults) {
            results = this.state.results;
        }
        if (this.state.noResults) {
            results = <ul className={"graysmall message prime"}>
                <li>Can't find any matches. Check the language combination.</li>
            </ul>
        }

        return (

            <div key={"container_" + this.props.code}
                 className={"tab sub-editor " + this.props.active_class + " " + this.props.tab_class + " " + isExtendedClass + " " + haveResults}
                 id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
                <div className="overflow">
                    {html}
                    <div className="results">
                        {results}
                    </div>
                </div>
                <br className="clear"/>
                {this.state.results.length > 0 ? extended : null}
            </div>
        )
    }
}

export default SegmentFooterTabConcordance;