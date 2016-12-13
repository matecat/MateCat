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

    openSettings() {
        ManageActions.openJobSettings(this.props.job.toJS(), this.props.projectName);
    }

    render () {
        var translateUrl = this.getTranslateUrl();
        var analysisUrl = this.getAnalysisUrl();
        var splitUrl = this.getSplitUrl();
        return <div className="card job z-depth-1">
            <div className="head-job">
                <div className="row">
                    <div className="col s2">
                        <div className="job-id">
                            <div id="id-job"><span>ID:</span>{this.props.job.get('id') + '-' + this.props.index }</div>
                        </div>
                    </div>
                    <div className="col s10">
                        <ul className="job-activity-icon right">

                            <li>
                                <a className="btn-floating btn-flat waves-effect waves-dark z-depth-0"
                                    onClick={this.openSettings.bind(this)}>
                                    <i className="material-icons">settings</i>
                                </a>
                            </li>
                            <li>
                                <a className='dropdown-button btn-floating btn-flat waves-effect waves-dark z-depth-0'
                                   data-activates='dropdown2'>
                                    <i className="material-icons">more_vert</i>
                                </a>
                                <ul id='dropdown2' className='dropdown-content'>
                                    <li><a href="#!">one</a></li>
                                    <li><a href="#!">two</a></li>
                                    <li><a href="#!">three</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div className="body-job">
                <div className="row">
                    <div className="col s7">
                        <div className="row">
                            <div className="col s3">
                                <div className="combo-language">
                                    <span id="source">{this.props.job.get('source')}</span> <i className="material-icons">play_arrow</i>
                                    <span id="target">{this.props.job.get('target')}</span>
                                </div>
                            </div>
                            <div className="col s7">
                                <div className="progress-bar">
                                    <div className="progress">
                                        <div className="determinate" style={{width: '70%'}}></div>
                                    </div>
                                </div>
                            </div>
                            <div className="col s2">
                                <div className="payable-words">
                                    <a href="#!"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="col s5">
                        <div className="row">
                            <div className="col s10">
                                <div className="button-list split-outsource right">
                                    <a className="btn waves-effect white waves-dark" target="_blank" href={splitUrl}>split
                                        <i className="large material-icons rotate">swap_horiz</i>
                                    </a>
                                    <a className="btn waves-effect waves-light green" target="_blank" href={analysisUrl}>outsource</a>
                                </div>
                            </div>
                            <div className="col s2 right">
                                <div className="button-list open right">
                                    <a className="btn waves-effect waves-light" target="_blank" href={translateUrl}>open</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        // <div className="row">
        //         <div className="col s4">
        //             <h6>{'JOB ID: ' + this.props.job.get('id') + '-' + this.props.index }</h6>
        //         <a className="collection-item">
        //         {this.props.job.get('source') + '->' + this.props.job.get('target') + ' ' + this.props.job.get('stats').get('TOTAL_FORMATTED') + ' Payable Words'}}</a>
        //     </div>
        //     <div className="col s3">
        //         <a target="_blank" href={analysisUrl}> Outsource </a>
        //     </div>
        //     <div className="col s3">
        //         <a target="_blank" href={translateUrl}> Open</a>
        //     </div>
        //     <div className="col s3">
        //         <a target="_blank" href={splitUrl}> split</a>
            {/*</div>*/}
        {/*</div>;*/}
    }
}
export default JobContainer ;
