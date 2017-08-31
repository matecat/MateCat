

class QualityReportVersions extends React.Component {
    constructor (props) {
        super(props);
    }

    getVersionsHtml() {
        let self = this;
        let versions = this.props.versions.map(function (item) {
            let date = new Date(item.created_at);
            let classPassed = (item['quality-report'].chunk.review.is_pass) ? 'version-green' : 'version-red';
            return <div className="item report-item" data-value={item.version_number} key={item.version_number}
                    onClick={self.openVersion.bind(this, item)}>
                <div className={classPassed}/>
                <div className="report-text">Version {item.version_number} ({item['quality-report'].chunk.review.score})</div>
                <span className="report-date">{date.toString()}</span>
            </div>
        });

        let currentIsPass = (config.is_pass == 1) ? 'version-green' : 'version-red';
        let current = <div className="item report-item" data-value="0" key="0"
                           onClick={self.openCurrentVersion.bind(this)}>
            <div className={currentIsPass}/>
            <div className="report-text">Current version ({config.score})</div>
            <span className="report-date"></span>
        </div>;
        versions = versions.reverse();
        versions.unshift(current);
        return versions;
    }

    openVersion(version) {
        let url = '/plugins/review_improved/quality_report/'+config.id_job+'/'+config.password+'/versions/'+version.version_number;
        window.location = url;
    }

    openCurrentVersion(version) {
        let url = '/plugins/review_improved/quality_report/'+config.id_job+'/'+config.password;
        window.location = url;
    }

    componentDidMount () {
        if (config.version_number) {
            $(this.dropdown).dropdown('set selected', config.version_number);
        } else {
            $(this.dropdown).dropdown('set selected', '0');
        }
    }

    componentWillUnmount() {}

    componentDidUpdate() {}

    render () {
        let versions = this.getVersionsHtml()
        return <div className="ui fluid selection dropdown " id="report-versions-select"
                    ref={(dropdown) => this.dropdown = dropdown}>
            <input type="hidden" name="versions"/>
                <i className="dropdown icon"/>
                <div className="default text">Select version</div>
                <div className="menu">
                    {versions}
                </div>
        </div>;
    }
}

export default QualityReportVersions ;

