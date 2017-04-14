class QualityReportButton extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            is_pass : null,
            score: null, 
            vote: this.props.vote
        };
    }

    getVote() {
        if ( this.state.is_pass != null ) {
            if ( this.state.is_pass ) {
                return 'excellent'; 
            }
            else {
                return 'fail'; 
            }
        }

        else {
            return this.state.vote ;
        }
    }

    render() {
        var label = "QUALITY REPORT"; 

        if ( this.state.score != null ) {
            label = sprintf("QUALITY REPORT (%s)",
                            this.state.score);
        }

        return <a id="quality-report"
        className="draft"
        data-vote={this.getVote()} 
        href={this.props.quality_report_href}
        target="_self">{label}</a> ; 
    }
}

export default QualityReportButton ; 
