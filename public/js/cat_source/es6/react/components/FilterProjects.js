class FilterProjects extends React.Component {
    constructor (props) {
        super(props);
    }

    componentDidMount () {
        // $(this.select).material_select(this.onChangeFunction.bind(this));
        $('.ui.dropdown').dropdown();
    }

    onChangeFunction() {
        this.props.filterFunction(this.select.value)
    }

    render () {
        return <div className="row">
                        {/*<div className="col s9">*/}
                            {/*<h4>Project List</h4>*/}
                        {/*</div>*/}

                        <div className="ui floating dropdown labeled icon button">
                            <i className="filter icon"></i>
                            <span className="text">Filter Posts</span>
                            <div className="menu">
                                <div className="ui icon search input">
                                    <input type="text" placeholder="Search tags..."/>
                                </div>
                                <div className="divider"></div>
                                <div className="header">
                                    <i className="tags icon"></i>
                                    Tag Label
                                </div>
                                <div className="scrolling menu">
                                    <div className="item">
                                        <div className="ui red empty circular label"></div>
                                        Active Projects
                                    </div>
                                    <div className="item">
                                        <div className="ui blue empty circular label"></div>
                                        Archived Projects
                                    </div>
                                    <div className="item">
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