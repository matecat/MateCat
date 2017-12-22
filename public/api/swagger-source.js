$(function () {
    var url = window.location.search.match(/url=([^&]+)/);
    if (url && url.length > 1) {
        url = decodeURIComponent(url[1]);
    } else {
        url = "http://petstore.swagger.io/v2/swagger.json";
    }

    hljs.configure({
        highlightSizeThreshold: 5000
    });
    var spec = {
        "swagger": "2.0",
        "info": {
            "description": "We developed a set of Rest API to let you integrate Matecat in your translation management system or in any other application. Use our API to create projects and check their status.",
            "version": "1.0.0"
        },
        "host": config.swagger_host,
        "schemes": [
            "https"
        ],
        "basePath": "/api",
        "produces": [
            "application/json"
        ],
        "paths": {
            "/new": {
                "post": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Create new Project on Matecat",
                    "description": "Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol.\n/new has a maximum file size limit of 200 MB per file and a max number of files of 600. MateCat PRO accepts 70 file formats. A list of all accepted file are available here: https://www.matecat.com/api/docs.\n",
                    "parameters": [
                        {
                            "name": "files",
                            "in": "formData",
                            "description": "The file(s) to be uploaded. You may also upload your own translation memories (TMX).",
                            "required": true,
                            "type": "file"
                        },
                        {
                            "name": "project_name",
                            "in": "formData",
                            "description": "The name of the project you want create.",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "source_lang",
                            "in": "formData",
                            "description": "RFC 5646 language+region Code ( en-US case sensitive ) as specified in W3C standards.",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "target_lang",
                            "in": "formData",
                            "description": "RFC 5646 language+region Code ( en-US case sensitive ) as specified in W3C standards. Multiple languages must be comma separated ( it-IT,fr-FR,es-ES case sensitive)",
                            "required": true,
                            "type": "integer"
                        },
                        {
                            "name": "tms_engine",
                            "in": "formData",
                            "description": "Identifier for Memory Server 0 means disabled, 1 means MyMemory)",
                            "required": false,
                            "type": "integer",
                            "default": 1
                        },
                        {
                            "name": "mt_engine",
                            "in": "formData",
                            "description": "Identifier for Machine Translation Service 0 means disabled, 1 means get MT from MyMemory).",
                            "required": false,
                            "type": "integer",
                            "default": 1
                        },
                        {
                            "name": "private_tm_key",
                            "in": "formData",
                            "description": "Private key(s) for MyMemory.  If a TMX file is uploaded and no key is provided, a new key will be created. - Existing MyMemory private keys or new to create a new key. - Multiple keys must be comma separated. Up to 5 keys allowed. (xxx345cvf,new,s342f234fc) - Only available if tms_engine is set to 1 or if is not used",
                            "required": false,
                            "type": "string"
                        },
                        {
                            "name": "subject",
                            "in": "formData",
                            "description": "The subject of the project you want to create.",
                            "required": false,
                            "type": "string",
                            "default": "general"
                        },
                        {
                            "name": "segmentation_rule",
                            "in": "formData",
                            "description": "The segmentation rule you want to use to parse your file.",
                            "required": false,
                            "type": "string"
                        },
                        {
                            "name": "owner_email",
                            "in": "formData",
                            "description": "The email of the owner of the project. This parameter is deprecated and being replaced by authentication headers.",
                            "required": false,
                            "type": "string",
                            "default": "anonymous"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "The metadata for the created project.",
                            "schema": {
                                "$ref": "#/definitions/NewProject"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/status": {
                "get": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Retrieve the status of a project",
                    "description": "Check Status of a created Project With HTTP POST ( application/x-www-form-urlencoded ) protocol",
                    "parameters": [
                        {
                            "name": "id_project",
                            "in": "query",
                            "description": "The identifier of the project, should be the value returned by the /new method.",
                            "required": true,
                            "type": "integer"
                        },
                        {
                            "name": "project_pass",
                            "in": "query",
                            "description": "The password associated with the project, should be the value returned by the /new method ( associated with the id_project )",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "An array of price estimates by product",
                            "schema": {
                                "$ref": "#/definitions/Status"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/change_project_password": {
                "post": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Change password",
                    "description": "Change the password of a project.",
                    "parameters": [
                        {
                            "name": "id_project",
                            "in": "formData",
                            "description": "The id of the project you want to update.",
                            "required": true,
                            "type": "integer"
                        },
                        {
                            "name": "old_pass",
                            "in": "formData",
                            "description": "The OLD password of the project you want to update.",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "new_pass",
                            "in": "formData",
                            "description": "The NEW password of the project you want to update.",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "An array of price estimates by product",
                            "schema": {
                                "$ref": "#/definitions/ChangePasswordResponse"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v1/new": {
                "post": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Create new Project on Matecat in detatched mode",
                    "description": "Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol.\n/" +
                        "new has a maximum file size limit of 200 MB per file and a max number of files of 600. " +
                        "This is the same as /new API but it will process the project creation in background. Client can poll the v1 project creation status API " +
                        "to be notified when the project is actually created.",

                    "parameters": [
                        {
                            "name": "files",
                            "in": "formData",
                            "description": "The file(s) to be uploaded. You may also upload your own translation memories (TMX).",
                            "required": true,
                            "type": "file"
                        },
                        {
                            "name": "project_name",
                            "in": "formData",
                            "description": "The name of the project you want create.",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "source_lang",
                            "in": "formData",
                            "description": "RFC 5646 language+region Code ( en-US case sensitive ) as specified in W3C standards.",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "target_lang",
                            "in": "formData",
                            "description": "RFC 5646 language+region Code ( en-US case sensitive ) as specified in W3C standards. Multiple languages must be comma separated ( it-IT,fr-FR,es-ES case sensitive)",
                            "required": true,
                            "type": "integer"
                        },
                        {
                            "name": "tms_engine",
                            "in": "formData",
                            "description": "Identifier for Memory Server 0 means disabled, 1 means MyMemory)",
                            "required": false,
                            "type": "integer",
                            "default": 1
                        },
                        {
                            "name": "mt_engine",
                            "in": "formData",
                            "description": "Identifier for Machine Translation Service 0 means disabled, 1 means get MT from MyMemory).",
                            "required": false,
                            "type": "integer",
                            "default": 1
                        },
                        {
                            "name": "private_tm_key",
                            "in": "formData",
                            "description": "Private key(s) for MyMemory.  If a TMX file is uploaded and no key is provided, a new key will be created. - Existing MyMemory private keys or new to create a new key. - Multiple keys must be comma separated. Up to 5 keys allowed. (xxx345cvf,new,s342f234fc) - Only available if tms_engine is set to 1 or if is not used",
                            "required": false,
                            "type": "string"
                        },
                        {
                            "name": "subject",
                            "in": "formData",
                            "description": "The subject of the project you want to create.",
                            "required": false,
                            "type": "string",
                            "default": "general"
                        },
                        {
                            "name": "segmentation_rule",
                            "in": "formData",
                            "description": "The segmentation rule you want to use to parse your file.",
                            "required": false,
                            "type": "string"
                        },
                        {
                            "name": "owner_email",
                            "in": "formData",
                            "description": "The email of the owner of the project. This parameter is deprecated and being replaced by authentication headers.",
                            "required": false,
                            "type": "string",
                            "default": "anonymous"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "The metadata for the created project.",
                            "schema": {
                                "$ref": "#/definitions/NewProject"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v1/projects/{id_project}/{password}/creation_status": {
                "get": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Shows creation status of a project",
                    "description": "Shows creation status of a project.",
                    "parameters": [
                        {
                            "name": "id_project",
                            "in": "path",
                            "description": "The id of the project",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the project",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "urls",
                            "schema": {
                                "$ref": "#/definitions/ProjectCreationStatus"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v1/jobs/{id_job}/{password}/stats": {
                "get": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Statistics",
                    "description": "Statistics",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Statistics",
                            "schema": {
                                "$ref": "#/definitions/Stats"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/projects/{id_project}/{password}/urls": {
                "get": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Urls of a Project",
                    "description": "Urls of a Project",
                    "parameters": [
                        {
                            "name": "id_project",
                            "in": "path",
                            "description": "The id of the project",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the project",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "urls",
                            "schema": {
                                "$ref": "#/definitions/Urls"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/projects/{id_project}/{password}/jobs/{id_job}/merge": {
                "post": {
                    "tags": [
                        "Project"
                    ],
                    "summary": "Merge",
                    "description": "Merge a splitted project",
                    "parameters": [
                        {
                            "name": "id_project",
                            "in": "formData",
                            "description": "The id of the project",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "formData",
                            "description": "The password of the project",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_job",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "urls"
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/translator": {
                "get": {
                    "tags": [
                        "Job"
                    ],
                    "summary": "Gets the translator assigned to a job",
                    "description": "Gets the translator assigned to a job.",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Job",
                            "schema": {
                                "$ref": "#/definitions/ExtendedJobItem"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
                "post": {
                    "tags": [
                        "Job"
                    ],
                    "summary": "Assigns a job to a translator",
                    "description": "Assigns a job to a translator.",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name" : "email",
                            "in" : "formData",
                            "description" : "email of the translator to assign the job",
                            "required" : true,
                            "type" : "string"
                        },
                        {
                            "name" : "delivery_date",
                            "in" : "formData",
                            "description" : "deliery date for the assignment, expressed as timestamp",
                            "required" : true,
                            "type" : "integer"
                        },
                        {
                            "name" : "timezone",
                            "in" : "formData",
                            "description" : "time zone to convert the delivery_date param expressed as offset based on UTC. Example 1.0, -7.0 etc.",
                            "required" : true,
                            "type" : "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Job",
                            "schema": {
                                "$ref": "#/definitions/ExtendedJobItem"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/comments": {
                "get": {
                    "tags": [
                        "Job"
                    ],
                    "summary": "Get segment comments in a job",
                    "description": "Gets the list of comments on all job segments.",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "from_id",
                            "in": "query",
                            "description": "Only return records starting from this id included",
                            "required": false,
                            "type": "integer"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Comments",
                            "schema": {
                                "$ref": "#/definitions/Comments"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/quality-report": {
                "get": {
                    "tags": [
                        "Project",
                        "Quality Report"
                    ],
                    "summary": "Quality report",
                    "description": "Quality report",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Quality report",
                            "schema": {
                                "$ref": "#/definitions/QualityReport"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/teams": {
                "get": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "List available teams",
                    "description": "Returns a list of all teams the current user is member of.",
                    "parameters": [],
                    "responses": {
                        "200": {
                            "description": "Teams",
                            "schema": {
                                "$ref": "#/definitions/TeamList"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
                "post": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "Create a new team",
                    "description": "Creates a new team.",
                    "parameters" : [
                        {
                            "name" : "type",
                            "type" : "string",
                            "in" : "fromData",
                            "required" : true,
                        },
                        {
                            "name" : "name",
                            "type" : "string",
                            "in" : "fromData",
                            "required" : true
                        },
                        {
                            "name" : "members",
                            "type" : "array",
                            "in" : "fromData",
                            "items" : {
                                "type" : "string",
                                "format" : "email",
                                "collectionFormat" : "multi"
                            },
                            "description" : "Array of email addresses of people to invite in a project",
                            "required" : true
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/TeamItem"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
            },
            "/v2/teams/{id_team}" : {
                "put": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "Update team",
                    "description": "Update team.",
                    "parameters" : [
                        {
                            "name"     : "id_team",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        },
                        {
                            "name" : "name",
                            "type" : "string",
                            "in" : "fromData",
                            "required" : true
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/TeamItem"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
            },
            "/v2/teams/{id_team}/members" : {
                "get": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "List team members",
                    "description": "List team members.",
                    "parameters" : [
                        {
                            "name"     : "id_team",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/TeamMembersList"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
                "post": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "Create new team memberships",
                    "description": "Create new team memberships.",
                    "parameters" : [
                        {
                            "name"     : "id_team",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        },
                        {
                            "name"     : "members",
                            "type"     : "array",
                            "in"       : "fromData",
                            "items"    : {
                                "type"   : "string",
                                "format" : "email",
                                "collectionFormat" : "multi"
                            }
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/TeamMembersList"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
            },
            "/v2/teams/{id_team}/members/{id_member}" : {
                "delete": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "List team members",
                    "description": "List team members.",
                    "parameters" : [
                        {
                            "name"        : "id_team",
                            "type"        : "integer",
                            "in"          : "path",
                            "required"    : true
                        },
                        {
                            "name"        : "id_member",
                            "type"        : "integer",
                            "in"          : "path",
                            "required"    : true,
                            "description" : "Id of the user to remove from team"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/TeamMembersList"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
            },
            "/v2/teams/{id_team}/projects" : {
                "get": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "Get the list of projects in a team",
                    "description": "Get the list of projects in a team.",
                    "parameters" : [
                        {
                            "name"     : "id_team",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/ProjectItem"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
            },
            "/v2/teams/{id_team}/projects/{id_project}" : {
                "get": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "Get a project in a team scope",
                    "description": "Get a project in a team scope.",
                    "parameters" : [
                        {
                            "name"     : "id_team",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        },
                        {
                            "name"     : "id_project",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/ProjectItem"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
                "put": {
                    "tags": [
                        "Teams",
                    ],
                    "summary": "Update a team's project",
                    "description": "Updates a team's project.",
                    "parameters" : [
                        {
                            "name"     : "id_team",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        },
                        {
                            "name"     : "id_project",
                            "type"     : "integer",
                            "in"       : "path",
                            "required" : true,
                        },

                        {
                            "name" : "id_assignee",
                            "type" : "integer",
                            "in" : "formData",
                            "required" : false,
                            "description" : "Provide a user's `uid` property to change project assignee"
                        },
                        {
                            "name" : "id_team",
                            "type" : "integer",
                            "in" : "formData",
                            "required" : false,
                            "description" : "Provide a given project's team to change project team"
                        },
                        {
                            "name" : "name",
                            "type" : "string",
                            "in" : "formData",
                            "required" : false,
                            "description" : "Changes the project's name"
                        },
                    ],
                    "responses": {
                        "200": {
                            "description": "Team",
                            "schema": {
                                "$ref": "#/definitions/ProjectItem"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/translation-issues": {
                "get": {
                    "tags": [
                        "Project",
                        "Translation Issues"
                    ],
                    "summary": "Project translation issues",
                    "description": "Project translation issues",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Translation issues",
                            "schema": {
                                "$ref": "#/definitions/TranslationIssues"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/translation-versions": {
                "get": {
                    "tags": [
                        "Project",
                        "Translation Versions"
                    ],
                    "summary": "Project translation versions",
                    "description": "Project translation versions",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Translation Versions",
                            "schema": {
                                "$ref": "#/definitions/TranslationVersions"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/segments/{id_segment}/translation-versions": {
                "get": {
                    "tags": [
                        "Project",
                        "Translation Versions"
                    ],
                    "summary": "Segment versions",
                    "description": "Segment versions",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "path",
                            "description": "The id of the segment",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Segment versions",
                            "schema": {
                                "$ref": "#/definitions/TranslationVersions"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/segments/{id_segment}/translation-versions/{version_number}": {
                "get": {
                    "tags": [
                        "Project",
                        "Translation Versions"
                    ],
                    "summary": "Get a Segment translation version",
                    "description": "Get a Segment translation version",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "path",
                            "description": "The id of the segment",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "version_number",
                            "in": "path",
                            "description": "The version number",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Segment version",
                            "schema": {
                                "$ref": "#/definitions/TranslationVersion"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/segments/{id_segment}/translation-issues": {
                "post": {
                    "tags": [
                        "Project",
                        "Translation Issues"
                    ],
                    "summary": "Create translation issues",
                    "description": "Create translation issues",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "formData",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "formData",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "formData",
                            "description": "The id of the segment",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "version_number",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_job",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_category",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "severity",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "translation_version",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "target_text",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "start_node",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "start_offset",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "end_node",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "end_offset",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "is_full_segment",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "comment",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Segment version",
                            "schema": {
                                "$ref": "#/definitions/Issue"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/segments/{id_segment}/translation-issues/{id_issue}": {
                "post": {
                    "tags": [
                        "Project",
                        "Translation Issues"
                    ],
                    "summary": "Update translation issues",
                    "description": "Update translation issues",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "formData",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "formData",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "formData",
                            "description": "The id of the segment",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_issue",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "rebutted_at",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Update Translation issue"
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
                "delete": {
                    "tags": [
                        "Project",
                        "Translation Issues"
                    ],
                    "summary": "Delete a translation Issue",
                    "description": "Delete a translation Issue",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "path",
                            "description": "The id of the segment",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_issue",
                            "in": "path",
                            "description": "The id of the issue",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Delete",
                            "schema": {
                                "$ref": "#/definitions/Issue"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/segments/{id_segment}/translation-issues/{id_issue}/comments": {
                "post": {
                    "tags": [
                        "Project",
                        "Translation Issues"
                    ],
                    "summary": "Add comment to a translation issue",
                    "description": "Create a comment translation issue",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "formData",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "formData",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "formData",
                            "description": "The id of the segment",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_issue",
                            "in": "formData",
                            "description": "The id of the issue",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "comment",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_qa_entry",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "source_page",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "uid",
                            "in": "formData",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Add comment"
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                },
                "get": {
                    "tags": [
                        "Project",
                        "Translation Issues"
                    ],
                    "summary": "Get comments",
                    "description": "Get comments",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "path",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "path",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_segment",
                            "in": "path",
                            "description": "The id of the segment",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "id_issue",
                            "in": "path",
                            "description": "The id of the issue",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Get comments"
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/jobs/{id_job}/{password}/options": {
                "post": {
                    "tags": [
                        "Job",
                        "Options"
                    ],
                    "summary": "Update Options",
                    "description": "Update Options (speech2text, guess tags, lexiqa)",
                    "parameters": [
                        {
                            "name": "id_job",
                            "in": "formData",
                            "description": "The id of the job",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "password",
                            "in": "formData",
                            "description": "The password of the job (Translate password)",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "speech2text",
                            "in": "formData",
                            "description": "To enable Speech To Text option",
                            "required": false,
                            "type": "boolean"
                        },
                        {
                            "name": "tag_projection",
                            "in": "formData",
                            "description": "To enable Guess Tags option",
                            "type": "boolean",
                            "required": false
                        },
                        {
                            "name": "lexiqa",
                            "in": "formData",
                            "description": "To enable lexiqa option",
                            "type": "boolean",
                            "required": false
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Update Options",
                            "schema": {
                                "$ref": "#/definitions/Options"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/glossaries/import/": {
                "post": {
                    "tags": [
                        "Glossary"
                    ],
                    "summary": "Import Glossary",
                    "description": "Import glossary file (.xlsx)",
                    "parameters": [
                        {
                            "name": "files",
                            "in": "formData",
                            "description": "The file(s) to be uploaded",
                            "required": true,
                            "type": "file"
                        },
                        {
                            "name": "name",
                            "in": "formData",
                            "description": "The file name.",
                            "type": "string",
                            "required": false
                        },
                        {
                            "name": "tm_key",
                            "in": "formData",
                            "description": "The tm key.",
                            "required": false,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Import Glossary"
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/glossaries/import/status/{tm_key}": {
                "get": {
                    "summary": "Glossary Upload status.",
                    "description": "Glossary Upload status.",
                    "parameters": [
                        {
                            "name": "tm_key",
                            "in": "path",
                            "description": "The tm key.",
                            "required": true,
                            "type": "string"
                        },
                        {
                            "name": "name",
                            "in": "query",
                            "description": "The file name.",
                            "type": "string"
                        }
                    ],
                    "tags": [
                        "Glossary"
                    ],
                    "responses": {
                        "200": {
                            "description": "Glossary Upload status",
                            "schema": {
                                "$ref": "#/definitions/UploadGlossaryStatusObject"
                            }
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            },
            "/v2/glossaries/export/{tm_key}": {
                "get": {
                    "tags": [
                        "Glossary"
                    ],
                    "summary": "Download Glossary",
                    "description": "download Glossary",
                    "parameters": [
                        {
                            "name": "tm_key",
                            "in": "path",
                            "description": "The tm key.",
                            "required": true,
                            "type": "string"
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Glossary"
                        },
                        "default": {
                            "description": "Unexpected error"
                        }
                    }
                }
            }
        },
        "definitions": {
            "NewProject": {
                "type": "object",
                "properties": {
                    "status": {
                        "type": "string",
                        "description": "Return the creation status of the project. The statuses can be:OK indicating that the creation worked.FAIL indicating that the creation is failed.",
                        "enum": [
                            "OK",
                            "FAIL"
                        ]
                    },
                    "id_project": {
                        "type": "string",
                        "description": "Return the unique id of the project just created. If creation status is FAIL this key will simply be omitted from the result."
                    },
                    "project_pass": {
                        "type": "string",
                        "description": "Return the password of the project just created. If creation status is FAIL this key will simply be omitted from the result."
                    },
                    "new_keys": {
                        "type": "string",
                        "description": "If you specified new as one or more value in the private_tm_key parameter, the new created keys are returned as CSV string (4rcf34rc,r34rcfewf3r2). Otherwise empty string is returned"
                    }
                }
            },
            "Status": {
                "type": "object",
                "properties": {
                    "errors": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "A list of objects containing error message at system wide level. Every error has a negative numeric code and a textual message ( currently the only error reported is the wrong version number in config.inc.php file and happens only after Matecat updates, so you should never see it )."
                    },
                    "data": {
                        "$ref": "#/definitions/Data-Status"
                    },
                    "status": {
                        "type": "string",
                        "description": "The analysis status of the project. ANALYZING - analysis/creation still in progress; NO_SEGMENTS_FOUND - the project has no segments to analyze (have you uploaded a file containing only images?); ANALYSIS_NOT_ENABLED - no analysis will be performed because of Matecat configuration; DONE - the analysis/creation is completed; FAIL - the analysis/creation is failed.",
                        "enum": [
                            "ANALYZING",
                            "NO_SEGMENTS_FOUND",
                            "ANALYSIS_NOT_ENABLED",
                            "DONE",
                            "FAIL"
                        ]
                    },
                    "analyze": {
                        "type": "string",
                        "description": "A link to the analyze page; it's a human readable version of this API output"
                    },
                    "jobs": {
                        "$ref": "#/definitions/Jobs-Status"
                    }
                }
            },
            "Data-Status": {
                "type": "object",
                "description": "Holds all progress statisticts for every job and for overall project. It contains jobs and summary sub-sections.",
                "properties": {
                    "jobs": {
                        "$ref": "#/definitions/Jobs"
                    },
                    "summary": {
                        "$ref": "#/definitions/Summary"
                    }
                }
            },
            "Jobs-Status": {
                "type": "object",
                "description": "Section jobs contains all metadata about job (like URIs, quality reports and languages)",
                "properties": {
                    "langpairs": {
                        "type": "object",
                        "description": "the language pairs for your project; an entry for every chunk in the project, with the id-password combination as key and the language pair as the value"
                    },
                    "job-url": {
                        "type": "object",
                        "description": "the links to the chunks of the project; an entry for every chunk in the project, with the id-password combination as key and the link to the chunk as the value."
                    },
                    "job-quality-details": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "a structure containing, for each chunk, an array of 5 objects, each object is a quality check performed on the job; the object contains the type of the check (Typing, Translation, Terminology, Language Quality, Style), the quantity of errors found, the allowed errors threshold and the rating given by the errors/threshold ratio (same as quality-overall)"
                    },
                    "quality-overall": {
                        "type": "object",
                        "description": "the overall quality rating for each chunk (Very good, Good, Acceptable, Poor, Fail)"
                    }
                }
            },
            "Summary": {
                "type": "object",
                "description": "Sub-section summary holds statistict for the whole project that are not related to single job objects.",
                "properties": {
                    "NAME": {
                        "type": "string",
                        "description": "A list of objects containing error message at system wide level. Every error has a negative numeric code and a textual message ( currently the only error reported is the wrong version number in config.inc.php file and happens only after Matecat updates, so you should never see it )."
                    },
                    "STATUS": {
                        "type": "string",
                        "description": "The status the project is from analysis perspective. NEW - just created, not analyzed yet, FAST_OK - preliminary (fast) analysis completed, now running translations (\"TM\") analysis, DONE - analysis complete.",
                        "enum": [
                            "NEW",
                            "FAST_OK",
                            "DONE"
                        ]
                    },
                    "IN_QUEUE_BEFORE": {
                        "type": "string",
                        "description": "Number of segments belonging to other projects that are being analyzed before yours; it's the wait time for you."
                    },
                    "TOTAL_SEGMENTS": {
                        "type": "string",
                        "description": "number of segments belonging to your project."
                    },
                    "SEGMENTS_ANALYZED": {
                        "type": "string",
                        "description": "analysis progress, on TOTAL_SEGMENTS"
                    },
                    "TOTAL_RAW_WC": {
                        "type": "string",
                        "description": "number of words (word count) of your project, as extracted by the textual parsers"
                    },
                    "TOTAL_STANDARD_WC": {
                        "type": "string",
                        "description": "word count, minus the sentences that are repeated"
                    },
                    "TOTAL_FAST_WC": {
                        "type": "string",
                        "description": "word count, minus the sentences that are partially repeated"
                    },
                    "TOTAL_TM_WC": {
                        "type": "string",
                        "description": "word count, with sentences found in the cloud translation memory discounted from the total; this depends on the percentage of overlapping between the sentences of your project and the past translations"
                    },
                    "TOTAL_PAYABLE": {
                        "type": "string",
                        "description": "total word count, after analysis."
                    }
                }
            },
            "Jobs": {
                "type": "object",
                "description": "Sub-section jobs holds statistict for all the job objects. The numerical keys on the first level are the IDs of the jobs contained in the project. Each job identifies a target language; as such, there is a 1-1 mapping between ID and target languages in your project. A job holds a chunks and a totals section.",
                "properties": {
                    "id_job": {
                        "$ref": "#/definitions/Job"
                    }
                }
            },
            "Job": {
                "type": "object",
                "description": "The numerical keys on the first level are the IDs of the jobs contained in the project. Each job identifies a target language; as such, there is a 1-1 mapping between ID and target languages in your project.",
                "properties": {
                    "chunk": {
                        "type": "object",
                        "description": "A structure modeling a portion of content to translate.  A whole file can be splitted in multiple chunks, to be distributed to multiple translators, or can be enveloped in a single chunk. Each chunk has a password as first level key and a numerical ID as second level key to identify different chunks for the same file. Each chunk contains the same structure of the totals section. The sum of the chunks equals to the totals."
                    },
                    "totals": {
                        "$ref": "#/definitions/Totals"
                    }
                }
            },
            "Totals": {
                "type": "object",
                "description": "Contains all analysis statistics for all files in the current job (i.e., all files that have to be translated in a target language)",
                "properties": {
                    "job_pass": {
                        "$ref": "#/definitions/Total"
                    }
                }
            },
            "Total": {
                "type": "object",
                "description": "password as first level key.",
                "properties": {
                    "TOTAL_PAYABLE": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "total word count, after analysis"
                    },
                    "REPETITIONS": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word count for the segments that repeat themselves in the file"
                    },
                    "INTERNAL_MATCHES": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word count for the segments that fuzzily overlap with others in the file, while not being an exact repetition"
                    },
                    "MT": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word count for all segments that can be translated with machine translation; it accounts for all the information that could not be discounted by repetitions, internal matches or translation memory"
                    },
                    "NEW": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word count for segments that can't be discounted with repetition or internal matches; it's the net translation effort"
                    },
                    "TM_100": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word count for the exact matches found in TM server"
                    },
                    "TM_75_99": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word count for partial matches in the TM that cover 75-99% of each segment"
                    },
                    "ICE": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word count for 100% TM matches that also share the same context with the TM"
                    },
                    "NUMBERS_ONLY": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        },
                        "description": "cumulative word counts for segments made of numberings, dates and similar not translatable data ( i.e. 93 / 127 )"
                    }
                }
            },
            "ChangePasswordResponse": {
                "type": "object",
                "properties": {
                    "status": {
                        "type": "string",
                        "description": "Return the exit status of the action. The statuses can be OK or FAIL",
                        "enum": [
                            "OK",
                            "FAIL"
                        ]
                    },
                    "id_project": {
                        "type": "string",
                        "description": "Returns the id of the project just updated"
                    },
                    "project_pass": {
                        "type": "string",
                        "description": "Returns the new pass of the project just updated"
                    },
                    "message": {
                        "type": "string",
                        "description": "Return the error message for the action if the status is FAIL"
                    }
                }
            },
            "Stats": {
                "type": "object",
                "properties": {
                    "id": {
                        "type": "integer"
                    },
                    "DRAFT": {
                        "type": "number"
                    },
                    "TRANSLATED": {
                        "type": "number"
                    },
                    "APPROVED": {
                        "type": "number"
                    },
                    "REJECTED": {
                        "type": "number"
                    },
                    "TOTAL": {
                        "type": "number"
                    },
                    "PROGRESS": {
                        "type": "number"
                    },
                    "TOTAL_FORMATTED": {
                        "type": "number"
                    },
                    "PROGRESS_FORMATTED": {
                        "type": "string"
                    },
                    "APPROVED_FORMATTED": {
                        "type": "string"
                    },
                    "REJECTED_FORMATTED": {
                        "type": "string"
                    },
                    "DRAFT_FORMATTED": {
                        "type": "string"
                    },
                    "TRANSLATED_FORMATTED": {
                        "type": "string"
                    },
                    "APPROVED_PERC": {
                        "type": "number"
                    },
                    "REJECTED_PERC": {
                        "type": "number"
                    },
                    "DRAFT_PERC": {
                        "type": "number"
                    },
                    "TRANSLATED_PERC": {
                        "type": "number"
                    },
                    "PROGRESS_PERC": {
                        "type": "number"
                    },
                    "TRANSLATED_PERC_FORMATTED": {
                        "type": "number"
                    },
                    "DRAFT_PERC_FORMATTED": {
                        "type": "number"
                    },
                    "APPROVED_PERC_FORMATTED": {
                        "type": "number"
                    },
                    "REJECTED_PERC_FORMATTED": {
                        "type": "number"
                    },
                    "PROGRESS_PERC_FORMATTED": {
                        "type": "number"
                    },
                    "TODO_FORMATTED": {
                        "type": "string"
                    },
                    "DOWNLOAD_STATUS": {
                        "type": "string"
                    },
                    "ANALYSIS_COMPLETE": {
                        "type": "string"
                    }
                }
            },
            "Urls": {
                "type": "object",
                "properties": {
                    "files": {
                        "$ref": "#/definitions/Files"
                    },
                    "jobs": {
                        "$ref": "#/definitions/UrlsJobs"
                    }
                }
            },
            "Files": {
                "type": "array",
                "items": {
                    "$ref": "#/definitions/JobFile"
                }
            },
            "JobFile": {
                "type": "object",
                "properties": {
                    "id": {
                        "type": "string"
                    },
                    "name": {
                        "type": "string"
                    },
                    "original_download_url": {
                        "type": "string"
                    },
                    "translation_download_url": {
                        "type": "string"
                    },
                    "xliff_download_url": {
                        "type": "string"
                    }
                }
            },
            "UrlsJobs": {
                "type": "array",
                "items": {
                    "$ref": "#/definitions/UrlsJob"
                }
            },
            "UrlsJob": {
                "type": "object",
                "properties": {
                    "id": {
                        "type": "string"
                    },
                    "target_lang": {
                        "type": "string"
                    },
                    "chunks": {
                        "type": "array",
                        "items": {
                            "$ref": "#/definitions/Chunk"
                        }
                    }
                }
            },
            "Chunk": {
                "type": "object",
                "properties": {
                    "password": {
                        "type": "string"
                    },
                    "translate_url": {
                        "type": "string"
                    },
                    "revise_url": {
                        "type": "string"
                    }
                }
            },
            "TranslationIssues": {
                "type": "array",
                "items": {
                    "$ref": "#/definitions/Issue"
                }
            },
            "Issue": {
                "type": "object",
                "properties": {
                    "comment": {
                        "type": "string"
                    },
                    "created_at": {
                        "type": "string"
                    },
                    "id": {
                        "type": "string"
                    },
                    "id_category": {
                        "type": "string"
                    },
                    "id_job": {
                        "type": "string"
                    },
                    "id_segment": {
                        "type": "string"
                    },
                    "is_full_segment": {
                        "type": "string"
                    },
                    "severity": {
                        "type": "string"
                    },
                    "start_node": {
                        "type": "string"
                    },
                    "start_offset": {
                        "type": "string"
                    },
                    "end_node": {
                        "type": "string"
                    },
                    "end_offset": {
                        "type": "string"
                    },
                    "translation_version": {
                        "type": "string"
                    },
                    "target_text": {
                        "type": "string"
                    },
                    "penality_points": {
                        "type": "string"
                    },
                    "rebutted_at": {
                        "type": "string"
                    }
                }
            },
            "Comments": {
                "type": "array",
                "items": {
                    "$ref": "#/definitions/Comment"
                }
            },
            "Comment": {
                "type": "object",
                "properties": {
                    "id": {
                        "type": "string"
                    },
                    "id_job": {
                        "type": "string"
                    },
                    "id_segment": {
                        "type": "string"
                    },
                    "created_at": {
                        "type": "string"
                    },
                    "email": {
                        "type": "string"
                    },
                    "full_name": {
                        "type": "string"
                    },
                    "uid": {
                        "type": "integer",
                        "format": "int32"
                    },
                    "resolved_at": {
                        "type": "string"
                    },
                    "source_page": {
                        "type": "integer",
                        "format": "int32"
                    },
                    "mwssage_type": {
                        "type": "integer",
                        "format": "int32"
                    },
                    "message": {
                        "type": "string"
                    }
                }
            },
            "QualityReport": {
                "type": "object",
                "properties": {
                    "chunk": {
                        "type": "object"
                    },
                    "job": {
                        "type": "object"
                    },
                    "project": {
                        "type": "object"
                    }
                }
            },
            "TranslationVersions": {
                "type": "array",
                "items": {
                    "$ref": "#/definitions/TranslationVersion"
                }
            },
            "TranslationVersion": {
                "type": "object",
                "properties": {
                    "id": {
                        "type": "integer",
                        "format": "int32"
                    },
                    "id_segment": {
                        "type": "integer",
                        "format": "int32"
                    },
                    "id_job": {
                        "type": "integer",
                        "format": "int32"
                    },
                    "translation": {
                        "type": "string"
                    },
                    "version_number": {
                        "type": "string"
                    },
                    "propagated_from": {
                        "type": "integer",
                        "format": "int32"
                    },
                    "created_at": {
                        "type": "string"
                    }
                }
            },
            "Options": {
                "type": "object",
                "properties": {
                    "speech2text": {
                        "type": "integer"
                    },
                    "tag_projection": {
                        "type": "integer"
                    },
                    "lexiqa": {
                        "type": "integer"
                    }
                }
            },
            "UploadGlossaryStatusObject": {
                "type": "object",
                "properties": {
                    "error": {
                        "type": "array",
                        "items": {
                            "type": "object"
                        }
                    },
                    "data": {
                        "$ref": "#/definitions/UploadGlossaryStatus"
                    },
                    "success": {
                        "type": "boolean"
                    }
                }
            },
            "UploadGlossaryStatus": {
                "type": "object",
                "properties": {
                    "done": {
                        "type": "integer"
                    },
                    "total": {
                        "type": "integer"
                    },
                    "source_lang": {
                        "type": "string"
                    },
                    "target_lang": {
                        "type": "string"
                    },
                    "completed": {
                        "type": "boolean"
                    }
                }
            },

            "PendingInvitation" : {
                "type" : "array",
                "items" : {
                    "type" : "string",
                    "description" : "Email address of the invited user"
                }
            },

            "TeamMembersList" : {
                "type": "object",
                "properties" : {
                    "members" : {
                        "type" : "array",
                        "items" : {
                            "$ref" : "#/definitions/TeamMember"
                        }
                    },
                    "pending_invitations" : {
                        "type" : "array",
                        "items" : {
                            "$ref" : "#/definitions/PendingInvitation"
                        }
                    }
                }
            },

            "TeamMember" : {
                "type": "object",
                "properties" : {
                    "id" : { "type" : "integer" },
                    "id_team" : { "type" : "integer" },
                    "user" : {
                        "type" : "object",
                        "$ref" : "#/definitions/User"
                    }
                }
            },

            "User" : {
                "type" : "object",
                "properties" : {
                    "uid" : { "type" : "integer" },
                    "first_name" : { "type" : "string" } ,
                    "last_name" : { "type" : "string" } ,
                    "email" : { "type" : "string" },
                    "has_password" : { "type" : "boolean" }
                }
            },

            "TeamList" : {
                "type": "object",
                "properties" : {
                    "teams" : {
                        "type" : "array",
                        "items" : {
                            "$ref" : "#/definitions/Team"
                        }
                    }
                }
            },

            "TeamItem" : {
                "type": "object",
                "properties" : {
                    "team" : {
                        "type" : "object",
                        "$ref" : "#/definitions/Team"
                    }
                }
            },

            "Team" : {
                "type": "object",
                "properties": {
                    "id" : { "type" : "integer", "required" : true },
                    "name": { "type" : "string", "required" : true },
                    "type": {
                        "type" : "string",
                        "enum" : ["general", "personal"],
                        "required" : true
                    },
                    "created_at": {
                        "type" : "string" ,
                        "format" : "date-time",
                        "required" : true
                    },
                    "created_by": {"type" : "integer", "required" : true },
                    "pending_invitations": { "type" : "array", "items" : "string" }
                }
            },

            "ProjectItem" : {
                "type" : "object" ,
                "properties" : {

                    "project" : {
                        "type" : "object",
                        "$ref" : "#/definitions/Project"
                    }
                }
            },

            "ExtendedJob" : {
                "type" : "object",

                "properties" : {
                    "id": { "type" : "integer" },
                    "password": { "type" : "password" },
                    "source": { "type" : "string" },
                    "target": { "type" : "string" },
                    "sourceTxt": { "type" : "string" },
                    "targetTxt": { "type" : "string" },
                    "status": { "type" : "string" } ,
                    "subject": { "type" : "string" },
                    "owner": { "type" : "string", "format" : "email" },
                    "open_threads_count": { "type" : "integer" },
                    "create_timestamp": { "type" : "integer" },
                    "create_date": { "type" : "string", "format" : "date-time" },
                    // "formatted_create_date": "Oct 23, 08:37", // TODO: to be removed from SERVER
                    "quality_overall": { "type" : "string" },
                    "pee": { "type" : "integer" },
                    "private_tm_key": { "type" : "string" },
                    "warnings_count": { "type" : "integer" },
                    "warning_segments": {
                        "type" : "array",
                        "items" : {
                            "type" : "object"
                        }
                    },
                    "outsource": {
                        "type" : "object",
                        "$ref" : "#/definitions/OutsourceConfirmation"
                    },

                    "translator": {
                        "type" : "object",
                        "$ref" : "#/definitions/Translator"
                    },

                    "total_raw_wc": { "type" : "float" },
                    "stats" : {
                        "type" : "object",
                        "$ref" : "#/definitions/Stats"
                    }
                }
            },

            "Translator" : {
                "type" : "object",
                "properties" : {
                    "email" : { "type" : "string", "format" : "email" },
                    "added_by" : { "type" : "integer" },
                    "delivery_date" : { "type" : "string" },
                    "delivery_timestamp"    : { "type" : "string" },
                    "source"                : { "type" : "string" },
                    "target"                : { "type" : "string" },
                    "id_translator_profile" : { "type" : "integer" },
                    "user"                  : {
                        "type" : "object",
                        "$ref" : "#/definitions/User"
                    }
                }
            },

            "OutsourceConfirmation" : {
                "type" : "object",
                "properties" : {
                    "create_timestamp" : {
                        "type" : "string",
                        "format" : "date-time",
                        "required" : true
                    },
                    "delivery_timestamp" : {
                        "type" : "integer",
                    },
                    "quote_review_link" : {

                    }
                }
            },
            "Project" : {
                "type" : "object",
                "properties" : {
                    "id": { "type" : "integer" },
                    "password": { "type" : "string" },
                    "name": { "type" : "string" },
                    "id_team": { "type" : "integer" },
                    "id_assignee": { "type" : "integer" } ,
                    "create_date": { "type" : "string", "format" : "date-time" },
                    "fast_analysis_wc": { "type" : "float" },
                    "standard_analysis_wc": { "type" : "float" },
                    "project_slug": { "type" : "string" },
                    "features": { "type" : "string" },
                    "is_cancelled": { "type" : "boolean" },
                    "is_archived": { "type" : "boolean" },
                    "remote_file_service": { "type" : "string" },
                    "jobs" : {
                        "type" : "array",
                        "items" : {
                            "$ref" : "#/definitions/ExtendedJob"
                        }
                    }
                }
            },

            "ExtendedJobItem" : {
                "type" : "object",
                "properties" : {
                    "job" : {
                        "$ref" : "#/definitions/ExtendedJob"
                    }
                }
            },

            "ProjectCreationStatus" : {
                "type" : "object",
                "properties" : {
                    "status": { "type" : "integer" },
                    "message": { "type" : "string" },
                    "id_project": { "type" : "integer" },
                    "project_pass": { "type" : "string" },
                    "project_name": { "type" : "string" },
                    "new_keys": { "type" : "string" },
                    "analyze_url": { "type" : "string" }
                }
            },

            "Error" : {
                "type" : "object",
                "properties" : {
                    "errors" : {
                        "type" : "array",
                        "items" : {
                            "type" : "object",
                            "properties" : {
                                "code" : "integer",
                                "message" : "string"
                            }
                        }
                    },
                    "data" : {
                        "type" : "array",
                        "items" : { "type" : "object" },
                        "description" : "This property contains any debug data that can " +
                        "serve for better understanding of the error"
                    }
                }
            }
        }
    };

    // Pre load translate...
//      if(window.SwaggerTranslator) {
//        window.SwaggerTranslator.translate();
//      }
    window.swaggerUi = new SwaggerUi({
        url: url,
        spec: spec,
        dom_id: "swagger-ui-container",
        supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
        onComplete: function(swaggerApi, swaggerUi){
//          if(typeof initOAuth == "function") {
//            initOAuth({
//              clientId: "your-client-id",
//              clientSecret: "your-client-secret-if-required",
//              realm: "your-realms",
//              appName: "your-app-name",
//              scopeSeparator: " ",
//              additionalQueryStringParams: {}
//            });
//          }
//
//          if(window.SwaggerTranslator) {
//            window.SwaggerTranslator.translate();
//          }
        },
        onFailure: function(data) {
            log("Unable to Load SwaggerUI");
        },
        docExpansion: "none",
        jsonEditor: false,
        defaultModelRendering: 'schema',
        showRequestHeaders: false
    });

    window.swaggerUi.load();

    function log() {
        if ('console' in window) {
            console.log.apply(console, arguments);
        }
    }
});
