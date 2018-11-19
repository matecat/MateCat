
class ProductionSummary extends React.Component {

    getTimeToEdit() {
        let str_pad_left = function(string,pad,length) {
            return (new Array(length+1).join(pad)+string).slice(-length);
        }
        let time = parseInt(this.props.jobInfo.get("total_time_to_edit")/1000);
        let hours = Math.floor(time / 3600);
        let minutes = Math.floor( time % 3600 / 60);
        let seconds = Math.floor(time % 3600 % 60);
        return str_pad_left(hours,'0',2)+':'+str_pad_left(minutes,'0',2)+':'+str_pad_left(seconds,'0',2);
    }

    componentDidUpdate() {
        if (this.props.jobInfo) {
            $(this.progressBar).find('.translate-tooltip').popup();
        }
    }

    componentDidMount() {
        $(this.tooltip).popup({
            position: 'bottom right',
            className   : {
                popup       : 'ui popup qr-score-popup'
            },
            hoverable: true
            // /*on : 'click'
        });
        // $(this.tooltipRev).popup({
        //     position: 'bottom right'
        // });
    }

    render () {
        let tooltipText = '<div style="color:gray">MateCat calculates the score as follows: </br></br>' +
                '<code>(Tot. error points * 1000) / reviewed words</code></br>' +
                'Reviewed words =  raw words - unmodified ICE matches</br></br>'+
            'The score is compared to a max. amount of tolerated error points.' +
            '<a style="text-decoration: underline" href="https://www.matecat.com/support/revising-projects/quality-report-matecat/" target="_blank">Learn more</a>' +
            '</div>';
        let tooltipText2 = '<div style="color:gray">Raw words that have actually been revised (ICE MATCHES NOT INCLUDED)</div>';
        let score = parseFloat(this.props.jobInfo.get('quality_summary').get('score'));
        let limit = (this.props.jobInfo.get('quality_summary').get('passfail') !== "") ? parseInt(JSON.parse(this.props.jobInfo.get('quality_summary').get('passfail')).options.limit):0;
        let qualityOverall = this.props.jobInfo.get('quality_summary').get('quality_overall');
        let jobPassed = qualityOverall !== null ? (qualityOverall !== "fail") : null;
        let jobPassedClass = (jobPassed === null) ? "" : ((jobPassed)? "qr-pass" : "qr-fail");
        let translator = this.props.jobInfo.get('translator') ? this.props.jobInfo.get('translator').get('email'): "Not assigned";
        let stats = this.props.jobInfo.get('stats');
        return <div className="qr-production shadow-1">
            <div className="qr-effort job-id">ID: {this.props.jobInfo.get('id')}</div>
            <div className="qr-effort source-to-target">
                <div className="qr-source"><b>{this.props.jobInfo.get('sourceTxt')}</b></div>
                <div className="qr-to">
                    <i className="icon-chevron-right icon" />
                </div>
                <div className="qr-target"><b>{this.props.jobInfo.get('targetTxt')}</b></div>
            </div>
            <div className="qr-effort progress-percent" ref={(bar)=>this.progressBar=bar}>
                <div className="progress-bar">
                    <div className="progr">
                        <div className="meter">
                            <a className="warning-bar translate-tooltip" data-variation="tiny"
                               data-html={"Rejected " + parseInt(stats.get('REJECTED_PERC'))+"%"}
                               style={{width: parseInt(stats.get('REJECTED_PERC'))+"%"}}

                            />

                            <a className="approved-bar translate-tooltip" data-variation="tiny"
                               data-html={"Approved " + parseInt(stats.get('APPROVED_PERC'))+"%"}
                               style={{width: parseInt(stats.get('APPROVED_PERC'))+"%"}}/>

                            <a className="translated-bar translate-tooltip" data-variation="tiny"
                               data-html={"Translated " + parseInt(stats.get('TRANSLATED_PERC'))+"%"}
                               style={{width: parseInt(stats.get('TRANSLATED_PERC'))+"%"}}/>

                            <a className="draft-bar translate-tooltip" data-variation="tiny"
                               data-html={"Draft " + parseInt(stats.get('DRAFT_PERC'))+"%"}
                               style={{width: parseInt(stats.get('DRAFT_PERC'))+"%"}}/>

                        </div>
                    </div>
                </div>
                <div className="percent">{parseInt(stats.get('PROGRESS_PERC_FORMATTED'))}%</div>
            </div>
            <div className="qr-effort">
                <div className="qr-label">Words</div>
                <div className="qr-info"><b>{stats.get('TOTAL_FORMATTED')}</b></div>
            </div>

            {/*{config.project_type !== "old" ? (*/}
            {/*<div className="qr-effort qr-review-words">*/}
                {/*<div className="qr-label" data-html={tooltipText2} ref={(tooltip) => this.tooltipRev = tooltip}>Reviewed <i className="icon-info icon" /></div>*/}
                {/*<div className="qr-info"><b>{this.props.jobInfo.get('quality_summary').get('total_reviews_words_count')}</b></div>*/}
            {/*</div>*/}
            {/*) :null}*/}

            <div className="qr-effort translator">
                <div className="qr-label">Translator</div>
                <div className="qr-info" title={translator}><b>{translator}</b></div>
            </div>
            {/*<div className="qr-effort reviser">*/}
                {/*<div className="qr-label">Reviser</div>*/}
                {/*<div className="qr-info"><b></b></div>*/}
            {/*</div>*/}
            <div className="qr-effort time-edit">
                <div className="qr-label">Time to edit</div>
                <div className="qr-info"><b>{this.getTimeToEdit()}</b> </div>
            </div>
            <div className="qr-effort pee">
                <div className="qr-label">PEE</div>
                <div className="qr-info"><b>{(this.props.jobInfo.get('pee')) ? parseInt(this.props.jobInfo.get('pee')):0}%</b> </div>
            </div>
            {config.project_type !== "old" ? (
            <div className={"qr-effort qr-score " + jobPassedClass}>
                {/*<div className="qr-label">Based on Reviewed Words</div>*/}
                <div className="qr-info">
                    <div className="qr-tolerated-score"><b>{score}</b></div>
                    { jobPassed === null ? (
                        <div>
                            <div className="qr-label">
                                Quality score
                            </div>
                            <div className="qr-pass-score"></div>
                        </div>
                    ) : (jobPassed ? (
                        <div>
                            <div className="qr-label">
                                Quality score
                            </div>
                            <div className="qr-pass-score"><b>Pass</b></div>
                        </div>
                    ) : (
                        <div>
                            <div className="qr-label">
                                Quality score
                            </div>
                            <div className="qr-pass-score"><b>Fail</b></div>
                        </div>
                    ))}
                </div>
                <div className="qr-label" data-html={tooltipText} ref={(tooltip) => this.tooltip = tooltip}>Threshold {limit} <i className="icon-info icon" /></div>
            </div>
            ) :null}
        </div>

    }
}

export default ProductionSummary ;