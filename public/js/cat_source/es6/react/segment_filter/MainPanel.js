class MainPanel extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            searchSettingsOpen : false, 
            selectedStatus : '' 
        }
    }

    toggleSettings() {
        this.setState({
            searchSettingsOpen : !this.state.searchSettingsOpen
        }); 
    }

    submitClick(e) {
        e.preventDefault() ;

        SegmentFilter.filterSubmit({
            status : this.state.selectedStatus
        });
    }

    filterSelectChanged(e) {
        this.setState({
            selectedStatus : e.target.value
        }); 
    }

    render() {

        var searchSettingsClass = classnames({
            hide                    : !this.state.searchSettingsOpen,
            'search-settings-panel' : true
        }); 

        return <div className="advanced-filter-searchbox searchbox">
            <form>
                <div className="block">
                    <label htmlFor="search-projectname">segment status</label>
                    <select 
                        onChange={this.filterSelectChanged.bind(this)}
                        value={this.state.selectedStatus} className="search-select">
                        <option value="">All</option>
                        <option value="translated">Translated</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div className="block">
                    <label htmlFor="select-source">Data sample</label>
                    <input type="checkbox" />
                </div>

                <div className="block">
                    <a className="search-settings" onClick={this.toggleSettings.bind(this)}>Settings</a>
                    <div className={searchSettingsClass}>
                        <div className="slider">Slider here</div>
                        <h4>Sample criteria</h4>

                        <div className="block">
                            <input type="radio" /><label>Edit distance</label>
                        </div>

                        <div className="block">
                            <input type="radio" /><label>Regular interval</label>
                            <select className="advanced-regular-interval">
                                <option>5</option>
                                <option>10</option>
                                <option>15</option>
                            </select>
                        </div>
                        <div className="block">
                            <input type="radio" /><label>Segment lenght</label>
                        </div>
                    </div>
                </div>

                <div className="block right">
                    <input id="clear-filter" type="button" className="btn" value="CLEAR" />
                    <input onClick={this.submitClick.bind(this)} id="exec-filter" type="submit" className="btn" value="FILTER" />
                </div>
            </form>

        </div>; 
    }
}

export default MainPanel ;
