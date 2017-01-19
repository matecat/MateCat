var FilterProjects = require("./FilterProjects").default;
var SearchInput = require("./SearchInput").default;

class SubHeader extends React.Component {
    constructor (props) {
        super(props);
    }

    componentDidMount() {
        $('.ui.dropdown.users-projects').dropdown();
    }
    render () {

        return (
            <section className="sub-head z-depth-1">
                <div className="container-fluid">
                    <div className="row">
                        <div className="col m3">
                            <nav>
                                <div className="nav-wrapper">
                                    <SearchInput
                                        closeSearchCallback={this.props.closeSearchCallback}
                                        onChange={this.props.searchFn}/>
                                </div>
                            </nav>
                        </div>
                        <div className="col m4 offset-m1">
                            <div className="row">
                                <div className="col top-12">
                                    <div className="assigned-list">
                                        <p>All members</p>
                                    </div>
                                </div>
                                <div className="col top-10">
                                    <div className="switch">
                                        <label>
                                            <input type="checkbox" />
                                            <span className="lever"/>
                                        </label>
                                    </div>
                                </div>
                                <div className="input-field col top-8">
                                    <div className="list-team">
                                        <span>
                                          Show me posts by
                                          <div className="ui inline dropdown users-projects">
                                            <div className="text">
                                              <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/jenny.jpg"/>
                                              Jenny Hess
                                            </div>
                                            <i className="dropdown icon"/>
                                            <div className="menu">
                                              <div className="item">
                                                <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/jenny.jpg"/>
                                                Jenny Hess
                                              </div>
                                              <div className="item">
                                                <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/elliot.jpg"/>
                                                Elliot Fu
                                              </div>
                                              <div className="item">
                                                <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/stevie.jpg"/>
                                                Stevie Feliciano
                                              </div>
                                              <div className="item">
                                                <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/christian.jpg"/>
                                                Christian
                                              </div>
                                              <div className="item">
                                                <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/matt.jpg"/>
                                                Matt
                                              </div>
                                              <div className="item">
                                                <img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/justen.jpg"/>
                                                Justen Kitsune
                                              </div>
                                            </div>
                                          </div>
                                        </span>
                                    </div>
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