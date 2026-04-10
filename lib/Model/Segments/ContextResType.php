<?php

namespace Model\Segments;

enum ContextResType: string
{
    case X_PATH                 = 'x-path';
    case X_CLIENT_NODEPATH      = 'x-client_nodepath';
    case X_TAG_ID               = 'x-tag-id';
    case X_CSS_CLASS            = 'x-css_class';
    case X_ATTRIBUTE_NAME_VALUE = 'x-attribute_name_value';
}
