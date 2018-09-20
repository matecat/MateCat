
class FilterSegments extends React.Component {

    constructor(props) {
        super(props);

        this.state = this.defaultState();
        this.lqaNestedCategories = this.props.categories;
        this.severities = this.getSeverities();
    }

    defaultState() {
        return {
            filter: {
                status: "",
                issue_category: null,
                severity: null
            }
        }
    }

    getSeverities() {
        let severities = [];
        let severitiesNames = [];
        this.lqaNestedCategories.categories.forEach((cat)=>{
            if (cat.subcategories.length === 0) {
                cat.severities.forEach((sev)=>{
                    if (severitiesNames.indexOf(sev.label) === -1 ) {
                        severities.push(sev);
                        severitiesNames.push(sev.label);
                    }
                });
            } else {
                cat.subcategories.forEach((subCat)=>{
                    subCat.severities.forEach((sev)=>{
                        if (severitiesNames.indexOf(sev.label) === -1 ) {
                            severities.push(sev);
                            severitiesNames.push(sev.label);
                        }
                    });
                });
            }
        });
        return severities;
    }

    filterSelectChanged(type, value) {
        let filter = jQuery.extend({}, this.state.filter);
        filter[type] = value;
        this.setState({
            filter: filter
        });

        this.props.applyFilter(this.state.filter);

    }

    resetStatusFilter() {
        let filter = jQuery.extend({}, this.state.filter);
        filter.status = "";
        $(this.statusDropdown).dropdown('restore defaults');
        this.setState({
            filter: filter
        });
        setTimeout(()=> {
            this.props.applyFilter(this.state.filter)
        });
    }
    resetCategoryFilter() {
        let filter = jQuery.extend({}, this.state.filter);
        filter.issue_category = null;
        $(this.categoryDropdown).dropdown('restore defaults');
        this.setState({
            filter: filter
        });
        setTimeout(()=> {
            this.props.applyFilter(this.state.filter)
        });
    }

    resetSeverityFilter() {
        let filter = jQuery.extend({}, this.state.filter);
        filter.severity = null;
        $(this.severityDropdown).dropdown('restore defaults');
        this.setState({
            filter: filter
        });
        setTimeout(()=> {
            this.props.applyFilter(this.state.filter)
        });
    }

    initDropDown() {
        let self = this;
        $(this.statusDropdown).dropdown({
            onChange: function(value, text, $selectedItem) {
                if (value && value !== "") {
                    self.filterSelectChanged('status', value);
                }
            }
        });
        $(this.categoryDropdown).dropdown({
            onChange: (value, text, $selectedItem) => {
                if (value && value !== "") {
                    self.filterSelectChanged('issue_category', value);
                }
            }
        });
        $(this.severityDropdown).dropdown({
            onChange: (value, text, $selectedItem) => {
                if (value && value !== "") {
                    self.filterSelectChanged('severity', value);
                }
            }
        });
        this.dropdownInitialized = true;
    }

    componentDidMount() {
        setTimeout(this.initDropDown.bind(this), 100);
    }

    componentDidUpdate() {
        if (!this.dropdownInitialized) {
            this.initDropDown();
        }
    }

    render () {
        let optionsStatus = config.searchable_statuses.map(function (item, index) {
            return <div className="item" key={index} data-value={item.value}>
                <div  className={"ui "+ item.label.toLowerCase() +"-color empty circular label"} />
                {item.label}
            </div>;
        });
        let optionsCategory = this.lqaNestedCategories.categories.map((item, index) => {
            return <div className="item" key={index} data-value={item.id}>
                {item.label}
            </div>;
        });
        let optionsSeverities = this.severities.map((item, index) => {
            return <div className="item" key={index} data-value={item.label}>
                {item.label}
            </div>;
        });
        let statusFilterClass = (this.state.filter.status && this.state.filter.status !== "") ? "filtered" : "not-filtered";
        let categoryFilterClass = (this.state.filter.issue_category && this.state.filter.issue_category !== "") ? "filtered" : "not-filtered";
        let severityFilterClass = (this.state.filter.severity && this.state.filter.severity !== "") ? "filtered" : "not-filtered";
        return <div className="qr-filter-list">Filters by
            <div className="filter-dropdown left-10">
                <div className={"filter-status " + statusFilterClass}>
                    <div className="ui top left pointing dropdown basic tiny button right-0" ref={(dropdown)=>this.statusDropdown=dropdown}>
                        <div className="text">
                            <div>Segment status</div>
                        </div>
                        <div className="ui cancel label"><i className="icon-cancel3" onClick={this.resetStatusFilter.bind(this)}/></div>
                        <div className="menu">
                            {optionsStatus}
                        </div>
                    </div>
                </div>
                <div className={"filter-category " + categoryFilterClass}>
                    <div className="ui top left pointing dropdown basic tiny button right-0" ref={(dropdown)=>this.categoryDropdown=dropdown}>
                        <div className="text">
                            <div>Issue category</div>
                        </div>
                        <div className="ui cancel label"><i className="icon-cancel3" onClick={this.resetCategoryFilter.bind(this)}/></div>
                        <div className="menu">
                            {optionsCategory}
                        </div>
                    </div>
                </div>
                <div className={"filter-category " + severityFilterClass}>
                    <div className="ui top left pointing dropdown basic tiny button right-0" ref={(dropdown)=>this.severityDropdown=dropdown}>
                        <div className="text">
                            <div>Issue severity</div>
                        </div>
                        <div className="ui cancel label"><i className="icon-cancel3" onClick={this.resetSeverityFilter.bind(this)}/></div>
                        <div className="menu">
                            {optionsSeverities}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    }
}

export default FilterSegments ;