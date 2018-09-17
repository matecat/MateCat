
class ProductionSummary extends React.Component {

    getTimeToEdit() {
        let str_pad_left = function(string,pad,length) {
            return (new Array(length+1).join(pad)+string).slice(-length);
        }
        let time = parseInt(this.props.jobInfo.get("total_time_to_edit"));
        let hours = Math.floor(time / 3600);
        let minutes = Math.floor( time / 60);
        let seconds = time - minutes * 60;
        return str_pad_left(hours,'0',3)+':'+str_pad_left(minutes,'0',2)+':'+str_pad_left(seconds,'0',2);
    }

    componentDidUpdate() {
        if (this.props.jobInfo) {
            $(this.progressBar).find('.translate-tooltip').popup();
        }
    }

    render () {
        if (this.props.jobInfo) {
            let stats = this.props.jobInfo.get('stats');
            return <div className="qr-production shadow-1">
                <div className="job-id">ID: {this.props.jobInfo.get('id')}</div>
                <div className="source-to-target">
                    <div className="qr-source"><b>{this.props.jobInfo.get('sourceTxt')}</b></div>
                    <div className="qr-to">
                        <i className="icon-chevron-right icon" />
                    </div>
                    <div className="qr-target"><b>{this.props.jobInfo.get('targetTxt')}</b></div>
                </div>
                <div className="progress-percent" ref={(bar)=>this.progressBar=bar}>
                    <div className="progress-bar">
                        <div className="progr">
                            <div className="meter">
                                <a className="warning-bar translate-tooltip" data-variation="tiny"
                                   data-html={"Rejected " + parseInt(stats.get('REJECTED_PERC'))+"%"}
                                   style={{width: parseInt(stats.get('REJECTED_PERC'))}}

                                />

                                <a className="approved-bar translate-tooltip" data-variation="tiny"
                                   data-html={"Approved " + parseInt(stats.get('APPROVED_PERC'))+"%"}
                                   style={{width: parseInt(stats.get('APPROVED_PERC'))}}/>

                                <a className="translated-bar translate-tooltip" data-variation="tiny"
                                   data-html={"Translated " + parseInt(stats.get('TRANSLATED_PERC'))+"%"}
                                   style={{width: parseInt(stats.get('TRANSLATED_PERC'))}}/>

                                <a className="draft-bar translate-tooltip" data-variation="tiny"
                                   data-html={"Draft " + parseInt(stats.get('DRAFT_PERC'))+"%"}
                                   style={{width: parseInt(stats.get('DRAFT_PERC'))}}/>

                            </div>
                        </div>
                    </div>
                    <div className="percent">100%</div>
                </div>
                <div className="qr-effort">
                    <div className="qr-label">Words</div>
                    <div className="qr-info"><b>{this.props.jobInfo.get('total_raw_wc')}</b></div>
                </div>
                <div className="qr-effort translator">
                    <div className="qr-label">Translator</div>
                    <div className="qr-info"><b></b></div>
                </div>
                <div className="qr-effort reviser">
                    <div className="qr-label">Reviser</div>
                    <div className="qr-info"><b></b></div>
                </div>
                <div className="qr-effort pee">
                    <div className="qr-label">PEE</div>
                    <div className="qr-info qr-good"><b>{parseInt(this.props.jobInfo.get('pee'))}%</b> </div>
                </div>
                <div className="qr-effort time-edit">
                    <div className="qr-label">Time Edit</div>
                    <div className="qr-info qr-bad"><b>{this.getTimeToEdit()}</b> </div>
                </div>
            </div>
        } else {
            return "";
        }
    }
}

export default ProductionSummary ;