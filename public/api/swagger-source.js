var spec = {
  swagger: '2.0',
  info: {
    title: 'Matecat API',
    description: `<p>We developed a set of Rest API to let you integrate Matecat in your translation management system or in any other application. Use our API to create projects and check their status.</p>
      <h2>How to authenticate</h2>
      <div class="opblock opblock-get">
        <div class="opblock-summary opblock-summary-get">
            In order to authenticate, add the x-matecat-key header to the API call and populate it with your complete API credentials in this format: <code>{APIkey}-{APIsecret}</code>
        </div>
      </div>
      `,
    version: '3.0.0',
  },
  host: config.swagger_host,
  schemes: ['https'],
  produces: ['application/json'],
  paths: {
    '/api/v3/projects/{id_project}/{password}': {
      get: {
        tags: ['Project'],
        summary: 'Get project information',
        description: 'Retrieve information on the specified Project',

        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The project ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The project Password',
            required: true,
            type: 'string',
          },
        ],

        responses: {
          200: {
            description: 'The metadata for the requested project.',
            schema: {
              $ref: '#/definitions/Project',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v1/new': {
      post: {
        tags: ['Project'],
        summary: 'Create new Project on Matecat in detached mode',
        description: `Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol.
          new has a maximum file size limit of 200 MB per file and a max number of files of 600. 
          This API will process the project creation in background. Client can poll the v1 project creation status API to be notified when the project is actually created.
          `,
        parameters: [
          {
            name: 'files[]',
            in: 'formData',
            description:
              'The file(s) to be uploaded, supported file formats <a href="#file-format">available here</a>. You may also upload TMX files, which will trigger the creation of a new translation memory in the account.',
            required: true,
            type: 'file',
          },
          {
            name: 'project_name',
            in: 'formData',
            description: 'The name of the project you want create.',
            required: true,
            type: 'string',
          },
          {
            name: 'source_lang',
            in: 'formData',
            description:
              'RFC 5646 language+region Code ( en-US case sensitive ) as specified in W3C standards.',
            required: true,
            type: 'string',
          },
          {
            name: 'target_lang',
            in: 'formData',
            description:
              'RFC 5646 language+region Code ( en-US case sensitive ) as specified in W3C standards. Multiple languages must be comma separated ( it-IT,fr-FR,es-ES case sensitive)',
            required: true,
            type: 'string',
          },
          {
            name: 'tms_engine',
            in: 'formData',
            description:
              'Identifier for Memory Server 0 means disabled, 1 means MyMemory)',
            required: false,
            type: 'integer',
            default: 1,
          },
          {
            name: 'mt_engine',
            in: 'formData',
            description:
              "Identifier for Machine Translation Engine. 0 deactivates MT, 1 uses ModernMT Lite, other values correspond to the user's personal MT engines (available engines are retrieved via the /api/v3/engines/list endpoint).",
            required: false,
            type: 'integer',
            default: 1,
          },
          {
            name: 'private_tm_key',
            in: 'formData',
            description:
              'Private key(s) for MyMemory.  If a TMX file is uploaded and no key is provided, a new key will be created. - Existing MyMemory private keys or new to create' +
              ' a new key. - Multiple keys must be comma separated. Up to 10 keys allowed. (xxx345cvf,new,s342f234fc) - If you want to set read, write or both on your private key you can' +
              " add after the key 'r' for read, 'w' for write or 'rw' for both  separated by ':' (xxx345cvf:r,new:w,s342f234fc:rw) - Only available if tms_engine is set to 1 or if is not used",
            required: false,
            type: 'string',
          },
          {
            name: 'subject',
            in: 'formData',
            description: 'The subject of the project you want to create.',
            required: false,
            type: 'string',
            default: 'general',
          },
          {
            name: 'segmentation_rule',
            in: 'formData',
            description:
              'The segmentation rule you want to use to parse your file.',
            required: false,
            type: 'string',
          },
          {
            name: 'owner_email',
            in: 'formData',
            description:
              'The email of the owner of the project. This parameter is deprecated and being replaced by authentication headers.',
            required: false,
            type: 'string',
            default: 'anonymous',
          },
          {
            name: 'due_date',
            in: 'formData',
            description:
              'If you want to set a due date for your project, send this param with a timestamp',
            required: false,
            type: 'string',
          },
          {
            name: 'id_team',
            in: 'formData',
            description: 'The team you want to assign this project',
            required: false,
            type: 'string',
          },
          {
            name: 'payable_rate_template_id',
            in: 'formData',
            description:
              'The id of the billing model you want to use in the project you are creating (if you want to use a custom billing model in a project, both relevant parameters must be included in the API call)',
            required: false,
            type: 'integer',
          },
          {
            name: 'payable_rate_template_name',
            in: 'formData',
            description:
              'The name of the billing model you want to use in the project you are creating (if you want to use a custom billing model in a project, both relevant parameters must be included in the API call)',
            required: false,
            type: 'string',
          },
          {
            name: 'get_public_matches',
            in: 'formData',
            description: 'Enable suggestions from the Public TM',
            required: false,
            type: 'string',
            default: 'true',
            enum: ['true', 'false'],
          },
          {
            name: 'pretranslate_100',
            in: 'formData',
            description: 'Pre-translate 100% matches from TM',
            required: false,
            type: 'integer',
            default: 0,
          },
          {
            name: 'dialect_strict',
            in: 'formData',
            description:
              'Specify if you want matches only from dialect (excluding the other language variants)',
            required: false,
            type: 'string',
            example: '{"it-IT": true, "en-US": false, "fr-FR": false}',
          },
          {
            name: 'mmt_glossaries',
            in: 'formData',
            description: 'Load specific MMT glossaries',
            required: false,
            type: 'string',
            example:
              '{"glossaries": [1, 2, 3, 4], "ignore_glossary_case": true }',
          },
          {
            name: 'deepl_formality',
            in: 'formData',
            description:
              'DeepL formality (choose between `default`, `prefer_less` or `prefer_more`)',
            required: false,
            type: 'string',
            default: 'default',
            enum: ['default', 'prefer_less', 'prefer_more'],
          },
          {
            name: 'deepl_id_glossary',
            in: 'formData',
            description: 'Specify a DeepL glossary',
            required: false,
            type: 'string',
            example: '34532',
          },
          {
            name: 'filters_extraction_parameters',
            in: 'formData',
            description:
              'Set the filters extraction parameters throught a json string',
            required: false,
            type: 'string',
            example:
              '{"name": "parameters": {"json": {"extract_arrays": true, "escape_forward_slashes": false, "translate_keys": ["key"], "do_not_translate_keys": [], "context_keys": [], "character_limit": []}}}',
          },
          {
            name: 'xliff_parameters',
            in: 'formData',
            description: 'Specific parameters for the analysis of Xliff files',
            required: false,
            type: 'string',
            example:
              '{"xliff12": [{"analysis": "pre-translated", "states": ["final"], "editor": "new"}], "xliff20": []}',
          },
          {
            name: 'xliff_parameters_template_id',
            in: 'formData',
            description:
              'Load a specific template for the analysis of Xliff files',
            required: false,
            type: 'string',
            example: '3213',
          },
          {
            name: 'filters_extraction_parameters_template_id',
            in: 'formData',
            description:
              'Load a specific template for the analysis of Xliff files and for filters extraction parameters',
            required: false,
            type: 'string',
            example: '3213',
          },
          {
            name: 'id_qa_model',
            in: 'formData',
            description: 'Load a specific QA framework (from the ID)',
            required: false,
            type: 'string',
            example: '3213',
          },
          {
            name: 'id_qa_model_template',
            in: 'formData',
            description:
              'Load a previously saved, custom QA framework template',
            required: false,
            type: 'string',
            example: '3213',
          },
          {
            name: 'metadata',
            in: 'formData',
            description:
              'Metadata for the project must be sent in JSON format Key:Value es: {"key1":"value1", "key2":"value2"}',
            required: false,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'The metadata for the created project.',
            schema: {
              $ref: '#/definitions/NewProject',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/status': {
      get: {
        deprecated: true,
        tags: ['Project'],
        summary: 'Retrieve the status of a project',
        description:
          'Check Status of a created Project With HTTP POST ( application/x-www-form-urlencoded ) protocol',
        parameters: [
          {
            name: 'id_project',
            in: 'query',
            description:
              'The identifier of the project, should be the value returned by the /new method.',
            required: true,
            type: 'integer',
          },
          {
            name: 'project_pass',
            in: 'query',
            description:
              'The password associated with the project, should be the value returned by the /new method ( associated with the id_project )',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'An array of price estimates by product',
            schema: {
              $ref: '#/definitions/Status',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/change-password': {
      post: {
        tags: ['Project', 'Job'],
      },
      summary: 'Change password',
      description: 'Change the password of a project or a job.',
      parameters: [
        {
          name: 'res',
          in: 'formData',
          description:
            'Possible values: job, prj (if left empy, job is the default value)',
          required: false,
          type: 'string',
        },
        {
          name: 'id',
          in: 'formData',
          description:
            'The id of the resource (project or job) whose password you want to change.',
          required: true,
          type: 'integer',
        },
        {
          name: 'password',
          in: 'formData',
          description:
            'The current password of the resource (project or job) whose password you want to change.',
          required: true,
          type: 'string',
        },
        {
          name: 'new_password',
          in: 'formData',
          description:
            'Use this to define the new password of the resource whose password you are changing. Becomes mandatory if undo is set to "true".',
          required: false,
          type: 'string',
        },
        {
          name: 'revision_number',
          in: 'formData',
          description:
            'Fill this in if you want to change the password of a revision job. Use this field to specify the revision step whose password you are changing. If this field is filled in, the password sent in the "password" field should be the one for the corresponding revision step. Possible values: 1, 2.',
          required: false,
          type: 'integer',
        },
        {
          name: 'undo',
          in: 'formData',
          description:
            'Set this to "true" if you\'d like to define the new password of the resource you are updating, rather than having a random one generated for you.',
          required: false,
          type: 'boolean',
        },
      ],
      responses: {
        200: {
          description: 'An array of price estimates by product',
          schema: {
            $ref: '#/definitions/ChangePasswordResponse',
          },
        },
        default: {
          description: 'Unexpected error',
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/analysis/status': {
      get: {
        tags: ['Project'],
        summary: 'Retrieve the status of a project',
        description: 'Check Status of a created Project With HTTP POST.',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description:
              'The identifier of the project, should be the value returned by the /new method.',
            required: true,
            type: 'integer',
          },
          {
            name: 'password',
            in: 'path',
            description:
              'The password associated with the project, should be the value returned by the /new method ( associated with the id_project )',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'An array of price estimates by product',
            schema: {
              $ref: '#/definitions/Status',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/change-name': {
      post: {
        tags: ['Project'],
        summary: 'Change the name of a project',
        description: 'Change the name of a created Project With HTTP POST.',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'name',
            in: 'formData',
            description: 'The new project name.',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'The id and the new project name',
            schema: {
              type: 'object',
              properties: {
                id: {
                  type: 'integer',
                  example: 123,
                },
                name: {
                  type: 'string',
                  example: 'the_new_project_name',
                },
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/creation_status': {
      get: {
        tags: ['Project'],
        summary: 'Shows creation status of a project',
        description: 'Shows creation status of a project.',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'urls',
            schema: {
              $ref: '#/definitions/ProjectCreationStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/completion_status': {
      get: {
        tags: ['Project'],
        summary: 'Shows project completion statuses',
        description:
          'Shows project completion statuses, ' +
          'it is related to the phases defined by the click on Marked As Completed button.',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'integer',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Statuses',
            schema: {
              $ref: '#/definitions/CompletionStatusItem',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}': {
      get: {
        tags: ['Job'],
        summary: 'Job Info',
        description: 'Get all information about a Job',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Job Information',
            schema: {
              $ref: '#/definitions/Chunk',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/translation/{id_job}/{password}': {
      get: {
        tags: ['Job'],
        summary: 'Download Translation',
        description: 'Download the Job translation',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Job translation',
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/cancel': {
      post: {
        tags: ['Job'],
        summary: 'Cancel API',
        description: 'API to cancel a Job',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/archive': {
      post: {
        tags: ['Job'],
        summary: 'Archive API',
        description: 'API to archive a Job',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/delete': {
      post: {
        tags: ['Job'],
        summary: 'Delete API',
        description: 'API to delete a Job',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/active': {
      post: {
        tags: ['Job'],
        summary: 'Active API',
        description: 'API to active a Job',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/r2': {
      post: {
        tags: ['Project'],
        summary: 'Generate second pass review 2',
        description: 'API to generate a second pass review',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description:
              'The id of the parent project of the job you intend to generate the Revise 2 step for',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description:
              'The password of parent project of the job you intend to generate the Revise 2 step for',
            required: true,
            type: 'string',
          },
          {
            name: 'id_job',
            in: 'formData',
            description:
              'The id of the job you intend to generate the Revise 2 step for',
            required: false,
            type: 'string',
          },
          {
            name: 'password',
            in: 'formData',
            description:
              'The password of the job you intend to generate the Revise 2 step for',
            required: false,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'CreateReview',
            schema: {
              $ref: '#/definitions/CreateReview',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/urls': {
      get: {
        tags: ['Project'],
        summary: 'Urls of a Project',
        description: 'Urls of a Project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'urls',
            schema: {
              $ref: '#/definitions/Urls',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },

    '/api/v3/projects/{id_project}/{password}/due_date': {
      post: {
        tags: ['Project'],
        summary: 'Create due date',
        description: 'Create due date given a project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'due_date',
            in: 'formData',
            description:
              'Date you want to set as due date. Date must be in the future',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'Project',
            schema: {
              $ref: '#/definitions/Project',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      put: {
        tags: ['Project'],
        consumes: ['application/json'],
        summary: 'Update due date',
        description: 'Update due date given a project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'body',
            in: 'body',
            description:
              'Date you want to set as due date. Date must be in the future',
            required: true,
            schema: {
              type: 'object',
              required: ['due_date'],
              properties: {
                due_date: {type: 'integer'},
              },
            },
          },
        ],
        responses: {
          200: {
            description: 'Project',
            schema: {
              $ref: '#/definitions/Project',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['Project'],
        summary: 'Delete due date',
        description: 'Delete due date given a project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Project',
            schema: {
              $ref: '#/definitions/Project',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/cancel': {
      post: {
        tags: ['Project'],
        summary: 'Cancel API',
        description: 'API to cancel a Project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/delete': {
      post: {
        tags: ['Project'],
        summary: 'Delete API',
        description: 'API to delete a Project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/archive': {
      post: {
        tags: ['Project'],
        summary: 'Archive API',
        description: 'API to archive a Project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/active': {
      post: {
        tags: ['Project'],
        summary: 'Active API',
        description: 'API to active a Project',
        parameters: [
          {
            name: 'id_project',
            in: 'path',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ChangeStatus',
            schema: {
              $ref: '#/definitions/ChangeStatus',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/jobs/{id_job}/merge': {
      post: {
        tags: ['Project'],
        summary: 'Merge',
        description: 'Merge a splitted project',
        parameters: [
          {
            name: 'id_project',
            in: 'formData',
            description: 'The id of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'formData',
            description: 'The password of the project',
            required: true,
            type: 'string',
          },
          {
            name: 'id_job',
            in: 'formData',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'urls',
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/projects/{id_project}/{password}/jobs/{id_job}/{job_password}/split/{num_split}/check':
      {
        post: {
          tags: ['Project'],
          summary: 'Split Check',
          description: 'Check a job can be splitted',
          parameters: [
            {
              name: 'id_project',
              in: 'path',
              description: 'The id of the project',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'path',
              description: 'The password of the project',
              required: true,
              type: 'string',
            },
            {
              name: 'id_job',
              in: 'path',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'job_password',
              in: 'path',
              description: 'The password of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'num_split',
              in: 'path',
              description: 'Number of chuck you want to split',
              required: true,
              type: 'integer',
            },
            {
              name: 'split_values',
              in: 'formData',
              description:
                'Number of word count values of each chunk returned in split check API',
              type: 'array',
              items: {type: 'double'},
            },
            {
              name: 'split_raw_words',
              in: 'formData',
              description:
                'Split the job by raw words instead of equivalent words',
              type: 'boolean',
            },
          ],
          responses: {
            200: {
              description: 'Split',
              schema: {
                $ref: '#/definitions/Split',
              },
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
      },
    '/api/v3/projects/{id_project}/{password}/jobs/{id_job}/{job_password}/split/{num_split}/apply':
      {
        post: {
          tags: ['Project'],
          summary: 'Split Job',
          description: 'Check a job can be splitted',
          parameters: [
            {
              name: 'id_project',
              in: 'path',
              description: 'The id of the project',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'path',
              description: 'The password of the project',
              required: true,
              type: 'string',
            },
            {
              name: 'id_job',
              in: 'path',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'job_password',
              in: 'path',
              description: 'The password of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'num_split',
              in: 'path',
              description: 'Number of chuck you want to split',
              required: true,
              type: 'integer',
            },
            {
              name: 'split_values',
              in: 'formData',
              description:
                'Number of word count values of each chunk returned in split check API',
              type: 'array',
              items: {type: 'double'},
            },
            {
              name: 'split_raw_words',
              in: 'formData',
              description:
                'Split the job by raw words instead of equivalent words',
              type: 'boolean',
            },
          ],
          responses: {
            200: {
              description: 'Split',
              schema: {
                $ref: '#/definitions/Split',
              },
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
      },
    '/api/v3/jobs/{id_job}/{password}/translator': {
      get: {
        tags: ['Job'],
        summary: 'Gets the translator assigned to a job',
        description: 'Gets the translator assigned to a job.',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Job',
            schema: {
              $ref: '#/definitions/JobTranslatorItem',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Job'],
        summary: 'Assigns a job to a translator',
        description: 'Assigns a job to a translator.',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'email',
            in: 'formData',
            description: 'email of the translator to assign the job',
            required: true,
            type: 'string',
          },
          {
            name: 'delivery_date',
            in: 'formData',
            description:
              'deliery date for the assignment, expressed as timestamp',
            required: true,
            type: 'integer',
          },
          {
            name: 'timezone',
            in: 'formData',
            description:
              'time zone to convert the delivery_date param expressed as offset based on UTC. Example 1.0, -7.0 etc.',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Job',
            schema: {
              $ref: '#/definitions/JobTranslatorItem',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/comments': {
      get: {
        tags: ['Job'],
        summary: 'Get segment comments in a job',
        description: 'Gets the list of comments on all job segments.',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'from_id',
            in: 'query',
            description: 'Only return records starting from this id included',
            required: false,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'Comments',
            schema: {
              $ref: '#/definitions/Comments',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/feedback': {
      post: {
        tags: ['Job'],
        summary: 'Create a revision feedback',
        description: 'Create a revision feedback',
        parameters: [
          {
            name: 'id_job',
            in: 'formData',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'formData',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'revision_number',
            in: 'formData',
            description: 'The revision number (1 or 2)',
            required: true,
            type: 'string',
          },
          {
            name: 'feedback',
            in: 'formData',
            description: 'The feedback',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'ok',
            schema: {
              type: 'object',
              properties: {
                status: {
                  type: 'string',
                  example: 'ok',
                },
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/qr/download': {
      post: {
        tags: ['Quality Report'],
        summary: 'Download the quality report',
        description: 'Download the quality report',
        parameters: [
          {
            name: 'idJob',
            in: 'formData',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'formData',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'format',
            in: 'formData',
            description: 'The QR format (csv or json)',
            required: true,
            type: 'string',
          },
          {
            name: 'segmentsPerFile',
            in: 'formData',
            description: 'The number of segments per file (max 100)',
            required: true,
            type: 'integer',
          },
        ],
        produces: ['text/csv', 'application/json'],
        responses: {
          200: {
            description: 'ok',
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/quality-report': {
      get: {
        tags: ['Job', 'Quality Report'],
        summary: 'Quality report',
        description: 'Quality report',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Quality report',
            schema: {
              $ref: '#/definitions/QualityReport',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/quality-report/segments': {
      get: {
        tags: ['Job', 'Quality Report'],
        summary: 'Quality report segments',
        description: 'API for fetching segments for quality report',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Quality report segments',
            schema: {
              $ref: '#/definitions/QualityReportSegments',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/teams': {
      get: {
        tags: ['Teams'],
        summary: 'List available teams',
        description:
          'Returns a list of all teams the current user is member of.',
        parameters: [],
        responses: {
          200: {
            description: 'Teams',
            schema: {
              $ref: '#/definitions/TeamList',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Teams'],
        summary: 'Create a new team',
        description: 'Creates a new team.',
        parameters: [
          {
            name: 'type',
            type: 'string',
            in: 'fromData',
            required: true,
          },
          {
            name: 'name',
            type: 'string',
            in: 'fromData',
            required: true,
          },
          {
            name: 'members',
            type: 'array',
            in: 'fromData',
            items: {
              type: 'string',
              format: 'email',
              collectionFormat: 'multi',
            },
            description:
              'Array of email addresses of people to invite in a project',
            required: true,
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/TeamItem',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/teams/{id_team}': {
      put: {
        tags: ['Teams'],
        summary: 'Update team',
        description: 'Update team.',
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
          {
            name: 'body',
            in: 'body',
            description: 'Parameters in JSON Body',
            required: true,
            schema: {
              type: 'object',
              properties: {
                name: {type: 'string'},
              },
            },
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/TeamItem',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/teams/{id_team}/members': {
      get: {
        tags: ['Teams'],
        summary: 'List team members',
        description: 'List team members.',
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/TeamMembersList',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Teams'],
        summary: 'Create new team memberships',
        description: 'Create new team memberships.',
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
          {
            name: 'members',
            type: 'array',
            in: 'fromData',
            items: {
              type: 'string',
              format: 'email',
              collectionFormat: 'multi',
            },
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/TeamMembersList',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/teams/{id_team}/members/{id_member}': {
      delete: {
        tags: ['Teams'],
        summary: 'List team members',
        description: 'List team members.',
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
          {
            name: 'id_member',
            type: 'integer',
            in: 'path',
            required: true,
            description: 'Id of the user to remove from team',
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/TeamMembersList',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/teams/{id_team}/projects': {
      get: {
        tags: ['Teams'],
        summary: 'Get the list of projects in a team',
        description: 'Get the list of projects in a team.',
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/ProjectList',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/teams/{id_team}/projects/{id_project}': {
      get: {
        tags: ['Teams'],
        summary: 'Get a project in a team scope',
        description: 'Get a project in a team scope.',
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
          {
            name: 'id_project',
            type: 'integer',
            in: 'path',
            required: true,
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/ProjectItem',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      put: {
        tags: ['Teams'],
        summary: "Update a team's project",
        description: "Updates a team's project.",
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
          {
            name: 'id_project',
            type: 'integer',
            in: 'path',
            required: true,
          },
          {
            name: 'body',
            in: 'body',
            description: 'Parameters in JSON Body',
            required: true,
            schema: {
              type: 'object',
              properties: {
                id_assignee: {type: 'integer'},
                id_team: {type: 'integer'},
                name: {type: 'string'},
              },
            },
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/ProjectItem',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/teams/{id_team}/projects/{project_name}': {
      get: {
        tags: ['Teams'],
        summary: 'Get projects in a team scope',
        description: 'Get projects in a team scope by name.',
        parameters: [
          {
            name: 'id_team',
            type: 'integer',
            in: 'path',
            required: true,
          },
          {
            name: 'project_name',
            type: 'string',
            in: 'path',
            required: true,
            description: 'The name can also be a part of a project name',
          },
        ],
        responses: {
          200: {
            description: 'Team',
            schema: {
              $ref: '#/definitions/ProjectsItems',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/translation-issues': {
      get: {
        tags: ['Job', 'Translation Issues'],
        summary: 'Project translation issues',
        description: 'Project translation issues',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Translation issues',
            schema: {
              $ref: '#/definitions/TranslationIssues',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/{source_page}/issue-report/segments': {
      get: {
        tags: ['Job', 'Segment issues'],
        summary: 'Segment issues',
        description: 'Segment issues',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
          {
            name: 'source_page',
            in: 'path',
            description:
              'The source page (possible values: 2 for R1, 3 for R2)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Segment issues report',
            schema: {
              $ref: '#/definitions/SegmentIssueReport',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/translation-versions': {
      get: {
        tags: ['Job', 'Translation Versions'],
        summary: 'Project translation versions',
        description: 'Project translation versions',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Translation Versions',
            schema: {
              $ref: '#/definitions/TranslationVersions',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/segments/{id_segment}/translation-versions':
      {
        get: {
          tags: ['Job', 'Translation Versions'],
          summary: 'Segment versions',
          description: 'Segment versions',
          parameters: [
            {
              name: 'id_job',
              in: 'path',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'path',
              description: 'The password of the job (Translate password)',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'path',
              description: 'The id of the segment',
              required: true,
              type: 'string',
            },
          ],
          responses: {
            200: {
              description: 'Segment versions',
              schema: {
                $ref: '#/definitions/TranslationVersions',
              },
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
      },
    '/api/v3/jobs/{id_job}/{password}/segments/{id_segment}/translation-versions/{version_number}':
      {
        get: {
          tags: ['Job', 'Translation Versions'],
          summary: 'Get a Segment translation version',
          description: 'Get a Segment translation version',
          parameters: [
            {
              name: 'id_job',
              in: 'path',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'path',
              description: 'The password of the job (Translate password)',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'path',
              description: 'The id of the segment',
              required: true,
              type: 'string',
            },
            {
              name: 'version_number',
              in: 'path',
              description: 'The version number',
              required: true,
              type: 'string',
            },
          ],
          responses: {
            200: {
              description: 'Segment version',
              schema: {
                $ref: '#/definitions/TranslationVersion',
              },
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
      },
    '/api/v3/jobs/{id_job}/{password}/segments/{id_segment}/translation-issues':
      {
        post: {
          tags: ['Job', 'Translation Issues'],
          summary: 'Create translation issues',
          description: 'Create translation issues',
          parameters: [
            {
              name: 'id_job',
              in: 'formData',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'formData',
              description: 'The password of the job (Translate password)',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'formData',
              description: 'The id of the segment',
              required: true,
              type: 'string',
            },
            {
              name: 'version_number',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'id_job',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'id_category',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'severity',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'translation_version',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'target_text',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'start_node',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'start_offset',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'end_node',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'end_offset',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'is_full_segment',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'comment',
              in: 'formData',
              required: true,
              type: 'string',
            },
          ],
          responses: {
            200: {
              description: 'Segment version',
              schema: {
                $ref: '#/definitions/Issue',
              },
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
      },
    '/api/v3/jobs/{id_job}/{password}/segments/{id_segment}/translation-issues/{id_issue}':
      {
        post: {
          tags: ['Job', 'Translation Issues'],
          summary: 'Update translation issues',
          description: 'Update translation issues',
          parameters: [
            {
              name: 'id_job',
              in: 'formData',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'formData',
              description: 'The password of the job (Translate password)',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'formData',
              description: 'The id of the segment',
              required: true,
              type: 'string',
            },
            {
              name: 'id_issue',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'rebutted_at',
              in: 'formData',
              required: true,
              type: 'string',
            },
          ],
          responses: {
            200: {
              description: 'Update Translation issue',
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
        delete: {
          tags: ['Job', 'Translation Issues'],
          summary: 'Delete a translation Issue',
          description: 'Delete a translation Issue',
          parameters: [
            {
              name: 'id_job',
              in: 'path',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'path',
              description: 'The password of the job (Translate password)',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'path',
              description: 'The id of the segment',
              required: true,
              type: 'string',
            },
            {
              name: 'id_issue',
              in: 'path',
              description: 'The id of the issue',
              required: true,
              type: 'string',
            },
          ],
          responses: {
            200: {
              description: 'Delete',
              schema: {
                $ref: '#/definitions/Issue',
              },
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
      },
    '/api/v3/jobs/{id_job}/{password}/segments/{id_segment}/translation-issues/{id_issue}/comments':
      {
        post: {
          tags: ['Job', 'Translation Issues'],
          summary: 'Add comment to a translation issue',
          description: 'Create a comment translation issue',
          parameters: [
            {
              name: 'id_job',
              in: 'formData',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'formData',
              description: 'The password of the job (Translate password)',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'formData',
              description: 'The id of the segment',
              required: true,
              type: 'string',
            },
            {
              name: 'id_issue',
              in: 'formData',
              description: 'The id of the issue',
              required: true,
              type: 'string',
            },
            {
              name: 'comment',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'id_qa_entry',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'source_page',
              in: 'formData',
              required: true,
              type: 'string',
            },
            {
              name: 'uid',
              in: 'formData',
              required: true,
              type: 'string',
            },
          ],
          responses: {
            200: {
              description: 'Add comment',
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
        get: {
          tags: ['Job', 'Translation Issues'],
          summary: 'Get comments',
          description: 'Get comments',
          parameters: [
            {
              name: 'id_job',
              in: 'path',
              description: 'The id of the job',
              required: true,
              type: 'string',
            },
            {
              name: 'password',
              in: 'path',
              description: 'The password of the job (Translate password)',
              required: true,
              type: 'string',
            },
            {
              name: 'id_segment',
              in: 'path',
              description: 'The id of the segment',
              required: true,
              type: 'string',
            },
            {
              name: 'id_issue',
              in: 'path',
              description: 'The id of the issue',
              required: true,
              type: 'string',
            },
          ],
          responses: {
            200: {
              description: 'Get comments',
            },
            default: {
              description: 'Unexpected error',
            },
          },
        },
      },
    '/api/v3/jobs/{id_job}/{password}/options': {
      post: {
        tags: ['Job', 'Options'],
        summary: 'Update Options',
        description: 'Update Options (speech2text, guess tags, lexiqa)',
        parameters: [
          {
            name: 'id_job',
            in: 'formData',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'formData',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
          {
            name: 'speech2text',
            in: 'formData',
            description: 'To enable Speech To Text option',
            required: false,
            type: 'boolean',
          },
          {
            name: 'tag_projection',
            in: 'formData',
            description: 'To enable Guess Tags option',
            type: 'boolean',
            required: false,
          },
          {
            name: 'lexiqa',
            in: 'formData',
            description: 'To enable lexiqa option',
            type: 'boolean',
            required: false,
          },
        ],
        responses: {
          200: {
            description: 'Update Options',
            schema: {
              $ref: '#/definitions/Options',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/files': {
      get: {
        tags: ['Job', 'Files'],
        summary: 'Job files',
        description: 'Get job files information',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Get job files information',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/QualityReportFile',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/xliff/{id_job}/{password}/{id_job}.zip': {
      get: {
        tags: ['Job', 'Files'],
        summary: 'Download the xliff file(s)',
        description: 'Download the job xliff file(s) in zip format',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
          {
            name: 'id_file',
            in: 'path',
            description: 'The ID of the job file',
            required: true,
            type: 'string',
          },
        ],
        produces: [
          'application/zip'
        ],
        responses: {
          200: {
            description: 'OK',
          },
          default: {
            description: 'Unexpected error',
          },
        }
      }
    },
    '/api/v3/jobs/{id_job}/{password}/file/{id_file}/instructions': {
      get: {
        tags: ['Job', 'Files'],
        summary: 'Job instructions',
        description: 'Get job instructions',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
          {
            name: 'id_file',
            in: 'path',
            description: 'The ID of the job file',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Get job instructions',
            type: 'object',
            properties: {
              instructions: {
                type: 'string',
                example: 'Write some instructions for this file',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Job', 'Files'],
        summary: 'Job instructions',
        description: 'Insert/update job instructions for a specific job file',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
          {
            name: 'id_file',
            in: 'path',
            description: 'The ID of the job file',
            required: true,
            type: 'string',
          },
          {
            name: 'instructions',
            in: 'body',
            description: 'The instructions for this file',
            required: true,
            type: 'string',
            example: 'Write some instructions for this file',
          },
        ],
        responses: {
          200: {
            description:
              'Insert/update job instructions for a specific job file',
            schema: {
              type: 'boolean',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/jobs/{id_job}/{password}/metadata': {
      get: {
        tags: ['Job'],
        summary: 'Job metadata',
        description: 'Get all job metadata',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Get all job metadata',
            schema: {
              $ref: '#/definitions/JobMetadata',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/TMX/{id_job}/{password}': {
      get: {
        tags: ['Job'],
        summary: 'Download Job TMX',
        description: 'Download the Job TMX ',
        parameters: [
          {
            name: 'id_job',
            in: 'path',
            description: 'The id of the job',
            required: true,
            type: 'string',
          },
          {
            name: 'password',
            in: 'path',
            description: 'The password of the job (Translate password)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Job TMX',
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/glossaries/check/': {
      post: {
        tags: ['Glossary'],
        summary: 'Check Glossary',
        description: 'Check if a glossary file (.xlsx) is valid or not',
        parameters: [
          {
            name: 'files',
            in: 'formData',
            description: 'The file(s) to be uploaded',
            required: true,
            type: 'file',
          },
          {
            name: 'name',
            in: 'formData',
            description: 'The file name.',
            type: 'string',
            required: false,
          },
          {
            name: 'tm_key',
            in: 'formData',
            description: 'The tm key.',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Check Glossary',
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/glossaries/import/': {
      post: {
        tags: ['Glossary'],
        summary: 'Import Glossary',
        description:
          '### Import glossary file (.xlsx) \n' +
          'If your glossary has **between two and ten locales in it**, you will be able to use it for jobs **in any of the possible combinations of the locales** (e.g. if you have en-US, es-ES and it-IT in a glossary you will be able to use it for en-US <> es-ES, en-US <> it-IT and es-ES <> it-IT jobs).\n' +
          'If your glossary has **more than 10 locales**, Matecat will only create combinations between **the first locale column from the left and the rest of the locales** (e.g. if in a glossary you have en-US as the first locale from the left, es-ES, it-IT and 10 more locales, you will be able to use it for en-US <> es-ES and en-US <> it-IT, but not for es-ES <> it-IT).',
        parameters: [
          {
            name: 'files',
            in: 'formData',
            description: 'The file(s) to be uploaded',
            required: true,
            type: 'file',
          },
          {
            name: 'name',
            in: 'formData',
            description: 'The file name.',
            type: 'string',
            required: false,
          },
          {
            name: 'tm_key',
            in: 'formData',
            description: 'The tm key.',
            required: false,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Import Glossary',
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/glossaries/import/status/{tm_key}': {
      get: {
        summary: 'Glossary Upload status.',
        description: 'Glossary Upload status.',
        parameters: [
          {
            name: 'tm_key',
            in: 'path',
            description: 'The tm key.',
            required: true,
            type: 'string',
          },
          {
            name: 'name',
            in: 'query',
            description: 'The file name.',
            type: 'string',
          },
        ],
        tags: ['Glossary'],
        responses: {
          200: {
            description: 'Glossary Upload status',
            schema: {
              $ref: '#/definitions/UploadGlossaryStatusObject',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/glossaries/export/': {
      post: {
        tags: ['Glossary'],
        summary: 'Download Glossary',
        description: 'download Glossary',
        parameters: [
          {
            name: 'tm_key',
            in: 'body',
            description: 'The tm key.',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Glossary',
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/engines/list': {
      get: {
        tags: ['Engines'],
        summary: 'Retrieve personal engine list.',
        description:
          'Retrieve personal engine list ( Google, Microsoft, etc. ).',
        parameters: [],
        responses: {
          200: {
            description: 'Engine List',
            schema: {
              $ref: '#/definitions/EnginesList',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/create-key': {
      post: {
        tags: ['TM keys'],
        summary: 'Create a TM key.',
        description: 'Create a TM key.',
        consumes: ['application/json'],
        parameters: [
          {
            in: 'body',
            schema: {
              type: 'object',
              properties: {
                key: {
                  type: 'string',
                  example: '1234_xxxx',
                },
                name: {
                  type: 'string',
                  example: 'My new key',
                },
              },
            },
          },
        ],
        responses: {
          200: {
            description: 'The new key created',
            schema: {
              type: 'object',
              properties: {
                key: {
                  type: 'string',
                  example: '1234_xxxx',
                },
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/keys/list': {
      get: {
        tags: ['TM keys'],
        summary: 'Retrieve private TM keys list.',
        description: 'Retrieve private TM keys list.',
        parameters: [],
        responses: {
          200: {
            description: 'Keys List',
            schema: {
              $ref: '#/definitions/KeysList',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/tm-keys/list': {
      get: {
        tags: ['TM keys'],
        summary: 'Retrieve private TM keys list.',
        description: 'Retrieve private TM keys list.',
        parameters: [],
        responses: {
          200: {
            description: 'Keys List',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/KeysListComplete',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/languages': {
      get: {
        tags: ['Languages'],
        summary: 'Supported languages list.',
        description: 'List of supported languages.',
        parameters: [],
        responses: {
          200: {
            description: 'Languages List',
            schema: {
              $ref: '#/definitions/Languages',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/files': {
      get: {
        tags: ['Files'],
        summary: 'Supported file types list.',
        description: 'List of supported file types.',
        parameters: [],
        responses: {
          200: {
            description: 'File types List',
            schema: {
              $ref: '#/definitions/Files',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },

    '/api/v3/project-template': {
      get: {
        tags: ['Project templates'],
        summary:
          'Shows the list of project template models available for the currents user',
        description:
          'Shows the list of project template models available for the currents user',
        responses: {
          200: {
            description: 'An array of JSON representation models.',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/ProjectTemplateSchema',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Project templates'],
        summary: 'Creates a new project template model',
        description: 'Creates a new project template model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/ProjectTemplateSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'create',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/project-template/{id}': {
      get: {
        tags: ['Project templates'],
        summary: 'Shows a particular project template model',
        description: 'Shows a particular project template model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'The model JSON representation.',
            schema: {
              $ref: '#/definitions/ProjectTemplateSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['Project templates'],
        summary: 'Deletes a particular project template model',
        description: 'Deletes a particular project template model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'delete',
            examples: {
              'application/json': {
                id: 3,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      put: {
        tags: ['Project templates'],
        summary: 'Updates a particular project template model',
        description: 'Updates a particular project template model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/ProjectTemplateSchema',
            },
          },
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'update',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/project-template/validate': {
      post: {
        tags: ['Project templates'],
        summary: 'Validates a project template before creation',
        description: 'Validates a project template before creation',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/ProjectTemplateSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'validate',
            examples: {
              'application/json': {
                errors: [],
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/project-template/schema': {
      get: {
        tags: ['Project templates'],
        summary: 'Shows the project template model creation schema',
        description: 'Shows the project template model creation schema',
        parameters: [],
        responses: {
          200: {
            description: 'schema',
            schema: {
              $ref: '#/definitions/ProjectTemplateSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/payable_rate': {
      get: {
        tags: ['Billing models'],
        summary:
          'Shows the list of billing models available for the currents user',
        description:
          'Shows the list of billing models available for the currents user',
        responses: {
          200: {
            description: 'An array of JSON representation models.',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/PayableRateSchema',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Billing models'],
        summary: 'Creates a new billing model',
        description: 'Creates a new billing model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/PayableRateSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'create',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/payable_rate/{id}': {
      get: {
        tags: ['Billing models'],
        summary: 'Shows a particular billing model',
        description: 'Shows a particular billing model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'The model JSON representation.',
            schema: {
              $ref: '#/definitions/PayableRateSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['Billing models'],
        summary: 'Deletes a particular billing model',
        description: 'Deletes a particular billing model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'delete',
            examples: {
              'application/json': {
                id: 3,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      put: {
        tags: ['Billing models'],
        summary: 'Updates a particular billing model',
        description: 'Updates a particular billing model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/PayableRateSchema',
            },
          },
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'update',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/payable_rate/validate': {
      post: {
        tags: ['Billing models'],
        summary: 'Validates a billing model before creation',
        description: 'Validates a billing model before creation',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/PayableRateSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'validate',
            examples: {
              'application/json': {
                errors: [],
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/payable_rate/schema': {
      get: {
        tags: ['Billing models'],
        summary: 'Shows the billing model creation schema',
        description: 'Shows the billing model creation schema',
        parameters: [],
        responses: {
          200: {
            description: 'schema',
            schema: {
              $ref: '#/definitions/PayableRateSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/qa_model_template': {
      get: {
        tags: ['Quality models'],
        summary:
          'Shows the list of quality models available for the currents user',
        description:
          'Shows the list of quality models available for the currents user',
        responses: {
          200: {
            description: 'An array of JSON representation models.',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/QualityModelSchema',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Quality models'],
        summary: 'Creates a new quality model',
        description: 'Creates a new quality model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/QualityModelSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'create',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/qa_model_template/{id}': {
      get: {
        tags: ['Quality models'],
        summary: 'Shows a particular quality model',
        description: 'Shows a particular quality model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'The model JSON representation.',
            schema: {
              $ref: '#/definitions/QualityModelSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['Quality models'],
        summary: 'Deletes a particular quality model',
        description: 'Deletes a particular quality model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'delete',
            examples: {
              'application/json': {
                id: 3,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      put: {
        tags: ['Quality models'],
        summary: 'Updates a particular quality model',
        description: 'Updates a particular quality model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/QualityModelSchema',
            },
          },
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'update',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/qa_model_template/validate': {
      post: {
        tags: ['Quality models'],
        summary: 'Validates a quality model before creation',
        description: 'Validates a quality model before creation',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/QualityModelSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'validate',
            examples: {
              'application/json': {
                errors: [],
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/qa_model_template/schema': {
      get: {
        tags: ['Quality models'],
        summary: 'Shows the quality model creation schema',
        description: 'Shows the quality model creation schema',
        parameters: [],
        responses: {
          200: {
            description: 'schema',
            schema: {
              $ref: '#/definitions/QualityModelSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/filters-config-template': {
      get: {
        tags: ['Filters analysis configuration'],
        summary:
          'Shows the list of filters analysis configuration models available for the currents user',
        description:
          'Shows the list of filters analysis configuration models available for the currents user',
        responses: {
          200: {
            description: 'An array of JSON representation models.',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/FiltersConfigSchema',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Filters analysis configuration'],
        summary: 'Creates a new filters analysis configuration model',
        description: 'Creates a new filters analysis configuration model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/FiltersConfigSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'create',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/filters-config-template/{id}': {
      get: {
        tags: ['Filters analysis configuration'],
        summary: 'Shows a particular filters analysis configuration model',
        description: 'Shows a particular filters analysis configuration model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'The model JSON representation.',
            schema: {
              $ref: '#/definitions/FiltersConfigSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['Filters analysis configuration'],
        summary: 'Deletes a particular filters analysis configuration model',
        description:
          'Deletes a particular filters analysis configuration model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'delete',
            examples: {
              'application/json': {
                id: 3,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      put: {
        tags: ['Filters analysis configuration'],
        summary: 'Updates a particular filters analysis configuration model',
        description:
          'Updates a particular filters analysis configuration model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/FiltersConfigSchema',
            },
          },
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'update',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/filters-config-template/validate': {
      post: {
        tags: ['Filters analysis configuration'],
        summary:
          'Validates a filters analysis configuration model before creation',
        description:
          'Validates a filters analysis configuration model before creation',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/FiltersConfigSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'validate',
            examples: {
              'application/json': {
                errors: [],
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/filters-config-template/schema': {
      get: {
        tags: ['Filters analysis configuration'],
        summary:
          'Shows the filters analysis configuration model creation schema',
        description:
          'Shows the filters analysis configuration model creation schema',
        parameters: [],
        responses: {
          200: {
            description: 'schema',
            schema: {
              $ref: '#/definitions/FiltersConfigSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/xliff-config-template': {
      get: {
        tags: ['Xliff analysis configuration'],
        summary:
          'Shows the list of xliff analysis configuration models available for the currents user',
        description:
          'Shows the list of xliff analysis configuration models available for the currents user',
        responses: {
          200: {
            description: 'An array of JSON representation models.',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/XliffConfigSchema',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['Xliff analysis configuration'],
        summary: 'Creates a new xliff analysis configuration model',
        description: 'Creates a new xliff analysis configuration model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/XliffConfigSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'create',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/xliff-config-template/{id}': {
      get: {
        tags: ['Xliff analysis configuration'],
        summary: 'Shows a particular xliff analysis configuration model',
        description: 'Shows a particular xliff analysis configuration model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'The model JSON representation.',
            schema: {
              $ref: '#/definitions/XliffConfigSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['Xliff analysis configuration'],
        summary: 'Deletes a particular xliff analysis configuration model',
        description: 'Deletes a particular xliff analysis configuration model',
        parameters: [
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'delete',
            examples: {
              'application/json': {
                id: 3,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      put: {
        tags: ['Xliff analysis configuration'],
        summary: 'Updates a particular xliff analysis configuration model',
        description: 'Updates a particular xliff analysis configuration model',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/XliffConfigSchema',
            },
          },
          {
            name: 'id',
            in: 'path',
            description: 'The model ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'update',
            examples: {
              'application/json': {
                id: 4,
                version: 1,
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/xliff-config-template/validate': {
      post: {
        tags: ['Xliff analysis configuration'],
        summary:
          'Validates a xliff analysis configuration model before creation',
        description:
          'Validates a xliff analysis configuration model before creation',
        parameters: [
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/XliffConfigSchema',
            },
          },
        ],
        responses: {
          200: {
            description: 'validate',
            examples: {
              'application/json': {
                errors: [],
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/xliff-config-template/schema': {
      get: {
        tags: ['Xliff analysis configuration'],
        summary: 'Shows the xliff analysis configuration model creation schema',
        description:
          'Shows the xliff analysis configuration model creation schema',
        parameters: [],
        responses: {
          200: {
            description: 'schema',
            schema: {
              $ref: '#/definitions/XliffConfigSchema',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },

    '/api/v3/deepl/{engineId}/glossaries': {
      get: {
        tags: ['DeepL'],
        summary: 'Get all DeepL engines',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'An array of JSON representation models.',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/DeepLGlossary',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      post: {
        tags: ['DeepL'],
        summary: 'Create new DeepL engine',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            in: 'body',
            schema: {
              $ref: '#/definitions/DeepLGlossary',
            },
          },
        ],
        responses: {
          200: {
            description: 'The JSON representation model.',
            schema: {
              $ref: '#/definitions/DeepLGlossary',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/deepl/{engineId}/glossaries/{id}': {
      get: {
        tags: ['DeepL'],
        summary: 'Get a specific DeepL glossary item',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'id',
            in: 'path',
            description: 'The glossary ID',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'The JSON representation model.',
            schema: {
              $ref: '#/definitions/DeepLGlossary',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['DeepL'],
        summary: 'Delete a specific DeepL glossary item',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'id',
            in: 'path',
            description: 'The glossary ID',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'The JSON representation model.',
            schema: {
              type: 'object',
              properties: {
                id: {
                  type: 'integer',
                  readOnly: true,
                },
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/deepl/{engineId}/glossaries/{id}/entries': {
      get: {
        tags: ['DeepL'],
        summary: 'Get a DeepL engine glossary items',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'id',
            in: 'path',
            description: 'The glossary ID',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description:
              'List the entries of a single glossary in the format specified by the Accept header.',
            schema: {
              type: 'string',
              example: 'Hello! Guten Tag!',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },

    '/api/v3/mmt/{engineId}/keys': {
      get: {
        tags: ['Modern MT'],
        summary: 'Get Modern MT keys',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'List the Modern MT keys.',
            schema: {
              type: 'array',
              items: {
                $ref: '#/definitions/MMTKey',
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/mmt/{engineId}/import-status/{uuid}': {
      get: {
        tags: ['Modern MT'],
        summary: 'Returns the status of an Import Job.',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'Returns the JSON representation of an Import Job.',
            schema: {
              $ref: '#/definitions/MMTGlossary',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/mmt/{engineId}/memory/create': {
      post: {
        tags: ['Modern MT'],
        summary: 'Create a new memory on Modern MT.',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            in: 'body',
            schema: {
              type: 'object',
              properties: {
                name: {
                  type: 'string',
                },
                description: {
                  type: 'string',
                },
                external_id: {
                  type: 'string',
                },
              },
            },
          },
        ],
        responses: {
          200: {
            description: 'Returns a JSON representation of the created memory.',
            schema: {
              $ref: '#/definitions/MMTKey',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/mmt/{engineId}/memory/update/{memoryId}': {
      post: {
        tags: ['Modern MT'],
        summary: 'Update Modern MT memory metadata',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'memoryId',
            in: 'path',
            description: 'The memory ID',
            required: true,
            type: 'integer',
          },
          {
            in: 'body',
            schema: {
              type: 'object',
              properties: {
                name: {
                  type: 'string',
                },
                description: {
                  type: 'string',
                },
              },
            },
          },
        ],
        responses: {
          200: {
            description: 'Returns a JSON representation of the created memory.',
            schema: {
              $ref: '#/definitions/MMTMemory',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
      delete: {
        tags: ['Modern MT'],
        summary: 'Delete a Modern MT memory',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'memoryId',
            in: 'path',
            description: 'The memory ID',
            required: true,
            type: 'integer',
          },
        ],
        responses: {
          200: {
            description: 'Returns a JSON representation of the created memory.',
            schema: {
              $ref: '#/definitions/MMTMemory',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/mmt/{engineId}/glossary/create-memory-and-import': {
      post: {
        tags: ['Modern MT'],
        summary: 'Store Modern MT glossary content',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'csv',
            in: 'formData',
            description: 'The CSV file',
            required: true,
            type: 'file',
          },
        ],
        responses: {
          200: {
            description:
              'Returns a JSON representation of the created glossary.',
            schema: {
              $ref: '#/definitions/MMTGlossary',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/mmt/{engineId}/glossary/import-status/{uuid}': {
      get: {
        tags: ['Modern MT'],
        summary: 'Get a Modern MT glossary items',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'uuid',
            in: 'path',
            description: 'The glossary uuid',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Returns a JSON representation of the glossary.',
            schema: {
              $ref: '#/definitions/MMTGlossary',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/mmt/{engineId}/glossary/import': {
      post: {
        tags: ['Modern MT'],
        summary: 'Get a DeepL engine glossary items',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'memoryId',
            in: 'formData',
            description: 'The memory id',
            required: true,
            type: 'integer',
          },
          {
            name: 'csv',
            in: 'formData',
            description: 'The CSV file',
            required: true,
            type: 'file',
          },
        ],
        responses: {
          200: {
            description: 'Returns a JSON representation of the glossary.',
            schema: {
              $ref: '#/definitions/MMTGlossary',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
    '/api/v3/mmt/{engineId}/glossary/modify': {
      post: {
        tags: ['Modern MT'],
        summary: 'Get a DeepL engine glossary items',
        description: '',
        parameters: [
          {
            name: 'engineId',
            in: 'path',
            description: 'The engine ID',
            required: true,
            type: 'integer',
          },
          {
            name: 'memoryId',
            in: 'body',
            description: 'The memory id',
            required: true,
            type: 'integer',
          },
          {
            name: 'tuid',
            in: 'body',
            description:
              'The tuid of the glossary entry to be updated. forbidden if type is unidirectional, required if type is equivalent',
            required: true,
            type: 'integer',
          },
          {
            name: 'terms',
            in: 'body',
            description: 'The glossary terms to be updated',
            required: true,
            type: 'string',
            example:
              '[{"term": "test", "language": "en"}, {"term": "prova", "language": "it"}, {"term": "prueba", "language": "es"}]',
          },
          {
            name: 'type',
            in: 'body',
            description:
              'The string identifying the glossary type (either unidirectional or equivalent)',
            required: true,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'Returns a JSON representation of the glossary.',
            schema: {
              $ref: '#/definitions/MMTGlossary',
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },

    '/api/v3/word-count/raw': {
      post: {
        tags: ['Word count'],
        summary: 'Create new Project on Matecat in detached mode',
        description: '',
        parameters: [
          {
            name: 'text',
            in: 'formData',
            description: 'The text string',
            required: true,
            type: 'string',
          },
          {
            name: 'language',
            in: 'formData',
            description: 'The language (optional).',
            required: false,
            type: 'string',
          },
        ],
        responses: {
          200: {
            description: 'The word and character count of a text string.',
            schema: {
              type: 'object',
              properties: {
                word_count: {
                  type: 'integer',
                  example: 123,
                },
                character_count: {
                  type: 'integer',
                  example: 123,
                },
              },
            },
          },
          default: {
            description: 'Unexpected error',
          },
        },
      },
    },
  },
  definitions: {
    MMTGlossary: {
      type: 'object',
      properties: {
        id: {
          type: 'string',
        },
        memory: {
          type: 'integer',
        },
        size: {
          type: 'integer',
        },
        progress: {
          type: 'integer',
        },
      },
    },
    MMTKey: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
        },
        name: {
          type: 'string',
        },
        has_glossary: {
          type: 'boolean',
        },
      },
    },
    MMTMemory: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
        },
        creationDate: {
          type: 'string',
          format: 'date-time',
        },
        name: {
          type: 'string',
        },
      },
    },
    DeepLGlossary: {
      type: 'object',
      properties: {
        glossary_id: {
          type: 'integer',
        },
        name: {
          type: 'string',
        },
        ready: {
          type: 'boolean',
        },
        source_lang: {
          type: 'string',
        },
        target_lang: {
          type: 'string',
        },
        creation_time: {
          type: 'string',
          format: 'date-time',
          required: true,
        },
        entry_count: {
          type: 'integer',
        },
      },
    },
    FiltersConfigSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
          readOnly: true,
        },
        uid: {
          type: 'integer',
          readOnly: true,
        },
        name: {
          type: 'string',
        },
        json: {
          type: 'object',
          properties: {
            extract_arrays: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            escape_forward_slashes: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            translate_keys: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
            do_not_translate_keys: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
            context_keys: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
            character_limit: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
          },
          dependencies: {
            translate_keys: {
              not: {
                required: ['do_not_translate_keys'],
              },
            },
            do_not_translate_keys: {
              not: {
                required: ['translate_keys'],
              },
            },
          },
          additionalProperties: false,
        },
        xml: {
          type: 'object',
          properties: {
            preserve_whitespace: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            translate_attributes: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
            translate_elements: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
            do_not_translate_elements: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
          },
          dependencies: {
            translate_elements: {
              not: {
                required: ['do_not_translate_elements'],
              },
            },
            do_not_translate_elements: {
              not: {
                required: ['translate_elements'],
              },
            },
          },
          additionalProperties: false,
        },
        yaml: {
          type: 'object',
          properties: {
            translate_keys: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
            do_not_translate_keys: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
          },
          dependencies: {
            translate_keys: {
              not: {
                required: ['do_not_translate_keys'],
              },
            },
            do_not_translate_keys: {
              not: {
                required: ['translate_keys'],
              },
            },
          },
          additionalProperties: false,
        },
        ms_word: {
          type: 'object',
          properties: {
            extract_doc_properties: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_comments: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_headers_footers: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_hidden_text: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            accept_revisions: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            exclude_styles: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
            exclude_highlight_colors: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
          },
          additionalProperties: false,
        },
        ms_excel: {
          type: 'object',
          properties: {
            extract_doc_properties: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_hidden_cells: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_diagrams: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_drawings: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_sheet_names: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            exclude_columns: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              default: [],
            },
          },
          additionalProperties: false,
        },
        ms_powerpoint: {
          type: 'object',
          properties: {
            extract_doc_properties: {
              type: 'boolean',
              nullable: true,
              default: false,
            },
            extract_hidden_slides: {
              type: 'boolean',
              nullable: true,
            },
            extract_notes: {
              type: 'boolean',
              nullable: true,
              default: true,
            },
            translate_slides: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
          },
          dependencies: {
            extract_hidden_slides: {
              not: {
                required: ['translate_slides'],
              },
            },
            translate_slides: {
              not: {
                required: ['extract_hidden_slides'],
              },
            },
          },
          additionalProperties: false,
        },
        dita: {
          type: 'object',
          properties: {
            do_not_translate_elements: {
              type: 'array',
              items: {
                $ref: {
                  type: 'string',
                },
              },
              nullable: true,
            },
          },
          required: ['do_not_translate_elements'],
          additionalProperties: false,
        },
      },
    },
    XliffConfigSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
          readOnly: true,
        },
        uid: {
          type: 'integer',
          readOnly: true,
        },
        name: {
          type: 'string',
        },
        rules: {
          type: 'object',
          additionalProperties: false,
          properties: {
            xliff12: {
              $ref: '#/definitions/XliffRule',
            },
            xliff20: {
              $ref: '#/definitions/XliffRule',
            },
          },
        },
      },
      required: ['rules', 'name'],
    },
    XliffRule: {
      type: 'object',
      properties: {
        states: {
          type: 'array',
          example: ['translated'],
          items: {
            $ref: {
              type: 'string',
            },
          },
        },
        analysis: {
          type: 'string',
          example: 'new',
        },
        editor: {
          type: 'string',
          example: 'translated',
        },
        matchCategory: {
          type: 'string',
        },
      },
    },
    ProjectTemplateSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
          readOnly: true,
        },
        uid: {
          type: 'integer',
          readOnly: true,
        },
        name: {
          type: 'string',
        },
        is_default: {
          type: 'boolean',
        },
        id_team: {
          type: 'integer',
        },
        tm_prioritization: {
          type: 'boolean',
        },
        pretranslate_100: {
          type: 'boolean',
        },
        pretranslate_101: {
          type: 'boolean',
        },
        get_public_matches: {
          type: 'boolean',
        },
        subject: {
          type: 'string',
        },
        source_language: {
          type: 'string',
        },
        target_language: {
          type: 'array',
          items: {
            type: 'string',
            example: 'it-IT',
          },
        },
        segmentation_rule: {
          type: 'object',
          nullable: true,
        },
        tm: {
          type: 'array',
          items: {
            type: 'object',
            $ref: '#/definitions/ProjectTemplateSchemaTM',
          },
        },
        mt: {
          type: 'object',
          $ref: '#/definitions/ProjectTemplateSchemaMT',
        },
        payable_rate_template_id: {
          type: 'integer',
          nullable: true,
        },
        qa_model_template_id: {
          type: 'integer',
          nullable: true,
        },
        xliff_config_template_id: {
          type: 'integer',
          nullable: true,
        },
        filters_template_id: {
          type: 'integer',
          nullable: true,
        },
      },
      required: ['name', 'id_team', 'pretranslate_100', 'get_public_matches'],
    },
    ProjectTemplateSchemaTM: {
      type: 'object',
      properties: {
        glos: {
          type: 'boolean',
        },
        is_shared: {
          type: 'boolean',
        },
        key: {
          type: 'string',
        },
        name: {
          type: 'string',
        },
        owner: {
          type: 'boolean',
        },
        tm: {
          type: 'boolean',
        },
        r: {
          type: 'boolean',
        },
        w: {
          type: 'boolean',
        },
        penalty: {
          type: 'integer',
          nullable: true,
          maximum: 100,
          minimum: 0,
        },
      },
    },
    ProjectTemplateSchemaMT: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
        },
        extra: {
          type: 'object',
        },
      },
    },
    QualityModelSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
          readOnly: true,
        },
        uid: {
          type: 'integer',
          readOnly: true,
        },
        label: {
          type: 'string',
        },
        version: {
          type: 'integer',
          readOnly: true,
          description:
            "The model version. It's incremented on every model update.",
          example: 1,
        },
        categories: {
          type: 'array',
          maxItems: 50,
          items: {
            $ref: '#/definitions/QAModelCategory',
          },
        },
        passfail: {
          type: 'object',
          $ref: '#/definitions/PassFail',
        },
        createdAt: {
          type: 'string',
          format: 'date-time',
          required: true,
        },
        modifiedAt: {
          type: 'string',
          format: 'date-time',
          required: true,
        },
        deletedAt: {
          type: 'string',
          format: 'date-time',
          required: true,
        },
      },
      required: ['version', 'label', 'categories', 'passfail'],
    },
    QAModelCategory: {
      type: 'object',
      properties: {
        id_parent: {
          type: 'integer',
        },
        label: {
          type: 'string',
          maxLength: 255,
        },
        code: {
          type: 'string',
          maxLength: 3,
        },
        severities: {
          type: 'array',
          maxItems: 50,
          items: {
            $ref: '#/definitions/QAModelSeverity',
          },
        },
        sort: {
          type: 'integer',
          nullable: true,
        },
      },
      required: ['code', 'label', 'severities'],
    },
    QAModelSeverity: {
      type: 'object',
      properties: {
        label: {
          type: 'string',
        },
        code: {
          type: 'string',
          maxLength: 3,
        },
        penalty: {
          type: 'number',
          format: 'currency',
          examples: ['0', '0.00', '0.05', '19.95'],
        },
        sort: {
          type: 'integer',
          nullable: true,
        },
      },
      required: ['code', 'label', 'penalty'],
    },
    PassFail: {
      type: 'object',
      properties: {
        type: {
          type: 'string',
          enum: ['points_per_thousand'],
        },
        thresholds: {
          type: 'array',
          maxItems: 2,
          items: {
            $ref: '#/definitions/PassFailThreshold',
          },
        },
      },
      required: ['type', 'thresholds'],
    },
    PassFailThreshold: {
      type: 'object',
      properties: {
        label: {
          type: 'string',
          enum: ['R1', 'R2'],
        },
        value: {
          type: 'integer',
        },
      },
      required: ['label', 'value'],
    },
    PayableRateSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
          readOnly: true,
        },
        uid: {
          type: 'integer',
          readOnly: true,
        },
        payable_rate_template_name: {
          type: 'string',
        },
        version: {
          type: 'integer',
          readOnly: true,
          description:
            "The model version. It's incremented on every model update.",
          example: 1,
        },
        breakdowns: {
          type: 'object',
          $ref: '#/definitions/PayableRateBreakdowns',
        },
      },
    },
    PayableRateBreakdowns: {
      type: 'object',
      properties: {
        default: {
          type: 'object',
          properties: {
            NO_MATCH: {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            '50%-74%': {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            '75%-84%': {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            '85%-94%': {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            '95%-99%': {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            '100%': {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            '100%_PUBLIC': {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            REPETITIONS: {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            INTERNAL: {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            MT: {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            ICE: {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
            ICE_MT: {
              type: 'integer',
              maximum: 100,
              minimum: 0,
            },
          },
          additionalProperties: false,
          required: [
            'NO_MATCH',
            '50%-74%',
            '75%-84%',
            '85%-94%',
            '95%-99%',
            '100%',
            '100%_PUBLIC',
            'REPETITIONS',
            'INTERNAL',
            'MT',
            'ICE',
            'ICE_MT',
          ],
        },
      },
      patternProperties: {
        '(^[a-z]{2,3}$)|(^[a-z]{2,3}-[A-Z0-9]{2,3}$)|(^[a-z]{2}-[A-Za-z]{2,4}-[A-Z]{2}$)':
          {
            type: 'object',
            patternProperties: {
              '(^[a-z]{2,3}$)|(^[a-z]{2,3}-[A-Z0-9]{2,3}$)|(^[a-z]{2}-[A-Za-z]{2,4}-[A-Z]{2}$)':
                {
                  type: 'object',
                  properties: {
                    NO_MATCH: {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    '50%-74%': {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    '75%-84%': {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    '85%-94%': {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    '95%-99%': {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    '100%': {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    '100%_PUBLIC': {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    REPETITIONS: {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    INTERNAL: {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    MT: {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    ICE: {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                    ICE_MT: {
                      type: 'integer',
                      maximum: 100,
                      minimum: 0,
                    },
                  },
                  additionalProperties: false,
                  required: [
                    'NO_MATCH',
                    '50%-74%',
                    '75%-84%',
                    '85%-94%',
                    '95%-99%',
                    '100%',
                    '100%_PUBLIC',
                    'REPETITIONS',
                    'INTERNAL',
                    'MT',
                    'ICE',
                    'ICE_MT',
                  ],
                },
            },
            additionalProperties: false,
          },
      },
      additionalProperties: false,
      required: ['default'],
    },

    NewProject: {
      type: 'object',
      properties: {
        status: {
          type: 'string',
          description:
            'Return the creation status of the project. The statuses can be:OK indicating that the creation worked.FAIL indicating that the creation is failed.',
          enum: ['OK', 'FAIL'],
        },
        id_project: {
          type: 'string',
          description:
            'Return the unique id of the project just created. If creation status is FAIL this key will simply be omitted from the result.',
        },
        project_pass: {
          type: 'string',
          description:
            'Return the password of the project just created. If creation status is FAIL this key will simply be omitted from the result.',
        },
        new_keys: {
          type: 'string',
          description:
            'If you specified new as one or more value in the private_tm_key parameter, the new created keys are returned as CSV string (4rcf34rc,r34rcfewf3r2). Otherwise empty string is returned',
        },
      },
    },
    Status: {
      type: 'object',
      properties: {
        name: {
          type: 'string',
        },
        status: {
          type: 'string',
        },
        create_date: {
          type: 'string',
        },
        subject: {
          type: 'string',
        },
        jobs: {
          type: 'array',
          items: {
            $ref: '#/definitions/StatusJob',
          },
        },
      },
    },
    StatusJob: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
        },
        source: {
          type: 'string',
        },
        source_name: {
          type: 'string',
        },
        target: {
          type: 'string',
        },
        target_name: {
          type: 'string',
        },
        chunks: {
          type: 'array',
          items: {
            $ref: '#/definitions/StatusChunk',
          },
        },
        summary: {
          type: 'object',
          properties: {
            in_queue_before: {
              type: 'integer',
            },
            total_segments: {
              type: 'integer',
            },
            segments_analyzed: {
              type: 'integer',
            },
            status: {
              type: 'string',
            },
            total_raw: {
              type: 'integer',
            },
            total_industry: {
              type: 'integer',
            },
            total_equivalent: {
              type: 'integer',
            },
            discount: {
              type: 'integer',
            },
          },
          required: [
            'in_queue_before',
            'total_segments',
            'segments_analyzed',
            'status',
            'total_raw',
            'total_industry',
            'total_equivalent',
            'discount',
          ],
        },
        analyze_url: {
          type: 'string',
        },
      },
      required: [
        'id',
        'source',
        'source_name',
        'target',
        'target_name',
        'summary',
        'chunks',
      ],
    },
    StatusChunk: {
      type: 'object',
      properties: {
        password: {
          type: 'string',
        },
        status: {
          type: 'string',
        },
        engines: {
          type: 'object',
          properties: {
            tm: {
              type: 'object',
              properties: {
                id: {
                  type: 'integer',
                },
                name: {
                  type: 'string',
                },
                type: {
                  type: 'string',
                },
                description: {
                  type: 'string',
                },
              },
              required: ['id', 'name', 'type', 'description'],
            },
            mt: {
              type: 'object',
              properties: {
                id: {
                  type: 'integer',
                },
                name: {
                  type: 'string',
                },
                type: {
                  type: 'string',
                },
                description: {
                  type: 'string',
                },
              },
              required: ['id', 'name', 'type', 'description'],
            },
          },
          required: ['tm', 'mt'],
        },
        memory_keys: {
          type: 'array',
          items: {},
        },
        urls: {
          type: 'object',
          properties: {
            t: {
              type: 'string',
            },
            r1: {
              type: 'string',
            },
            r2: {
              type: 'string',
            },
          },
          required: ['t', 'r1'],
        },
        files: {
          type: 'array',
          items: {
            $ref: '#/definitions/StatusFile',
          },
        },
      },
      required: ['password', 'status', 'engines'],
    },
    StatusFile: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
        },
        id_file_part: {
          type: 'integer',
        },
        name: {
          type: 'string',
        },
        original_name: {
          type: 'string',
        },
        total_raw: {
          type: 'integer',
        },
        total_equivalent: {
          type: 'integer',
        },
        matches: {
          type: 'array',
          items: {
            $ref: '#/definitions/StatusFileMatch',
          },
        },
      },
    },
    StatusFileMatch: {
      type: 'object',
      properties: {
        raw: {
          type: 'integer',
        },
        equivalent: {
          type: 'integer',
        },
        type: {
          type: 'string',
        },
      },
      required: ['raw', 'equivalent', 'type'],
    },
    'Data-Status': {
      type: 'object',
      description:
        'Holds all progress statisticts for every job and for overall project. It contains jobs and summary sub-sections.',
      properties: {
        jobs: {
          $ref: '#/definitions/Jobs',
        },
        summary: {
          $ref: '#/definitions/Summary',
        },
      },
    },
    'Jobs-Status': {
      type: 'object',
      description:
        'Section jobs contains all metadata about job (like URIs, quality reports and languages)',
      properties: {
        langpairs: {
          type: 'object',
          description:
            'the language pairs for your project; an entry for every chunk in the project, with the id-password combination as key and the language pair as the value',
        },
        'job-url': {
          type: 'object',
          description:
            'the links to the chunks of the project; an entry for every chunk in the project, with the id-password combination as key and the link to the chunk as the value.',
        },
        'job-quality-details': {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'a structure containing, for each chunk, an array of 5 objects, each object is a quality check performed on the job; the object contains the type of the check (Typing, Translation, Terminology, Language Quality, Style), the quantity of errors found, the allowed errors threshold and the rating given by the errors/threshold ratio (same as quality-overall)',
        },
        'quality-overall': {
          type: 'object',
          description:
            'the overall quality rating for each chunk (Very good, Good, Acceptable, Poor, Fail)',
        },
      },
    },
    Summary: {
      type: 'object',
      description:
        'Sub-section summary holds statistict for the whole project that are not related to single job objects.',
      properties: {
        NAME: {
          type: 'string',
          description:
            'A list of objects containing error message at system wide level. Every error has a negative numeric code and a textual message ( currently the only error reported is the wrong version number in config.inc.php file and happens only after Matecat updates, so you should never see it ).',
        },
        STATUS: {
          type: 'string',
          description:
            'The status the project is from analysis perspective. NEW - just created, not analyzed yet, FAST_OK - preliminary (fast) analysis completed, now running translations ("TM") analysis, DONE - analysis complete.',
          enum: ['NEW', 'FAST_OK', 'DONE'],
        },
        IN_QUEUE_BEFORE: {
          type: 'string',
          description:
            "Number of segments belonging to other projects that are being analyzed before yours; it's the wait time for you.",
        },
        TOTAL_SEGMENTS: {
          type: 'string',
          description: 'number of segments belonging to your project.',
        },
        SEGMENTS_ANALYZED: {
          type: 'string',
          description: 'analysis progress, on TOTAL_SEGMENTS',
        },
        TOTAL_RAW_WC: {
          type: 'string',
          description:
            'number of words (word count) of your project, as extracted by the textual parsers',
        },
        TOTAL_STANDARD_WC: {
          type: 'string',
          description: 'word count, minus the sentences that are repeated',
        },
        TOTAL_FAST_WC: {
          type: 'string',
          description:
            'word count, minus the sentences that are partially repeated',
        },
        TOTAL_TM_WC: {
          type: 'string',
          description:
            'word count, with sentences found in the cloud translation memory discounted from the total; this depends on the percentage of overlapping between the sentences of your project and the past translations',
        },
        TOTAL_PAYABLE: {
          type: 'string',
          description: 'total word count, after analysis.',
        },
      },
    },
    Jobs: {
      type: 'object',
      description:
        'Sub-section jobs holds statistict for all the job objects. The numerical keys on the first level are the IDs of the jobs contained in the project. Each job identifies a target language; as such, there is a 1-1 mapping between ID and target languages in your project. A job holds a chunks and a totals section.',
      properties: {
        id_job: {
          $ref: '#/definitions/Job',
        },
      },
    },
    Job: {
      type: 'object',
      description:
        'The numerical keys on the first level are the IDs of the jobs contained in the project. Each job identifies a target language; as such, there is a 1-1 mapping between ID and target languages in your project.',
      properties: {
        chunk: {
          type: 'object',
          description:
            'A structure modeling a portion of content to translate.  A whole file can be splitted in multiple chunks, to be distributed to multiple translators, or can be enveloped in a single chunk. Each chunk has a password as first level key and a numerical ID as second level key to identify different chunks for the same file. Each chunk contains the same structure of the totals section. The sum of the chunks equals to the totals.',
        },
        totals: {
          $ref: '#/definitions/Totals',
        },
      },
    },
    Totals: {
      type: 'object',
      description:
        'Contains all analysis statistics for all files in the current job (i.e., all files that have to be translated in a target language)',
      properties: {
        job_pass: {
          $ref: '#/definitions/Total',
        },
      },
    },
    Total: {
      type: 'object',
      description: 'password as first level key.',
      properties: {
        TOTAL_PAYABLE: {
          type: 'array',
          items: {
            type: 'object',
          },
          description: 'total word count, after analysis',
        },
        REPETITIONS: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'cumulative word count for the segments that repeat themselves in the file',
        },
        INTERNAL_MATCHES: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'cumulative word count for the segments that fuzzily overlap with others in the file, while not being an exact repetition',
        },
        MT: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'cumulative word count for all segments that can be translated with machine translation; it accounts for all the information that could not be discounted by repetitions, internal matches or translation memory',
        },
        NEW: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            "cumulative word count for segments that can't be discounted with repetition or internal matches; it's the net translation effort",
        },
        TM_100: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'cumulative word count for the exact matches found in TM server',
        },
        TM_75_99: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'cumulative word count for partial matches in the TM that cover 75-99% of each segment',
        },
        ICE: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'cumulative word count for 100% TM matches that also share the same context with the TM',
        },
        NUMBERS_ONLY: {
          type: 'array',
          items: {
            type: 'object',
          },
          description:
            'cumulative word counts for segments made of numberings, dates and similar not translatable data ( i.e. 93 / 127 )',
        },
      },
    },
    ChangePasswordResponse: {
      type: 'object',
      properties: {
        id: {
          type: 'string',
          description: 'Returns the id of the resource just updated',
        },
        new_pwd: {
          type: 'string',
          description: 'Returns the new pass of the resource just updated',
        },
        old_pwd: {
          type: 'string',
          description: 'Returns the old pass of the resource just updated',
        },
      },
    },
    Urls: {
      type: 'object',
      properties: {
        files: {
          $ref: '#/definitions/JobFiles',
        },
        jobs: {
          $ref: '#/definitions/UrlsJobs',
        },
      },
    },
    JobFiles: {
      type: 'array',
      items: {
        $ref: '#/definitions/JobFile',
      },
    },
    JobFile: {
      type: 'object',
      properties: {
        id: {
          type: 'string',
        },
        name: {
          type: 'string',
        },
        original_download_url: {
          type: 'string',
        },
        translation_download_url: {
          type: 'string',
        },
        xliff_download_url: {
          type: 'string',
        },
      },
    },
    UrlsJobs: {
      type: 'array',
      items: {
        $ref: '#/definitions/UrlsJob',
      },
    },
    UrlsJob: {
      type: 'object',
      properties: {
        id: {
          type: 'string',
        },
        target_lang: {
          type: 'string',
        },
        chunks: {
          type: 'array',
          items: {
            $ref: '#/definitions/Url',
          },
        },
      },
    },
    Url: {
      type: 'object',
      properties: {
        password: {
          type: 'string',
        },
        translate_url: {
          type: 'string',
        },
        revise_url: {
          type: 'string',
        },
      },
    },
    TranslationIssues: {
      type: 'array',
      items: {
        $ref: '#/definitions/Issue',
      },
    },
    Issue: {
      type: 'object',
      properties: {
        comment: {
          type: 'string',
        },
        created_at: {
          type: 'string',
        },
        id: {
          type: 'string',
        },
        id_category: {
          type: 'string',
        },
        id_job: {
          type: 'string',
        },
        id_segment: {
          type: 'string',
        },
        is_full_segment: {
          type: 'string',
        },
        severity: {
          type: 'string',
        },
        start_node: {
          type: 'string',
        },
        start_offset: {
          type: 'string',
        },
        end_node: {
          type: 'string',
        },
        end_offset: {
          type: 'string',
        },
        translation_version: {
          type: 'string',
        },
        target_text: {
          type: 'string',
        },
        penality_points: {
          type: 'string',
        },
        rebutted_at: {
          type: 'string',
        },
      },
    },
    Comments: {
      type: 'array',
      items: {
        $ref: '#/definitions/Comment',
      },
    },
    Comment: {
      type: 'object',
      properties: {
        id: {
          type: 'string',
        },
        id_job: {
          type: 'string',
        },
        id_segment: {
          type: 'string',
        },
        created_at: {
          type: 'string',
        },
        email: {
          type: 'string',
        },
        full_name: {
          type: 'string',
        },
        uid: {
          type: 'integer',
          format: 'int32',
        },
        resolved_at: {
          type: 'string',
        },
        source_page: {
          type: 'integer',
          format: 'int32',
        },
        mwssage_type: {
          type: 'integer',
          format: 'int32',
        },
        message: {
          type: 'string',
        },
      },
    },
    QualityReport: {
      type: 'object',
      properties: {
        chunk: {
          type: 'object',
        },
        job: {
          type: 'object',
        },
        project: {
          type: 'object',
        },
      },
    },
    QualityReportSegments: {
      type: 'object',
      properties: {
        segments: {
          type: 'array',
          items: {
            $ref: '#/definitions/QualityReportSegment',
          },
        },
        first_segment: {
          type: 'integer',
          format: 'int32',
        },
        last_segment: {
          type: 'integer',
          format: 'int32',
        },
        _params: {
          type: 'object',
          properties: {
            ref_segment: {
              type: 'integer',
              format: 'int32',
            },
            where: {
              type: 'string',
            },
            step: {
              type: 'integer',
              format: 'int32',
            },
            filter: {
              type: 'string',
            },
          },
        },
        _links: {
          type: 'object',
          properties: {
            base: {
              type: 'string',
            },
            last_segment_id: {
              type: 'integer',
              format: 'int32',
            },
            pages: {
              type: 'integer',
            },
            items_per_page: {
              type: 'integer',
            },
            total_items: {
              type: 'integer',
            },
            self: {
              type: 'string',
            },
            next: {
              type: 'string',
              nullable: true,
            },
            prev: {
              type: 'string',
              nullable: true,
            },
          },
        },
      },
    },
    QualityReportSegment: {
      type: 'object',
      properties: {
        comments: {
          type: 'array',
          items: {},
          nullable: true,
        },
        dataRefMap: {
          type: 'array',
          items: {},
          nullable: true,
        },
        edit_distance: {
          type: 'string',
          nullable: true,
        },
        file: {
          $ref: '#/definitions/QualityReportFile',
        },
        ice_locked: {
          type: 'integer',
        },
        ice_modified: {
          type: 'boolean',
        },
        id: {
          type: 'integer',
          format: 'int32',
        },
        is_pre_translated: {
          type: 'boolean',
        },
        issues: {
          type: 'array',
          items: {
            $ref: '#/definitions/Issue',
          },
        },
        last_revisions: {
          type: 'array',
          items: {},
          nullable: true,
        },
        last_translation: {
          type: 'string',
        },
        locked: {
          type: 'integer',
        },
        match_type: {
          type: 'string',
        },
        parsed_time_to_edit: {
          type: 'array',
          items: {
            type: 'string',
          },
          nullable: true,
        },
        pee: {
          type: 'integer',
        },
        pee_translation_revise: {
          type: 'integer',
        },
        pee_translation_suggestion: {
          type: 'integer',
        },
        raw_word_count: {
          type: 'float',
        },
        revision_number: {
          type: 'integer',
          nullable: true,
        },
        secs_per_word: {
          type: 'float',
        },
        segment: {
          type: 'string',
        },
        segment_hash: {
          type: 'string',
          nullable: true,
        },
        source_page: {
          type: 'string',
        },
        status: {
          type: 'string',
        },
        suggestion: {
          type: 'string',
        },
        suggestion_match: {
          type: 'string',
        },
        suggestion_source: {
          type: 'string',
        },
        target: {
          type: 'string',
        },
        time_to_edit: {
          type: 'integer',
        },
        time_to_edit_revise: {
          type: 'integer',
        },
        time_to_edit_revise_2: {
          type: 'integer',
        },
        time_to_edit_translation: {
          type: 'integer',
        },
        translation: {
          type: 'string',
        },
        version: {
          type: 'string',
        },
        version_number: {
          type: 'string',
        },
        warnings: {
          type: 'object',
          properties: {
            details: {
              type: 'object',
              properties: {
                issues_info: {
                  type: 'object',
                },
                id_segment: {
                  type: 'string',
                },
                tag_mismatch: {
                  type: 'object',
                },
              },
            },
          },
        },
      },
    },
    QualityReportFile: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
          format: 'int32',
        },
        first_segment: {
          type: 'integer',
          format: 'int32',
        },
        last_segment: {
          type: 'integer',
          format: 'int32',
        },
        file_name: {
          type: 'string',
        },
        raw_words: {
          type: 'integer',
        },
        weighted_words: {
          type: 'integer',
        },
        standard_words: {
          type: 'integer',
        },
        metadata: {
          type: 'array',
        },
      },
    },
    SegmentIssueReport: {
      type: 'object',
      properties: {
        modified_segments_count: {
          type: 'integer',
        },
        issue_count: {
          type: 'integer',
        },
        modified_segments: {
          type: 'array',
          items: {
            $ref: {
              type: 'object',
              properties: {
                id_segment: {
                  type: 'integer',
                },
                issue_count: {
                  type: 'integer',
                },
              },
            },
          },
        },
      },
    },
    TranslationVersions: {
      type: 'array',
      items: {
        $ref: '#/definitions/TranslationVersion',
      },
    },
    TranslationVersion: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
          format: 'int32',
        },
        id_segment: {
          type: 'integer',
          format: 'int32',
        },
        id_job: {
          type: 'integer',
          format: 'int32',
        },
        translation: {
          type: 'string',
        },
        version_number: {
          type: 'string',
        },
        propagated_from: {
          type: 'integer',
          format: 'int32',
        },
        created_at: {
          type: 'string',
        },
      },
    },
    Options: {
      type: 'object',
      properties: {
        speech2text: {
          type: 'integer',
        },
        tag_projection: {
          type: 'integer',
        },
        lexiqa: {
          type: 'integer',
        },
      },
    },
    JobMetadata: {
      type: 'object',
      properties: {
        project: {
          type: 'object',
        },
        job: {
          type: 'object',
        },
        files: {
          type: 'array',
          items: {
            type: 'object',
            properties: {
              id: {
                type: 'integer',
              },
              filename: {
                type: 'string',
              },
              data: {
                type: 'object',
              },
            },
          },
        },
      },
    },
    UploadGlossaryStatusObject: {
      type: 'object',
      properties: {
        error: {
          type: 'array',
          items: {
            type: 'object',
          },
        },
        data: {
          $ref: '#/definitions/UploadGlossaryStatus',
        },
        success: {
          type: 'boolean',
        },
      },
    },
    UploadGlossaryStatus: {
      type: 'object',
      properties: {
        done: {
          type: 'integer',
        },
        total: {
          type: 'integer',
        },
        source_lang: {
          type: 'string',
        },
        target_lang: {
          type: 'string',
        },
        completed: {
          type: 'boolean',
        },
      },
    },

    Languages: {
      type: 'array',
      items: {
        $ref: '#/definitions/Language',
      },
    },

    Language: {
      type: 'object',
      properties: {
        code: {
          type: 'string',
          description: 'Rfc code',
        },
        name: {
          type: 'string',
          description: 'Language name',
        },
        direction: {
          type: 'string',
          enum: ['ltr', 'rtl'],
          description:
            'Language direction, ltr (left-to-right text) or rtl (right-to-left text)',
        },
      },
    },
    Files: {
      type: 'object',
      properties: {
        Office: {
          type: 'array',
          items: {
            type: 'array',
            items: {
              $ref: '#/definitions/File',
            },
          },
        },
        Web: {
          type: 'array',
          items: {
            type: 'array',
            items: {
              $ref: '#/definitions/File',
            },
          },
        },
        'Scanned Files': {
          type: 'array',
          items: {
            type: 'array',
            items: {
              $ref: '#/definitions/File',
            },
          },
        },
        'Interchange Formats': {
          type: 'array',
          items: {
            type: 'array',
            items: {
              $ref: '#/definitions/File',
            },
          },
        },
        'Desktop Publishing': {
          type: 'array',
          items: {
            type: 'array',
            items: {
              $ref: '#/definitions/File',
            },
          },
        },
        Localization: {
          type: 'array',
          items: {
            type: 'array',
            items: {
              $ref: '#/definitions/File',
            },
          },
        },
      },
    },

    File: {
      type: 'object',
      properties: {
        ext: {
          type: 'string',
        },
        class: {
          type: 'string',
        },
      },
    },

    PendingInvitation: {
      type: 'array',
      items: {
        type: 'string',
        description: 'Email address of the invited user',
      },
    },

    TeamMembersList: {
      type: 'object',
      properties: {
        members: {
          type: 'array',
          items: {
            $ref: '#/definitions/TeamMember',
          },
        },
        pending_invitations: {
          type: 'array',
          items: {
            $ref: '#/definitions/PendingInvitation',
          },
        },
      },
    },

    TeamMember: {
      type: 'object',
      properties: {
        id: {type: 'integer'},
        id_team: {type: 'integer'},
        user: {
          type: 'object',
          $ref: '#/definitions/User',
        },
      },
    },

    User: {
      type: 'object',
      properties: {
        uid: {type: 'integer'},
        first_name: {type: 'string'},
        last_name: {type: 'string'},
        email: {type: 'string'},
        has_password: {type: 'boolean'},
      },
    },

    TeamList: {
      type: 'object',
      properties: {
        teams: {
          type: 'array',
          items: {
            $ref: '#/definitions/Team',
          },
        },
      },
    },

    TeamItem: {
      type: 'object',
      properties: {
        team: {
          type: 'object',
          $ref: '#/definitions/Team',
        },
      },
    },

    Team: {
      type: 'object',
      properties: {
        id: {type: 'integer', required: true},
        name: {type: 'string', required: true},
        type: {
          type: 'string',
          enum: ['general', 'personal'],
          required: true,
        },
        created_at: {
          type: 'string',
          format: 'date-time',
          required: true,
        },
        created_by: {type: 'integer', required: true},
        pending_invitations: {type: 'array', items: 'string'},
      },
    },

    EnginesList: {
      type: 'array',
      items: {
        $ref: '#/definitions/Engine',
      },
    },
    Engine: {
      type: 'object',
      properties: {
        id: {type: 'integer', required: true},
        name: {type: 'string', required: true},
        type: {
          type: 'string',
          enum: ['MT', 'TM'],
          required: true,
        },
        description: {
          type: 'string',
          required: true,
        },
      },
    },

    KeysList: {
      type: 'object',
      properties: {
        private_keys: {
          type: 'array',
          items: {
            $ref: '#/definitions/Key',
          },
        },
        shared_keys: {
          type: 'array',
          items: {
            $ref: '#/definitions/Key',
          },
        },
      },
    },
    KeysListComplete: {
      type: 'object',
      properties: {
        tm: {
          type: 'boolean',
        },
        glos: {
          type: 'boolean',
        },
        owner: {
          type: 'boolean',
        },
        name: {
          type: 'string',
          example: 'Key name',
        },
        key: {
          type: 'string',
          example: 'xxxyyyzzz',
        },
        penalty: {
          type: 'number',
          example: 0.93,
        },
        is_shared: {
          type: 'boolean',
        },
      },
    },
    Key: {
      type: 'object',
      properties: {
        key: {type: 'string', required: true},
        name: {type: 'string', required: true},
      },
    },

    ProjectList: {
      type: 'object',
      properties: {
        projects: {
          type: 'array',
          items: {
            $ref: '#/definitions/Project',
          },
        },
      },
    },
    ProjectItem: {
      type: 'object',
      properties: {
        project: {
          type: 'object',
          $ref: '#/definitions/Project',
        },
      },
    },

    ProjectsItems: {
      type: 'object',
      properties: {
        projects: {
          type: 'array',
          items: {
            $ref: '#/definitions/Project',
          },
        },
      },
    },

    Project: {
      type: 'object',
      properties: {
        id: {type: 'integer'},
        password: {type: 'string'},
        name: {type: 'string'},
        id_team: {type: 'integer'},
        id_assignee: {type: 'integer'},
        create_date: {type: 'string', format: 'date-time'},
        fast_analysis_wc: {type: 'float'},
        standard_analysis_wc: {type: 'float'},
        project_slug: {type: 'string'},
        features: {type: 'string'},
        is_cancelled: {type: 'boolean'},
        is_archived: {type: 'boolean'},
        remote_file_service: {type: 'string'},
        jobs: {
          type: 'array',
          items: {
            $ref: '#/definitions/ExtendedJob',
          },
        },
      },
    },

    CompletionStatusItem: {
      type: 'object',
      properties: {
        project_status: {
          type: 'object',
          properties: {
            revise: {
              type: 'array',
              items: {
                type: 'object',
                properties: {
                  id: {type: 'string'},
                  password: {type: 'string'},
                  completed: {type: 'boolean'},
                  completed_at: {type: 'string', format: 'date-time'},
                  event_id: {type: 'string'},
                },
              },
            },
            translate: {
              type: 'array',
              items: {
                type: 'object',
                properties: {
                  id: {type: 'string'},
                  password: {type: 'string'},
                  completed: {type: 'boolean'},
                  completed_at: {type: 'string', format: 'date-time'},
                  event_id: {type: 'string'},
                },
              },
            },
            id: {type: 'string'},
            completed: {type: 'boolean'},
          },
        },
      },
    },

    Chunk: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
        },
        chunks: {
          type: 'array',
          items: {
            type: 'object',
            $ref: '#/definitions/ExtendedJob',
          },
        },
      },
    },

    ExtendedJob: {
      type: 'object',

      properties: {
        id: {type: 'integer'},
        password: {type: 'string'},
        source: {type: 'string'},
        target: {type: 'string'},
        sourceTxt: {type: 'string'},
        targetTxt: {type: 'string'},
        status: {type: 'string'},
        subject: {type: 'string'},
        owner: {type: 'string', format: 'email'},
        open_threads_count: {type: 'integer'},
        create_timestamp: {type: 'integer'},
        created_at: {type: 'string', format: 'date-time'},
        create_date: {type: 'string', format: 'date-time'},
        formatted_create_date: {type: 'string'},
        quality_overall: {type: 'string'},
        pee: {type: 'integer'},
        tte: {type: 'integer', format: 'seconds'},
        private_tm_key: {
          type: 'array',
          items: {
            type: 'string',
          },
        },
        warnings_count: {type: 'integer'},
        warning_segments: {
          type: 'array',
          items: {
            type: 'integer',
          },
        },
        outsource: {
          type: 'object',
          $ref: '#/definitions/OutsourceConfirmation',
        },

        translator: {
          type: 'object',
          $ref: '#/definitions/Translator',
        },

        total_raw_wc: {type: 'float'},
        urls: {
          type: 'object',
          $ref: '#/definitions/JobUrl',
        },
        stats: {
          type: 'object',
          $ref: '#/definitions/Stats',
        },
        quality_summary: {
          type: 'object',
          $ref: '#/definitions/QualitySummary',
        },
      },
    },

    QualitySummary: {
      type: 'object',
      properties: {
        equivalent_class: {type: 'integer'},
        quality_overall: {type: 'string'},
        errors_count: {type: 'integer'},
        revise_issues: {
          type: 'object',
          properties: {
            typing: {type: 'object', $ref: '#/definitions/ReviseIssue'},
            translation: {type: 'object', $ref: '#/definitions/ReviseIssue'},
            terminology: {type: 'object', $ref: '#/definitions/ReviseIssue'},
            language_quality: {
              type: 'object',
              $ref: '#/definitions/ReviseIssue',
            },
            style: {type: 'object', $ref: '#/definitions/ReviseIssue'},
          },
        },
      },
    },

    ReviseIssue: {
      type: 'object',
      properties: {
        allowed: {type: 'number'},
        found: {type: 'integer'},
      },
    },

    JobUrl: {
      type: 'object',
      properties: {
        translate_url: {type: 'string'},
        revise_url: {type: 'string'},
        original_download_url: {type: 'string'},
        translation_download_url: {type: 'string'},
        xliff_download_url: {type: 'string'},
      },
    },

    Stats: {
      type: 'object',
      properties: {
        equivalent: {
          type: 'object',
          properties: {
            new: {type: 'number'},
            draft: {type: 'number'},
            translated: {type: 'number'},
            approved: {type: 'number'},
            approved2: {type: 'number'},
            total: {type: 'number'},
          },
        },
        raw: {
          type: 'object',
          properties: {
            new: {type: 'number'},
            draft: {type: 'number'},
            translated: {type: 'number'},
            approved: {type: 'number'},
            approved2: {type: 'number'},
            total: {type: 'number'},
          },
        },
      },
    },

    JobTranslatorItem: {
      type: 'object',
      properties: {
        id: {type: 'integer'},
        password: {type: 'password'},
        translator: {
          type: 'object',
          $ref: '#/definitions/Translator',
        },
      },
    },

    Translator: {
      type: 'object',
      properties: {
        email: {type: 'string', format: 'email'},
        added_by: {type: 'integer'},
        delivery_date: {type: 'string'},
        delivery_timestamp: {type: 'string'},
        source: {type: 'string'},
        target: {type: 'string'},
        id_translator_profile: {type: 'integer'},
        user: {
          type: 'object',
          $ref: '#/definitions/User',
        },
      },
    },

    OutsourceConfirmation: {
      type: 'object',
      properties: {
        create_timestamp: {
          type: 'string',
          format: 'date-time',
          required: true,
        },
        delivery_timestamp: {
          type: 'integer',
        },
        quote_review_link: {},
      },
    },

    ExtendedJobItem: {
      type: 'object',
      properties: {
        job: {
          $ref: '#/definitions/ExtendedJob',
        },
      },
    },

    ProjectCreationStatus: {
      type: 'object',
      properties: {
        status: {type: 'integer'},
        message: {type: 'string'},
        id_project: {type: 'integer'},
        project_pass: {type: 'string'},
        project_name: {type: 'string'},
        new_keys: {type: 'string'},
        analyze_url: {type: 'string'},
      },
    },

    Error: {
      type: 'object',
      properties: {
        errors: {
          type: 'array',
          items: {
            type: 'object',
            properties: {
              code: 'integer',
              message: 'string',
            },
          },
        },
        data: {
          type: 'array',
          items: {type: 'object'},
          description:
            'This property contains any debug data that can ' +
            'serve for better understanding of the error',
        },
      },
    },

    Split: {
      type: 'object',
      properties: {
        data: {
          type: 'object',
          properties: {
            raw_word_count: 'float',
            eq_word_count: 'float',
            job_first_segment: 'integer',
            job_last_segment: 'integer',
            id: 'integer',
            show_in_cattool: 'integer',
            chunks: {
              type: 'array',
              items: {
                type: 'object',
                properties: {
                  eq_word_count: 'integer',
                  raw_word_count: 'integer',
                  segment_start: 'integer',
                  segment_end: 'integer',
                  last_opened_segment: 'integer',
                },
              },
            },
          },
        },
      },
    },

    ChangeStatus: {
      type: 'object',
      properties: {
        code: {type: 'integer', required: true},
        data: {type: 'string', required: true},
        status: {
          type: 'string',
          enum: ['active', 'cancelled', 'archived'],
          required: true,
        },
      },
    },

    CreateReview: {
      type: 'object',
      properties: {
        chunk_review: {
          type: 'array',
          items: {
            $ref: '#/definitions/CreateReviewItem',
          },
        },
      },
    },

    CreateReviewItem: {
      type: 'object',
      properties: {
        id: {
          type: 'integer',
        },
        id_job: {
          type: 'string',
        },
        review_password: {
          type: 'string',
        },
      },
    },

    Segment: {
      type: 'object',
      properties: {
        id_segment: {type: 'integer'},
        autopropagated_from: {type: 'string'},
        status: {type: 'string'},
        translation: {type: 'string'},
        translation_date: {type: 'string'},
        match_type: {type: 'string'},
        context_hash: {type: 'string'},
        locked: {type: 'integer'},
        version_number: {type: 'integer'},
        issues: {
          type: 'array',
          items: {
            $ref: '#/definitions/Issue',
          },
        },
      },
    },
  },
  securityDefinitions: {
    ApiKeyAuth: {
      type: 'apiKey',
      in: 'header',
      name: 'x-matecat-key',
    },
  },
  security: [
    {
      ApiKeyAuth: [],
    },
  ],
}
