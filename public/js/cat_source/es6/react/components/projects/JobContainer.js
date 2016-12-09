class JobContainer extends React.Component {

    constructor (props) {
        super(props);
        this.getTranslateUrl = this.getTranslateUrl.bind(this);
        this.getAnalysisUrl = this.getAnalysisUrl.bind(this);
    }

    componentDidMount () {

    }

    shouldComponentUpdate(nextProps, nextState){
        return (nextProps.job !== this.props.job )
    }

    getTranslateUrl() {
        var use_prefix = ( this.props.jobsLenght > 1 );
        var chunk_id = this.props.job.get('id') + ( ( use_prefix ) ? '-' + this.props.index : '' ) ;
        return '/translate/'+this.props.projectName+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+ this.props.job.get('password')  ;
    }

    getAnalysisUrl() {
        return '/analyze/'+ this.props.projectName +'/'+this.props.projectId+'-' + this.props.projectPassword + '?open=analysis&jobid=' + this.props.job.get('id');
    }
    getSplitUrl() {
        return '/analyze/'+ this.props.projectName +'/'+this.props.projectId+'-' + this.props.projectPassword + '?open=split&jobid=' + this.props.job.get('id');
    }

    render () {
        var translateUrl = this.getTranslateUrl();
        var analysisUrl = this.getAnalysisUrl();
        var splitUrl = this.getSplitUrl();
        return <div className="row">
                <div className="col s4">
                    <h6>{'JOB ID: ' + this.props.job.get('id') + '-' + this.props.index }</h6>
                <a className="collection-item">
                {this.props.job.get('source') + '->' + this.props.job.get('target') + ' ' + this.props.job.get('stats').get('TOTAL_FORMATTED') + ' Payable Words'}}</a>
            </div>
            <div className="col s3">
                <a target="_blank" href={analysisUrl}> Outsource </a>
            </div>
            <div className="col s3">
                <a target="_blank" href={translateUrl}> Open</a>
            </div>
            <div className="col s3">
                <a target="_blank" href={splitUrl}> split</a>
            </div>
        </div>;
    }
}
export default JobContainer ;