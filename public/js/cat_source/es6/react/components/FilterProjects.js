class FilterProjects extends React.Component {
    constructor (props) {
        super(props);

        this.onChangeFunction = this.onChangeFunction.bind(this);
    }

    componentDidMount () {
        var self = this;
        this.dropdown = $('.ui.dropdown.projects-state');
        this.dropdown.dropdown({
            onChange: function() {
                self.onChangeFunction();
            }
        });
        this.dropdown.dropdown('set selected', 'active');
    }

    onChangeFunction() {
        this.props.filterFunction(this.dropdown.dropdown('get value'));
    }

    render () {
        return <div className="row">
                        <div className="ui floating projects-state fluid dropdown labeled icon button">
                            <i className="filter icon"></i>
                            <span className="text">Filter Projects</span>
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
                        </div>
                    </div>;



    }
}

export default FilterProjects ;