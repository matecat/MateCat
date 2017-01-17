var FilterProjects = require("./FilterProjects").default;
var SearchInput = require("./SearchInput").default;

class SubHeader extends React.Component {
    constructor (props) {
        super(props);
    }

    render () {

        return (
            <section className="sub-head z-depth-1">
                <div className="container">
                    <div className="row">
                        <div className="col m4">
                            <nav>
                                <div className="nav-wrapper">
                                    <SearchInput
                                        closeSearchCallback={this.props.closeSearchCallback}
                                        onChange={this.props.searchFn}/>
                                </div>
                            </nav>
                        </div>
                        <div className="assigned-list">
                            <div className="col top-12">
                                <p>All members</p>
                            </div>
                            <div className="col">
                                <div className="switch">
                                    <label>
                                        <input type="checkbox" />
                                        <span className="lever"></span>
                                    </label>
                                </div>
                            </div>
                            <div className="list-team">
                                <div className="input-field col m2">
                                    <select className="list-member-team">
                                      <option value="1" defaultValue>Assigned to me</option>
                                      <option value="2" className="left circle">Alessandro</option>
                                      <option value="3" className="left circle">Annalisa</option>
                                      <option value="4" className="left circle">Claudia</option>
                                    </select>
                                    
                                </div>
                            </div>
                        </div>
                        <div className="col m2 right">
                            <FilterProjects
                                filterFunction={this.props.filterFunction}/>
                        </div>
                    </div>
                </div>
            </section>
        );
    }
}

export default SubHeader ;