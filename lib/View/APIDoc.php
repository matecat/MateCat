<?php
require_once '../../inc/Bootstrap.php';
Bootstrap::start();

$count = 0;
foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
    $count += count( $value );
}

$nr_supoported_files = $count;

$max_file_size_in_MB = INIT::$MAX_UPLOAD_FILE_SIZE / (1024 * 1024);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>API - Matecat</title>
    <link href="/public/css/style.css" rel="stylesheet" type="text/css" />
    <link href="/public/css/manage.css" rel="stylesheet" type="text/css" />
    <link href="/public/css/build/common.css" rel="stylesheet" type="text/css" />
    <script src="/public/js/lib/jquery.js"></script>
      <link rel="icon" type="image/png" href="images/favicon-32x32.png" sizes="32x32" />
  <link rel="icon" type="image/png" href="images/favicon-16x16.png" sizes="16x16" />
  <link href='/public/api/dist/css/screen.css' media='screen' rel='stylesheet' type='text/css'/>
  <link href='/public/api/dist/css/print.css' media='print' rel='stylesheet' type='text/css'/>

  <script src='/public/api/dist/lib/object-assign-pollyfill.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery-1.8.0.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery.slideto.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery.wiggle.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jquery.ba-bbq.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/handlebars-4.0.5.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/lodash.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/backbone-min.js' type='text/javascript'></script>
  <script src='/public/api/dist/swagger-ui.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/highlight.9.1.0.pack.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/highlight.9.1.0.pack_extended.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/jsoneditor.min.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/marked.js' type='text/javascript'></script>
  <script src='/public/api/dist/lib/swagger-oauth.js' type='text/javascript'></script>
  <script type="text/javascript">
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
        "host": "www.matecat.com",
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
              "description": "Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol.\n/new has a maximum file size limit of 200 MB per file and a max number of files of 600. Matecat PRO accept only 68 file formats. A list of all accepted file are available here: https://www.matecat.com/api/docs.\n",
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
                  "description": "The email of the owner of the project.",
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
//          "/v2/project-completion-status/{id_project}": {
//            "get": {
//              "tags": [
//                "Project"
//              ],
//              "summary": "Project completion status",
//              "description": "Add \"Mark as Complete\" button",
//              "parameters": [
//                {
//                  "name": "id_project",
//                  "in": "path",
//                  "description": "The id of the project",
//                  "required": true,
//                  "type": "string"
//                }
//              ],
//              "responses": {
//                "200": {
//                  "description": "Project completion status"
//                },
//                "default": {
//                  "description": "Unexpected error"
//                }
//              }
//            }
//          },
          "/v2/jobs/{id_job}/{password}/comments": {
            "get": {
              "tags": [
                "Project",
                "Comments"
              ],
              "summary": "Get Comments",
              "description": "Get Comments",
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
              "id:job": {
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
              "errore": {
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
  </script>
</head>
<body class="api swagger-section pippo">



<header>
    <div class="wrapper ">
        <a href="/" class="logo"></a>
    </div>
</header>
<div id="contentBox" class="wrapper">
    <div class="colsx">
        <a href="#top"><span class="logosmall"></span></a>
        <h1>API</h1>
        <ul class="menu">
              
            
                <li data-id="Project"><a class="anchor_api">Project</a></li>

              
                <li data-id="Comments"><a class="anchor_api">Comments</a></li>
                <li data-id="Quality_Report"><a class="anchor_api">Quality Report</a></li>
                <li data-id="Translation_Issues"><a class="anchor_api">Translation Issues</a></li>
                <li data-id="Translation_Versions"><a class="anchor_api">Translation Versions</a></li>
                <li data-id="Job"><a class="anchor_api">Job</a></li>
                <li data-id="Options"><a class="anchor_api">Options</a></li>
                <li data-id="Glossary"><a class="anchor_api">Glossary</a></li>
              
            
            <li><a href="#file-format">Supported file format</a></li>
            <li><a href="#languages">Supported languages</a></li>
            <li><a href="#subjects">Supported subjects</a></li>
            <li><a href="#seg-rules">Supported segmentation rules</a></li>
        </ul>
    </div>
            <a name="top" class="top"></a>
    <div class="coldx">
        <div class="block block-api block-swagger">
             <a name="api-swagger"><h3 class="method-title">List of commands</h3></a>      
            <div id="swagger-ui-container" class="swagger-ui-wrap">
                <div id="message-bar" class="swagger-ui-wrap" data-sw-translate>&nbsp;</div>
            </div>
              <a class="gototop" href="#top">Go to top</a>
        </div>
        <div class="block block-api">
            <a name="file-format"><h3 class="method-title">Supported file formats</h3></a>


            <table class="tablestats fileformat" width="100%" border="0" cellspacing="0" cellpadding="0">

                <thead>
                <tr><th width="40%">Office</th>
                    <th width="15%">Web</th>
                    <th width="15%">Interchange Formats</th>
                    <th width="15%">Desktop Publishing</th>
                    <th width="15%">Localization</th>
                </tr></thead>
                <tbody><tr>
                    <td>
                        <ul class="office">
                            <li><span class="extdoc">doc</span></li>
                            <li><span class="extdoc">dot</span></li>
                            <li><span class="extdoc">docx</span></li>
                            <li><span class="extdoc">dotx</span></li>
                            <li><span class="extdoc">docm</span></li>
                            <li><span class="extdoc">dotm</span></li>
                            <li><span class="extdoc">rtf</span></li>
                            <li><span class="extdoc">odt</span></li>
                            <li><span class="extdoc">sxw</span></li>
                            <li><span class="exttxt">txt</span></li>
                            <li><span class="extpdf">pdf</span></li>
                            <li><span class="extxls">xls</span></li>
                            <li><span class="extxls">xlt</span></li>
                            <li><span class="extxls">xlsm</span></li>
                            <li><span class="extxls">xlsx</span></li>
                            <li><span class="extxls">xltx</span></li>
                            <li><span class="extxls">ods</span></li>
                            <li><span class="extxls">sxc</span></li>
                            <li><span class="extxls">csv</span></li>
                            <li><span class="extppt">pot</span></li>
                            <li><span class="extppt">pps</span></li>
                            <li><span class="extppt">ppt</span></li>
                            <li><span class="extppt">potm</span></li>
                            <li><span class="extppt">potx</span></li>
                            <li><span class="extppt">ppsm</span></li>
                            <li><span class="extppt">ppsx</span></li>
                            <li><span class="extppt">pptm</span></li>
                            <li><span class="extppt">pptx</span></li>
                            <li><span class="extppt">odp</span></li>
                            <li><span class="extppt">sxi</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="exthtm">htm</span></li>
                            <li><span class="exthtm">html</span></li>
                            <li><span class="exthtm">xhtml</span></li>
                            <li><span class="extxml">xml</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extxif">xliff</span></li>
                            <li><span class="extxif">sdlxliff</span></li>
                            <li><span class="exttmx">tmx</span></li>
                            <li><span class="extttx">ttx</span></li>
                            <li><span class="extitd">itd</span></li>
                            <li><span class="extxlf">xlf</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extmif">mif</span></li>
                            <li><span class="extidd">inx</span></li>
                            <li><span class="extidd">idml</span></li>
                            <li><span class="extidd">icml</span></li>
                            <li><span class="extqxp">xtg</span></li>
                            <li><span class="exttag">tag</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extdit">dita</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extpro">properties</span></li>
                            <li><span class="extrcc">rc</span></li>
                            <li><span class="extres">resx</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extdit">dita</span></li>
                            <li><span class="extsgl">sgml</span></li>
                            <li><span class="extsgm">sgm</span></li>
                            <li><span class="extxml">Android xml</span></li>
                            <li><span class="extstr">strings</span></li>
                        </ul>
                    </td>
                </tr>
                </tbody></table>


            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <a name="languages"><h3 class="method-title">Supported languages</h3></a>

            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <th>
                    Language ( Code )
                </th>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <ul class="lang-list">
                            <?php foreach( Langs_Languages::getInstance()->getEnabledLanguages() as $lang ): ?>
                            <li><?=$lang['name'] . " (" . $lang['code']  . ")"?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
            <a name="subjects"><h3 class="method-title">Supported subjects</h3></a>


            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Subject name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach( Langs_LanguageDomains::getInstance()->getEnabledDomains() as $domains ): ?>
                <tr><td><?=$domains['display']?></td><td><?=$domains['key']?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>


        <div class="block block-api">
            <a name="seg-rules"><h3 class="method-title">Supported segmentation rules</h3></a>


            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Segmentation rule name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>General </td><td><code>empty</code></td></tr>
                <tr><td>Patent</td><td>patent</td></tr>

                </tbody>
            </table>
            <a class="last gototop" href="#top">Go to top</a>
        </div>

        <div class="block block-api">
        </div>
        
    </div>
</div>
<script type="text/javascript">
// add active class to menu

  $(".menu a").click(function() {
        if ($(this).hasClass('active')) {
          console.log('active');
          
        }
        else {
          $(".menu a").removeClass('active');
           $(this).addClass('active');
           console.log('inactive');
        }
       
    });
// anchor menu when scrolling

    $(window).scroll(function() {
        var scroll = $(window).scrollTop();

        if (scroll >= 30) {
            $(".colsx").addClass("menuscroll");
        }
        else {
            $(".colsx").removeClass("menuscroll");
        }

    });
  

// smooth scrolling

    $(function() {
        $('a[href*=#]:not([href=#])').click(function() {
            if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) +']');
                if (target.length) {
                    $('html,body').animate({
                        scrollTop: target.offset().top
                    }, 1000);
                    return false;
                }
            }
        });
    });

// scroll to id + add active on menu

 $(".anchor_api").click(function() {
                    var name = $(this).closest("li").attr("data-id");
                      $('html, body').animate({
                          scrollTop: $("#resource_"+name).offset().top
                      }, 500,function() {
                        
                    });  
                       if ($(this).hasClass("selected") && $("#resource_"+name).hasClass('active')) {
                            console.log("selected");
                        }
                        else {
                          if (!$("#resource_"+name).hasClass('active')) {
                            console.log("selected");
                            $(this).addClass('selected');
                          $("#resource_"+name+ " #endpointListTogger_"+name).click();
                            console.log("selected");
                        }
                          
                        }
                  });
</script>
</body>
</html>
