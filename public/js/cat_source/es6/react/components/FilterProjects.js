class FilterProjects extends React.Component {
    constructor (props) {
        super(props);

        this.onChangeFunction = this.onChangeFunction.bind(this);
    }

    componentDidMount () {
        let self = this;
        this.dropdown = $('.ui.dropdown.projects-state');
        this.dropdown.dropdown({
            onChange: function() {
                self.onChangeFunction();
            }
        });
        this.currentFilter = 'active';
        this.dropdown.dropdown('set selected', 'active');
    }

    onChangeFunction() {
        if (this.currentFilter !== this.dropdown.dropdown('get value')) {
            this.props.filterFunction(this.dropdown.dropdown('get value'));
            this.currentFilter = this.dropdown.dropdown('get value');
        }
    }

    render () {
        return <div className="projects-state ui dropdown icon button">
                    <i className="filter icon"/>
                    <div className="menu">
                        <div className="scrolling menu">
                            <div className="item" data-value="active">
                                <div className="ui red empty circular label"></div>
                                Active Projects
                            </div>
                            <div className="item" data-value="archived">
                                <div className="ui blue empty circular label"></div>
                                Archived Projects
                            </div>
                            <div className="item" data-value="cancelled">
                                <div className="ui black empty circular label"></div>
                                Cancelled Projects
                            </div>
                        </div>
                    </div>
                </div>;
    }
}

export default FilterProjects ;